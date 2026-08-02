<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * V0.5.7 块C — 系统监控面板
 *
 * 6 端点 (admin only):
 *   GET /api/admin/monitor/metrics   — 总览 (一次拿所有)
 *   GET /api/admin/monitor/disk      — 磁盘详情
 *   GET /api/admin/monitor/db        — DB 连接/缓存/锁
 *   GET /api/admin/monitor/services  — php-fpm/nginx/opcache
 *   GET /api/admin/monitor/errors    — laravel.log 24h 错误趋势
 *   GET /api/admin/monitor/backups   — 最近备份文件
 */
class SystemMonitorController extends Controller
{
    public function __construct()
    {
        // 全 admin only (在 routes 里加 middleware 限制)
    }

    /** GET /api/admin/monitor/metrics — 总览 */
    public function metrics(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => [
                'timestamp' => now()->toIso8601String(),
                'disk'      => $this->getDiskSummary(),
                'db'        => $this->getDbSummary(),
                'services'  => $this->getServicesSummary(),
                'errors'    => $this->getErrorSummary(),
                'backups'   => $this->getBackupSummary(),
            ],
        ]);
    }

    public function disk(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->getDiskSummary()]);
    }

    public function db(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->getDbSummary()]);
    }

    public function services(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->getServicesSummary()]);
    }

    public function errors(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->getErrorSummary()]);
    }

    public function backups(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->getBackupSummary()]);
    }

    // ========== 私有方法 ==========

    /** 磁盘用量汇总 (5 个挂载点) */
    private function getDiskSummary(): array
    {
        $mounts = ['/', '/var', '/tmp', '/home', '/data'];
        $rows = [];
        foreach ($mounts as $m) {
            $total = @disk_total_space($m);
            $free  = @disk_free_space($m);
            if ($total === false || $free === false) {
                $rows[] = ['mount' => $m, 'available' => false];
                continue;
            }
            $used = $total - $free;
            $rows[] = [
                'mount'    => $m,
                'total'    => $total,
                'used'     => $used,
                'free'     => $free,
                'percent'  => round($used / $total * 100, 1),
                'severity' => $this->severity($used / $total * 100),
            ];
        }
        return [
            'mounts'  => $rows,
            'max_percent' => max(array_column(array_filter($rows, fn($r) => ($r['available'] ?? true) !== false), 'percent') ?: [0]),
        ];
    }

    /** DB 状态: 连接数 / 缓存命中率 / 表大小 Top 5 */
    private function getDbSummary(): array
    {
        // 活跃连接数
        $connRow = DB::selectOne("
            SELECT count(*) AS active_connections,
                   count(*) FILTER (WHERE state = 'active') AS running
            FROM pg_stat_activity
            WHERE datname = current_database()
        ");

        // 缓存命中率 (pg_stat_database)
        $cacheRow = DB::selectOne("
            SELECT blks_hit, blks_read,
                   CASE WHEN (blks_hit + blks_read) = 0 THEN 0
                        ELSE round(blks_hit::numeric / (blks_hit + blks_read) * 100, 2)
                   END AS hit_rate
            FROM pg_stat_database WHERE datname = current_database()
        ");

        // 慢查询 (执行 > 1s 的当前查询)
        $slow = DB::select("
            SELECT pid, now() - query_start AS duration, left(query, 100) AS query
            FROM pg_stat_activity
            WHERE state = 'active' AND now() - query_start > INTERVAL '1 second'
            ORDER BY duration DESC LIMIT 10
        ");

        // 表大小 Top 5
        $bigTables = DB::select("
            SELECT schemaname || '.' || tablename AS name,
                   pg_total_relation_size(schemaname || '.' || tablename) AS size
            FROM pg_tables
            WHERE schemaname = 'public'
            ORDER BY size DESC LIMIT 5
        ");

        // 锁数量
        $locks = DB::selectOne("SELECT count(*) AS cnt FROM pg_locks WHERE NOT granted");

        return [
            'active_connections' => (int) $connRow->active_connections,
            'running_queries'    => (int) $connRow->running,
            'cache_hit_rate'     => (float) ($cacheRow->hit_rate ?? 0),
            'cache_blks_hit'     => (int) ($cacheRow->blks_hit ?? 0),
            'cache_blks_read'    => (int) ($cacheRow->blks_read ?? 0),
            'slow_queries'       => array_map(fn($s) => [
                'pid'      => $s->pid,
                'duration' => trim($s->duration),
                'query'    => $s->query,
            ], $slow),
            'slow_count'         => count($slow),
            'big_tables'         => array_map(fn($t) => [
                'name' => $t->name,
                'size' => (int) $t->size,
            ], $bigTables),
            'waiting_locks'      => (int) ($locks->cnt ?? 0),
        ];
    }

    /** 服务状态: php-fpm 进程 / opcache / redis */
    private function getServicesSummary(): array
    {
        $out = [];

        // PHP 进程数 (FPM workers)
        // V0.9.2 改造: shell_exec → Process::run, 改用 pgrep (更安全, 不依赖 grep -c 模式)
        try {
            $result = \Illuminate\Support\Facades\Process::run(['pgrep', '-c', '-f', 'php-fpm: pool www']);
            $fpmCount = $result->successful() ? (int) trim($result->output()) : 0;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('FPM_COUNT_FAILED', ['msg' => $e->getMessage()]);
            $fpmCount = 0;
        }
        $out['php_fpm_workers'] = $fpmCount;

        // opcache 状态 (在 PHP 进程内读)
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
        $out['opcache_enabled'] = (bool) ($opcache['opcache_enabled'] ?? false);
        $out['opcache_memory_used']     = (int) ($opcache['memory_usage']['used_memory'] ?? 0);
        $out['opcache_memory_free']     = (int) ($opcache['memory_usage']['free_memory'] ?? 0);
        $out['opcache_hit_rate']        = isset($opcache['opcache_statistics'])
            ? round(($opcache['opcache_statistics']['hits'] / max(1, $opcache['opcache_statistics']['hits'] + $opcache['opcache_statistics']['misses'])) * 100, 2)
            : 0;

        // 磁盘空间 (上面 diskSummary 已有, 这里补)
        $out['disk_free_root'] = @disk_free_space('/');

        // cache 驱动
        $out['cache_driver'] = config('cache.default');

        // 当前进程 load average
        $load = sys_getloadavg();
        $out['load_avg_1']  = $load[0] ?? 0;
        $out['load_avg_5']  = $load[1] ?? 0;
        $out['load_avg_15'] = $load[2] ?? 0;

        // PHP 版本 + Laravel 版本
        $out['php_version']    = PHP_VERSION;
        $out['laravel_version'] = app()->version();
        $out['environment']   = app()->environment();
        $out['debug']         = (bool) config('app.debug');

        return $out;
    }

    /** 错误日志 24h 统计 */
    private function getErrorSummary(): array
    {
        $logPath = storage_path('logs/laravel.log');
        if (!file_exists($logPath)) {
            return ['available' => false, 'total_24h' => 0, 'by_hour' => []];
        }

        // 24h 前时间
        $cutoff = now()->subHours(24)->getTimestamp();
        $byHour = [];
        for ($i = 0; $i < 24; $i++) {
            $byHour[$i] = 0;
        }
        $total = 0;
        $lastError = null;

        // 读最后 5000 行 (避免太慢)
        $lines = $this->tailFile($logPath, 5000);
        foreach ($lines as $line) {
            if (strpos($line, 'local.ERROR') === false && strpos($line, 'production.ERROR') === false) {
                continue;
            }
            // 解析时间 [2026-06-24 12:34:56]
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}):\d{2}:\d{2}\]/', $line, $m)) {
                $ts = strtotime($m[1] . ':00:00');
                if ($ts >= $cutoff) {
                    $hour = (int) date('G', $ts);
                    $byHour[$hour]++;
                    $total++;
                }
                if (!$lastError || $ts > $lastError['ts']) {
                    $lastError = [
                        'ts'      => $ts,
                        'snippet' => substr($line, 0, 200),
                    ];
                }
            }
        }

        return [
            'available'    => true,
            'total_24h'    => $total,
            'by_hour'      => $byHour,
            'last_error'   => $lastError ? [
                'time'    => date('Y-m-d H:i:s', $lastError['ts']),
                'snippet' => $lastError['snippet'],
            ] : null,
        ];
    }

    /** 备份文件列表 (backups/ 目录) */
    private function getBackupSummary(): array
    {
        $dir = storage_path('app/backups');
        // 也查 /tmp/full-backup-*
        $rows = [];

        $scanDirs = [
            $dir,
            '/tmp',
        ];
        foreach ($scanDirs as $d) {
            if (!is_dir($d)) continue;
            $files = @scandir($d);
            if (!$files) continue;
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $full = $d . '/' . $f;
                if (!is_file($full)) continue;
                // 只看 .sql.gz / .tar.gz
                if (!preg_match('/\.(sql\.gz|tar\.gz)$/i', $f)) continue;
                $rows[] = [
                    'name' => $f,
                    'path' => $full,
                    'size' => filesize($full),
                    'mtime' => filemtime($full),
                    'mtime_human' => date('Y-m-d H:i', filemtime($full)),
                ];
            }
        }
        // 按 mtime 倒序, 只取最近 5 个
        usort($rows, fn($a, $b) => $b['mtime'] - $a['mtime']);
        $rows = array_slice($rows, 0, 5);

        $latest = $rows[0]['mtime'] ?? null;
        $ageDays = $latest ? round((time() - $latest) / 86400, 1) : null;

        return [
            'count'     => count($rows),
            'latest'    => $rows[0] ?? null,
            'age_days'  => $ageDays,
            'severity'  => $ageDays === null ? 'info' : ($ageDays > 7 ? 'danger' : ($ageDays > 3 ? 'warning' : 'success')),
            'files'     => $rows,
        ];
    }

    /** 严重度 0-100 */
    private function severity(float $percent): string
    {
        if ($percent >= 95) return 'danger';
        if ($percent >= 85) return 'warning';
        if ($percent >= 70) return 'info';
        return 'success';
    }

    /** tail 文件最后 N 行 (避免一次读爆内存) */
    private function tailFile(string $path, int $n): array
    {
        $size = filesize($path);
        if ($size < 1024 * 1024) {
            // < 1MB 直接读
            return file($path, FILE_IGNORE_NEW_LINES) ?: [];
        }
        // > 1MB, fseek 读尾部
        $fp = fopen($path, 'r');
        if (!$fp) return [];
        $data = '';
        $block = 4096;
        $pos = $size;
        while ($pos > 0 && substr_count($data, "\n") <= $n) {
            $pos -= $block;
            fseek($fp, max(0, $pos));
            $data = fread($fp, $size - $pos) . $data;
        }
        fclose($fp);
        $lines = explode("\n", $data);
        return array_slice($lines, -$n);
    }
}
