<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every queue driver. Here you may define a default
    | connection name for the queue API.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. Drivers are "sync", "database",
    | "beanstalkd", "sqs", "redis".
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        // 业务通用队列 — 默认
        'database' => [
            'driver'      => 'database',
            'connection'  => env('DB_QUEUE_CONNECTION'),
            'table'       => env('DB_QUEUE_TABLE', 'jobs'),
            'queue'       => env('DB_QUEUE', 'default'),
            'retry_after' => env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        // 通知专用队列 — 高优先级, 失败重试快
        'notifications' => [
            'driver'      => 'database',
            'table'       => 'jobs',
            'queue'       => 'notifications',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        // 报表导出队列 — 低优先级, 长超时
        'exports' => [
            'driver'      => 'database',
            'table'       => 'jobs',
            'queue'       => 'exports',
            'retry_after' => 700, // 略大于 Job::timeout 600
            'after_commit' => false,
        ],

        // 排班专用队列 — 中优先级
        'schedules' => [
            'driver'      => 'database',
            'table'       => 'jobs',
            'queue'       => 'schedules',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        // Redis 队列 (生产环境推荐) — 可选
        'redis' => [
            'driver'      => 'redis',
            'connection'  => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue'       => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for'   => null,
            'after_commit' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the failed
    | jobs. The driver "database-uuids" is used by the queue system to
    | generate a UUID for each failed job ID.
    |
    */

    'failed' => [
        'driver'   => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table'    => 'failed_jobs',
    ],

];
