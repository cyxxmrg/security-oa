<?php
/**
 * V0.4.10 A2 - 极简 Laravel app boot, 不依赖 artisan
 *
 * 给 PHPUnit Feature 测试用, 让 Model scope / GlobalScope 真跑
 * 不用 RefreshDatabase (避免引入完整 migration + 重 DB)
 */
require __DIR__ . '/../vendor/autoload.php';

// 强制用 oa_test 数据库连接
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_DATABASE'] = 'security_oa_test';
$_ENV['DB_USERNAME'] = 'oa_test';
$_ENV['DB_PASSWORD'] = 'test_pass_2026';
$_ENV['CACHE_STORE'] = 'array';
$_ENV['SESSION_DRIVER'] = 'array';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
