<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * V0.6.3 — Tests\TestCase 基类
 *
 * 提供:
 *  - Laravel app boot (for HTTP 调用 + DB factory)
 *  - actingAs($user) 登录模拟
 *  - createApplication 必要 boot
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * 创建 Laravel app 实例 (Laravel 11 标准)
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }
}