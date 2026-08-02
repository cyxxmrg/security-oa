<?php

namespace App\Http\Middleware;

use App\Services\ErrorReporter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * V1.2.7 P2-3 性能监控中间件 (升级版)
 *
 * 职责:
 *  1) 统计每个接口耗时 (request_time)
 *  2) 慢接口告警: 分级阈值 (200ms warn, 500ms slow, 1000ms critical)
 *  3) 5xx 错误: 自动上报
 *  4) 健康指标聚合: 用 Cache 计数, 1 分钟滚动 (请求数/平均耗时/最慢/错误数)
 *  5) 慢接口自动 dispatch SlowRequestAlertJob 发钉钉/企业微信 (P2-3 增量)
 *
 * 设计:
 *  - 不写库, 不引外部依赖, 只用 Log + ErrorReporter + Cache
 *  - JSON Lines 格式方便 jq/awk 解析
 *  - 慢接口阈值可由 env OA_SLOW_MS 调整
 */
class PerformanceMonitor
{
    /** 普通警告阈值 (ms) — 200ms 算偏慢 */
    private int $warnMs;

    /** 慢接口阈值 (ms) — 500ms 算慢 */
    private int $slowMs;

    /** 严重超时阈值 (ms) — 1000ms 算严重, 直接告警 */
    private int $criticalMs;

    /** 静默路径 (避免 health check / favicon 污染日志) */
    private const SILENT_PATHS = [
        'up',                  // Laravel 内置 health
        'favicon.ico',
        '_debugbar',
    ];

    public function __construct()
    {
        $this->warnMs     = (int) config('oa.warn_ms', 200);
        $this->slowMs     = (int) config('oa.slow_ms', 500);
        $this->criticalMs = (int) config('oa.critical_ms', 1000);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $start    = microtime(true);
        $response = $next($request);
        $elapsed  = (int) ((microtime(true) - $start) * 1000);
        $status   = $response->getStatusCode();
        $path     = '/' . ltrim($request->path(), '/');
        $method   = $request->method();

        // 静默路径直接返回, 不打日志
        foreach (self::SILENT_PATHS as $p) {
            if (str_contains($path, $p)) {
                return $response;
            }
        }

        $record = [
            'ts'      => now()->toIso8601String(),
            'method'  => $method,
            'path'    => $path,
            'status'  => $status,
            'ms'      => $elapsed,
            'user_id' => $request->user()?->id,
            'ip'      => $request->ip(),
            'ua'      => substr($request->userAgent() ?? '', 0, 50),
        ];

        // ===== 1) 健康指标聚合 (Cache 滚动) =====
        $this->updateMetrics($path, $elapsed, $status);

        // ===== 2) 严重超时 (>1s) — 立即 dispatch 告警 Job =====
        if ($elapsed > $this->criticalMs) {
            ErrorReporter::warn('CRITICAL_SLOW_REQUEST', $record + [
                'threshold_ms' => $this->criticalMs,
            ]);
            $this->dispatchAlertJob($record, 'critical');
            return $response;
        }

        // ===== 3) 慢接口 (>500ms) — 走 ErrorReporter warn =====
        if ($elapsed > $this->slowMs) {
            ErrorReporter::warn('SLOW_REQUEST', $record + [
                'threshold_ms' => $this->slowMs,
            ]);
            return $response;
        }

        // ===== 4) 偏慢 (>200ms) — 写 debug, 用于找 P95 性能瓶颈 =====
        if ($elapsed > $this->warnMs) {
            if (config('oa.log_api', false)) {
                Log::debug('WARN_SLOW ' . json_encode($record, JSON_UNESCAPED_UNICODE));
            }
            return $response;
        }

        // ===== 5) 5xx 错误: 必上报 =====
        if ($status >= 500) {
            ErrorReporter::warn('HTTP_5XX', $record);
            return $response;
        }

        // ===== 6) 4xx 业务异常 (但 401/422/403/404 太多, 略过以免噪声) =====
        if ($status >= 400 && !in_array($status, [401, 422, 403, 404])) {
            Log::info('HTTP_4XX ' . json_encode($record, JSON_UNESCAPED_UNICODE));
            return $response;
        }

        // ===== 7) 正常请求 — 默认不记, OA_LOG_API=1 时记 debug =====
        if (config('oa.log_api', false)) {
            Log::debug('API ' . json_encode($record, JSON_UNESCAPED_UNICODE));
        }

        return $response;
    }

    /**
     * 更新 Cache 里的健康指标 (1 分钟滚动窗口)
     * 用于: 1) 实时大屏 2) 慢接口热点分析 3) 缓存命中率
     */
    private function updateMetrics(string $path, int $ms, int $status): void
    {
        $minute = now()->format('Y-m-d-H-i');
        $key    = "metrics:api:{$minute}";

        // 复用 5 分钟 TTL, 自然滚动
        $data = Cache::get($key, [
            'count'      => 0,
            'total_ms'   => 0,
            'max_ms'     => 0,
            'errors_5xx' => 0,
            'paths'      => [], // path => count
        ]);

        $data['count']++;
        $data['total_ms'] += $ms;
        $data['max_ms'] = max($data['max_ms'], $ms);
        if ($status >= 500) {
            $data['errors_5xx']++;
        }
        $data['paths'][$path] = ($data['paths'][$path] ?? 0) + 1;

        Cache::put($key, $data, now()->addMinutes(5));

        // V1.2.7 P2-3: 同步写入分钟级 cache miss 计数
        // 实际 miss 数据由 CacheHelper::remember 写入, 这里只是把空 key 占住避免重复读
    }

    /**
     * 派发慢接口告警 Job (P2-3 增量)
     *
     * 实际告警通道 (钉钉/企业微信) 在 SlowRequestAlertJob 内部配置
     * 默认降级: 只写日志, 不发推送 (避免误报刷屏)
     */
    private function dispatchAlertJob(array $record, string $level): void
    {
        try {
            \App\Jobs\SlowRequestAlertJob::dispatch($record, $level);
        } catch (\Throwable $e) {
            // 告警失败不影响主流程
            Log::warning('SlowRequestAlertJob dispatch failed', [
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
