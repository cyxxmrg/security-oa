<?php

use Laravel\Horizon\Horizon;

/**
 * V1.2.6 P2-1: Horizon 队列面板 + supervisor 配置
 *
 * 启动:  sudo supervisorctl start oa-horizon
 * 重启:  php artisan horizon:terminate && sudo supervisorctl restart oa-horizon
 * 面板:  /horizon (auth:sanctum, system/admin 角色可见)
 *
 * 队列优先级: notifications > schedules > exports > default
 */
return [

    'name'   => env('HORIZON_NAME', 'oa-horizon'),
    'domain' => env('HORIZON_DOMAIN'),
    'path'   => env('HORIZON_PATH', 'horizon'),
    'use'    => 'default',
    'prefix' => env('HORIZON_PREFIX', 'oa_horizon:'),

    // 纯 API 项目, 用 Sanctum 解析 token
    'middleware' => ['auth:sanctum'],
    'guard'      => 'web',

    // 等待时长阈值 (秒), 超过触发 LongWaitDetected
    'waits' => [
        'redis:default'       => 60,
        'redis:notifications' => 30,
        'redis:schedules'     => 120,
        'redis:exports'       => 300,
    ],

    // 任务保留时间 (分钟)
    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    'silenced' => [],
    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,
    'memory_limit'     => 64,

    // 默认 supervisor (所有环境基础)
    'defaults' => [
        'supervisor-1' => [
            'connection'         => 'redis',
            'queue'              => ['default', 'notifications', 'schedules', 'exports'],
            'balance'            => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'       => 1,
            'maxProcesses'       => 1,
            'maxTime'            => 0,
            'maxJobs'            => 0,
            'memory'             => 128,
            'tries'              => 3,
            'timeout'            => 60,
            'nice'               => 0,
        ],
    ],

    // 不同环境覆盖
    // 注意: Horizon 5.x 的 ProvisioningPlan::applyDefaultOptions 用 array_replace_recursive
    // environments.production 完全替换 defaults (按 supervisor 名为 key 替换)
    // 所以 production 段必须包含完整字段, 或者写 [] 让 defaults 100% 生效
    'environments' => [
        'production' => [
            'supervisor-1' => [
                // 生产环境: 队列/连接/balance 全部从 defaults 继承
                // 只调整容量参数
                'minProcesses'    => 2,
                'maxProcesses'    => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'memory'          => 256,
                'tries'           => 5,        // 生产重试多给一次
                'timeout'         => 120,      // 报表类长任务 2 分钟超时
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'memory'       => 128,
                'tries'        => 1,
                'timeout'      => 60,
            ],
        ],
    ],

    // 文件监听 (horizon:listen 时生效)
    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
