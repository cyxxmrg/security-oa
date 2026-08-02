<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use App\Models\Warranty;
use App\Services\WarrantyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * V0.4.5 每日 22:00 扫描质保期
 *
 * 行为:
 *  1. 标记已过期 (active/expiring → expired)
 *  2. 扫描 N 天内即将到期的质保期
 *  3. 给每个到期质保期的:
 *     - 项目负责人 (project.manager_id)
 *     - 项目创建人 (project.created_by) - 若存在
 *     发站内通知 + 邮件
 *  4. 输出统计日志
 *
 * 用法:
 *   php artisan warranty:scan-expiry                     # 默认 30 天
 *   php artisan warranty:scan-expiry --within-days=14    # 自定义
 *   php artisan warranty:scan-expiry --dry-run           # 仅统计不发通知
 */
class ScanWarrantyExpiry extends Command
{
    protected $signature = 'warranty:scan-expiry
        {--within-days=30 : 多少天内到期算即将到期}
        {--dry-run : 仅统计, 不修改状态不发送通知}';

    protected $description = '每日 22:00 扫描质保期 (即将到期提醒 + 已过期自动更新)';

    public function handle(WarrantyService $service): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $withinDays= max(1, (int) $this->option('within-days'));
        $today     = now()->toDateString();

        $this->info("开始扫描质保期 (withinDays={$withinDays}, dry-run=" . ($dryRun ? 'yes' : 'no') . ", today={$today})");

        // 1) 标记已过期
        $expiredCount = $dryRun
            ? Warranty::whereNull('deleted_at')
                ->whereIn('status', [Warranty::STATUS_ACTIVE, Warranty::STATUS_EXPIRING])
                ->where('end_date', '<', $today)
                ->count()
            : $service->markExpiredWarranties();
        $this->info("已过期标记: {$expiredCount} 个");

        // 2) 找出即将到期的 id 列表
        $expiringIds = $service->scanExpiringWarranties($withinDays);
        $this->info("即将到期 ({$withinDays} 天内): " . count($expiringIds) . " 个");

        if (empty($expiringIds)) {
            $this->info('[ScanWarrantyExpiry] 扫描完成, 无即将到期质保期');
            $this->logSummary($expiredCount, 0, 0, 0, $dryRun);
            return 0;
        }

        // 3) 加载详细数据, 准备通知
        $warranties = Warranty::with([
            'project:id,name,project_no,manager_id,created_by',
            'project.manager:id,name,email',
            'customer:id,name',
        ])
            ->whereNull('deleted_at')
            ->whereIn('id', $expiringIds)
            ->get();

        $notified = 0;
        $emailed  = 0;
        $errors   = 0;

        foreach ($warranties as $w) {
            try {
                $project = $w->project;
                if (!$project) {
                    $this->warn("  ! warranty #{$w->id} 关联项目不存在, 跳过");
                    continue;
                }

                // 收件人: 项目负责人 + 项目创建人 (去重)
                $receivers = collect();
                if ($project->manager_id) {
                    $u = User::find($project->manager_id);
                    if ($u) {
                        $receivers->push($u);
                    }
                }
                if (!empty($project->created_by) && $project->created_by !== $project->manager_id) {
                    $u = User::find($project->created_by);
                    if ($u) {
                        $receivers->push($u);
                    }
                }

                if ($receivers->isEmpty()) {
                    $this->warn("  ! warranty #{$w->id} 项目无负责人/创建人, 跳过");
                    continue;
                }

                $daysLeft = max(0, (int) now()->startOfDay()
                    ->diffInDays(Carbon\Carbon::parse($w->end_date)->startOfDay(), false));
                $title   = "⏰ 质保期即将到期: {$w->warranty_no} (剩 {$daysLeft} 天)";
                $content = $this->buildContent($w, $project, $daysLeft);

                foreach ($receivers as $u) {
                    if ($dryRun) {
                        $this->line("  [DRY] → {$u->name} ({$u->email}): {$title}");
                        $notified++;
                        continue;
                    }

                    // 站内通知
                    $this->sendDatabaseNotification($u, $title, $content, [
                        'warranty_id'  => $w->id,
                        'warranty_no'  => $w->warranty_no,
                        'project_id'   => $project->id,
                        'end_date'     => $w->end_date?->toDateString(),
                        'days_left'    => $daysLeft,
                    ]);
                    $notified++;

                    // 邮件
                    if ($u->email) {
                        try {
                            Mail::raw($content, function ($msg) use ($u, $title) {
                                $msg->to($u->email, $u->name)->subject($title);
                            });
                            $emailed++;
                        } catch (\Throwable $e) {
                            Log::warning('ScanWarrantyExpiry 邮件发送失败', [
                                'warranty_id' => $w->id,
                                'user_id'     => $u->id,
                                'email'       => $u->email,
                                'msg'         => $e->getMessage(),
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('ScanWarrantyExpiry 单条处理失败', [
                    'warranty_id' => $w->id ?? null,
                    'msg'         => $e->getMessage(),
                ]);
                $this->error("  ✗ warranty #" . ($w->id ?? '?') . ": {$e->getMessage()}");
            }
        }

        $this->logSummary($expiredCount, count($expiringIds), $notified, $emailed, $dryRun, $errors);
        $this->info("[ScanWarrantyExpiry] expired={$expiredCount} expiring=" . count($expiringIds)
            . " notified={$notified} emailed={$emailed} errors={$errors}");

        return 0;
    }

    /**
     * 构建通知内容
     */
    private function buildContent(Warranty $w, Project $project, int $daysLeft): string
    {
        $lines = [
            "项目「{$project->name}」的质保期将在 {$daysLeft} 天后到期。",
            "",
            "质保期编号: {$w->warranty_no}",
            "项目 ID:    {$project->id}",
            "项目编号:   {$project->project_no}",
            "客户:       " . ($w->customer->name ?? "-"),
            "起止日期:   {$w->start_date} ~ {$w->end_date}",
            "剩余天数:   {$daysLeft} 天",
            "",
            "请尽快安排:",
            "  1. 客户续签沟通",
            "  2. 准备新的质保期 / 服务单",
            "  3. 检查关联设备运行状态",
        ];
        return implode("\n", $lines);
    }

    /**
     * 写数据库通知 (复用 ScanOverdueConstructionLogs 的稳定模式)
     */
    private function sendDatabaseNotification(User $user, string $title, string $content, array $payload): void
    {
        try {
            DB::table('notifications')->insert([
                'type'            => 'warranty_expiring',
                'notifiable_type' => User::class,
                'notifiable_id'   => $user->id,
                'data'            => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'title'           => $title,
                'content'         => $content,
                'level'           => 'warning',
                'sender_id'       => null,
                'read_at'         => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // 兜底: 通知表无 title/content 列也兼容
            DB::table('notifications')->insert([
                'type'            => 'warranty_expiring',
                'notifiable_type' => User::class,
                'notifiable_id'   => $user->id,
                'data'            => json_encode(array_merge($payload, [
                    'title'   => $title,
                    'content' => $content,
                ]), JSON_UNESCAPED_UNICODE),
                'read_at'         => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    /**
     * 汇总日志
     */
    private function logSummary(int $expired, int $expiring, int $notified, int $emailed, bool $dryRun, int $errors = 0): void
    {
        Log::info('ScanWarrantyExpiry summary', [
            'expired_marked' => $expired,
            'expiring_count' => $expiring,
            'notified_count' => $notified,
            'emailed_count'  => $emailed,
            'errors'         => $errors,
            'dry_run'        => $dryRun,
        ]);
    }
}
