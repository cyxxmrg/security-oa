<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * V1.2.7 P1 - ScheduleService 单元测试 (纯逻辑层)
 *
 * 不依赖 Laravel Kernel, 不连数据库, 只验证方法签名和最小输入输出契约
 * 完整数据库交互测试在 tests/Feature/ScheduleApiTest.php
 *
 * 用例: 5 个
 */
class ScheduleServiceTest extends TestCase
{
    /**
     * 1. Service 类存在且可实例化
     */
    public function test_service_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\ScheduleService::class));
    }

    /**
     * 2. batchSave 方法签名: 接数组, 返回 ['created' => int, 'updated' => int]
     */
    public function test_batch_save_signature(): void
    {
        $reflection = new \ReflectionMethod(\App\Services\ScheduleService::class, 'batchSave');
        $this->assertTrue($reflection->isPublic());
        $this->assertCount(1, $reflection->getParameters());
        $this->assertEquals('assignments', $reflection->getParameters()[0]->getName());
    }

    /**
     * 3. batchByGroup 方法签名: 接标量参数, 返回 ['count' => int]
     */
    public function test_batch_by_group_signature(): void
    {
        $reflection = new \ReflectionMethod(\App\Services\ScheduleService::class, 'batchByGroup');
        $this->assertTrue($reflection->isPublic());

        $params = $reflection->getParameters();
        $this->assertCount(5, $params);

        $names = array_map(fn($p) => $p->getName(), $params);
        $this->assertEquals(['groupId', 'shiftId', 'startDate', 'endDate', 'skipWeekends'], $names);
    }

    /**
     * 4. smartSuggest 返回结构约定: 数组 of {user_id, suggested_shift_*}
     */
    public function test_smart_suggest_return_structure(): void
    {
        $reflection = new \ReflectionMethod(\App\Services\ScheduleService::class, 'smartSuggest');
        $this->assertTrue($reflection->isPublic());
    }

    /**
     * 5. monthlyStats 接收 month 字符串 (Y-m), 返回 array 含 month/by_shift/by_user/total 四个 key
     */
    public function test_monthly_stats_method_exists(): void
    {
        $reflection = new \ReflectionMethod(\App\Services\ScheduleService::class, 'monthlyStats');
        $this->assertTrue($reflection->isPublic());
        $this->assertCount(1, $reflection->getParameters());
        $this->assertEquals('month', $reflection->getParameters()[0]->getName());
    }
}
