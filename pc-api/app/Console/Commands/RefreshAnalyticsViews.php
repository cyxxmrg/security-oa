<?php

namespace App\Console\Commands;

use App\Jobs\SlowRequestAlertJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * V1.2.7 E1: 刷新 6 大报表物化视图
 *
 * 用法:
 *   php artisan analytics:refresh           # 刷新全部
 *   php artisan analytics:refresh mv_revenue_monthly  # 刷新单个
 *
 * 调度: 每天凌晨 02:00 (Routes/console.php 配)
 * 性能: 单视图 REFRESH MATERIALIZED VIEW CONCURRENTLY 大约 2-10s (取决于数据量)
 */
class RefreshAnalyticsViews extends Command
{
    protected $signature = 'analytics:refresh {view? : 单个视图名} {--no-concurrent : 强制非 CONCURRENTLY (会锁表但更快)}';
    protected $description = '刷新 BI 报表的物化视图 (凌晨调度任务)';

    private const VIEWS = [
        'mv_revenue_monthly' => '月度营收',
        'mv_sales_funnel'    => '销售漏斗',
        'mv_project_health'  => '项目健康度',
        'mv_customer_rfm'    => '客户 RFM',
        'mv_inventory_aging' => '库存周转',
        'mv_finance_pnl'     => '财务利润表',
    ];

    public function handle(): int
    {
        $target = $this->argument('view');
        $useConcurrent = ! $this->option('no-concurrent');

        if ($target) {
            if (!array_key_exists($target, self::VIEWS)) {
                $this->error("未知视图: {$target}");
                $this->line('可用: ' . implode(', ', array_keys(self::VIEWS)));
                return self::FAILURE;
            }
            $views = [$target => self::VIEWS[$target]];
        } else {
            $views = self::VIEWS;
        }

        $successCount = 0;
        $failCount    = 0;
        $startTime    = microtime(true);

        foreach ($views as $name => $label) {
            $this->line("  ⏳ 刷新 {$name} ({$label})...");
            $t0 = microtime(true);
            try {
                // 刷新前统计 row count
                $beforeCnt = DB::selectOne("SELECT COUNT(*) AS cnt FROM {$name}")->cnt ?? 0;

                $sql = $useConcurrent
                    ? "REFRESH MATERIALIZED VIEW CONCURRENTLY {$name}"
                    : "REFRESH MATERIALIZED VIEW {$name}";
                DB::statement($sql);

                $afterCnt = DB::selectOne("SELECT COUNT(*) AS cnt FROM {$name}")->cnt ?? 0;
                $elapsed = round((microtime(true) - $t0) * 1000);

                $this->info("  ✅ {$name}: {$beforeCnt} → {$afterCnt} rows, {$elapsed}ms");
                $successCount++;

                // 写 cache 记录刷新时间 (供 /analytics/refresh-status fallback)
                \Illuminate\Support\Facades\Cache::put('analytics:refresh_at', now()->toIso8601String(), 86400);
                // 视图自带 refreshed_at 列时更新 MAX
                if (DB::selectOne("SELECT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_name=? AND column_name='refreshed_at') AS h", [$name])->h) {
                    DB::statement("UPDATE {$name} SET refreshed_at = NOW()");
                }
            } catch (\Throwable $e) {
                $elapsed = round((microtime(true) - $t0) * 1000);
                $this->error("  ❌ {$name}: FAIL after {$elapsed}ms - " . $e->getMessage());
                Log::error('AnalyticsView refresh failed', [
                    'view' => $name,
                    'msg'  => $e->getMessage(),
                    'trace'=> $e->getTraceAsString(),
                ]);

                // 发送告警 (异步, 不阻塞主流程)
                SlowRequestAlertJob::dispatch([
                    'type'  => 'analytics_view_refresh_failed',
                    'view'  => $name,
                    'error' => $e->getMessage(),
                    'ms'    => $elapsed,
                ], 'critical');

                $failCount++;
            }
        }

        $total = round((microtime(true) - $startTime) * 1000);
        $this->line('');
        $this->info(sprintf(
            '完成: %d 成功 / %d 失败, 总耗时 %dms',
            $successCount, $failCount, $total
        ));

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
