<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * 错误监控 - 轻量级实现
 *
 * 当前阶段: 写入 storage/logs/laravel.log (走 LOG_CHANNEL 默认 stack)
 *   - 每日切文件可通过设置 LOG_CHANNEL=daily
 *   - 或部署 logrotate 切割 laravel.log
 * 未来阶段: 可对接 Sentry / 飞书 / 钉钉机器人
 *
 * 调用方式: ErrorReporter::report($e, $extra = [])
 */
class ErrorReporter
{
    /**
     * 上报一个异常
     *
     * @param  \Throwable  $e
     * @param  array  $extra  附加上下文 (user_id, url, ip, ...)
     */
    public static function report(\Throwable $e, array $extra = []): void
    {
        $trace = explode("\n", $e->getTraceAsString());

        $record = [
            'ts'         => now()->toIso8601String(),
            'level'      => 'error',
            'msg'        => $e->getMessage(),
            'exception'  => get_class($e),
            'file'       => $e->getFile(),
            'line'       => $e->getLine(),
            'trace_top'  => array_slice($trace, 0, 8),
        ];

        // 合并 extra (user_id, url, ip, etc.)
        $record = array_merge($record, $extra);

        // JSON Lines 格式 — 方便后续用 jq / awk 解析
        Log::error('APP_ERROR ' . json_encode($record, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /**
     * 上报一条警告 (不抛异常，仅记录)
     */
    public static function warn(string $msg, array $extra = []): void
    {
        $record = array_merge([
            'ts'    => now()->toIso8601String(),
            'level' => 'warning',
        ], $extra);
        Log::warning($msg . ' ' . json_encode($record, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
}
