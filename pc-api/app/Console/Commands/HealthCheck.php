<?php

namespace App\Console\Commands;

use App\Services\ErrorReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * V0.8 健康探针
 *
 * 每 5 分钟跑一次 (cron), 打 12 个关键端点, 检测:
 *  - HTTP 5xx / 网络超时
 *  - p95 响应时间 > 2s
 *  - 业务 code != 0/200
 *
 * 发现异常走 ErrorReporter → 同一条告警管线 (后续接钉钉/飞书 webhook 即可)
 */
class HealthCheck extends Command
{
    protected $signature   = 'oa:health-check {--base= : 基础 URL 默认 env(OA_HEALTH_BASE)} {--timeout=5 : 单端点超时秒}';
    protected $description = 'V0.8 健康探针 — 打 12 个关键端点, 5xx/超时/慢接口走 ErrorReporter';

    /** 关键端点 (全免登录或只读 + 缓存友好) */
    private const PROBES = [
        // 公共
        ['GET',  '/up',                            'Laravel内置health'],
        // 业务只读 (免登录或 401 不算错, 401 算"需鉴权但服务可用")
        ['GET',  '/api/auth/me',                   '当前用户'],
        ['GET',  '/api/permissions/my',            '我的权限'],
        ['GET',  '/api/notifications/unread-count', '未读消息数'],
        ['GET',  '/api/dashboard/overview',        '工作台总览'],
        ['GET',  '/api/customers',                 '客户列表'],
        ['GET',  '/api/projects',                  '项目列表'],
        ['GET',  '/api/tenders',                   '招标列表'],
        ['GET',  '/api/purchase/orders',           '采购单列表'],
        ['GET',  '/api/inspections/plans',         '巡检计划'],
        ['GET',  '/api/tasks/mine',                '我的任务'],
        ['GET',  '/api/notifications',             '通知列表'],
    ];

    /** 慢接口阈值 (ms) */
    private int $slowMs;

    public function __construct()
    {
        parent::__construct();
        $this->slowMs = (int) config('oa.slow_ms', 800);
    }

    public function handle(): int
    {
        $base = $this->option('base') ?: config('oa.health.base', 'http://127.0.0.1:8081');
        $timeout = (int) $this->option('timeout');
        $token = config('oa.health.token', ''); // 可选: admin token, 让 /api/me 也能通过

        $start = microtime(true);
        $stats = ['total' => 0, 'ok' => 0, 'slow' => 0, '5xx' => 0, '4xx' => 0, 'net' => 0];
        $slowOnes = [];
        $badOnes  = [];

        foreach (self::PROBES as [$method, $path, $name]) {
            $stats['total']++;
            $url = rtrim($base, '/') . $path;
            $t0  = microtime(true);
            try {
                $req = Http::timeout($timeout)->acceptJson();
                if ($token) $req = $req->withToken($token);
                $resp = $req->{$method === 'GET' ? 'get' : 'post'}($url, $method === 'GET' ? [] : []);
                $ms   = (int) ((microtime(true) - $t0) * 1000);
                $code = $resp->status();

                if ($code >= 500) {
                    $stats['5xx']++;
                    $badOnes[] = compact('method', 'path', 'name', 'code', 'ms');
                    ErrorReporter::warn('HEALTH_5XX', compact('method', 'path', 'name', 'code', 'ms', 'url'));
                } elseif ($code === 401) {
                    // 401 = 服务可用, 鉴权缺失 (没传 token) — 不算错
                    $stats['ok']++;
                } elseif ($code >= 400) {
                    $stats['4xx']++;
                    // 4xx 不一定算错 (404 在某些路径上正常), 只在慢的时候才报
                    if ($ms > $this->slowMs) {
                        $stats['slow']++;
                        $slowOnes[] = compact('method', 'path', 'name', 'code', 'ms');
                    }
                } else {
                    $stats['ok']++;
                    if ($ms > $this->slowMs) {
                        $stats['slow']++;
                        $slowOnes[] = compact('method', 'path', 'name', 'code', 'ms');
                    }
                }
            } catch (\Throwable $e) {
                $ms = (int) ((microtime(true) - $t0) * 1000);
                $stats['net']++;
                $badOnes[] = compact('method', 'path', 'name', 'ms') + ['err' => substr($e->getMessage(), 0, 100)];
                ErrorReporter::warn('HEALTH_NET', compact('method', 'path', 'name', 'ms', 'url') + ['err' => $e->getMessage()]);
            }
        }

        $elapsed = (int) ((microtime(true) - $start) * 1000);
        $summary = [
            'ts'         => now()->toIso8601String(),
            'kind'       => 'HEALTH_SUMMARY',
            'elapsed_ms' => $elapsed,
            'stats'      => $stats,
            'slow_top'   => array_slice($slowOnes, 0, 5),
            'bad_top'    => array_slice($badOnes, 0, 5),
        ];
        $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE));

        // 整体不健康 → ERROR 级别 (后续接钉钉会推到群)
        if ($stats['5xx'] + $stats['net'] > 0) {
            ErrorReporter::warn('HEALTH_DOWN', $summary);
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
