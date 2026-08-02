<?php

/**
 * V0.9.4 — OA 自定义配置 (替代 env() 直接调用)
 *
 * 通过 config() 读取, 避免缓存失效后 env() 返回 null 的坑
 */

return [

    // 应用版本号 (全系统单一真相源 — 改这里, 前端/后端/部署全部自动同步)
    'app_version' => 'v1.4.1',

    // 慢接口阈值 (毫秒) — P2-3 三级阈值
    'warn_ms'     => (int) env('OA_WARN_MS', 200),   // 偏慢, debug 记
    'slow_ms'     => (int) env('OA_SLOW_MS', 500),   // 慢, ErrorReporter warn
    'critical_ms' => (int) env('OA_CRITICAL_MS', 1000), // 严重, dispatch 告警 Job

    // 是否记录每个 API 请求 (开发用, 生产关闭)
    'log_api' => env('OA_LOG_API', false),

    // 健康探针配置
    'health' => [
        // 探针基础 URL
        'base'  => env('OA_HEALTH_BASE', 'http://127.0.0.1:8081'),
        // 可选: admin token, 让需要鉴权的端点也能通过
        'token' => env('OA_HEALTH_TOKEN', ''),
    ],

    // P2-3 慢接口告警 webhook (钉钉/企业微信, 不配就只写日志)
    'alert_webhook' => env('SLOW_ALERT_WEBHOOK_URL', ''),

    // V1.2.10 分页上限 (替代 EnforcePaginationLimit 里 env() 直调)
    'max_per_page' => (int) env('OA_MAX_PER_PAGE', 200),

    // V1.2.10 是否允许破坏性重置 (wipe-data 等, 仅内网/开发环境)
    'allow_destructive_reset' => (bool) env('OA_ALLOW_DESTRUCTIVE_RESET', false),

];
