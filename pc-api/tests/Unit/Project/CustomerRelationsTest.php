<?php

namespace Tests\Unit\Project;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * V0.4.10 — Customer Model 关系完整性测试
 */
class CustomerRelationsTest extends TestCase
{
    private function getMethods(string $class): array
    {
        if (!class_exists($class)) {
            $this->markTestSkipped("$class 不存在");
        }
        $r = new ReflectionClass($class);
        return array_map(fn($m) => $m->getName(), $r->getMethods());
    }

    public function test_customer_has_essential_relations(): void
    {
        $methods = $this->getMethods(\App\Models\Customer::class);

        // V0.4.10 D2 补的关系
        $this->assertContains('opportunities', $methods, 'Customer::opportunities 缺失');
        $this->assertContains('leads', $methods, 'Customer::leads 缺失');
        $this->assertContains('warranties', $methods, 'Customer::warranties 缺失');

        // 老关系保留
        $this->assertContains('contacts', $methods);
        $this->assertContains('primaryContact', $methods);
        $this->assertContains('devices', $methods);
        $this->assertContains('projects', $methods);
        $this->assertContains('serviceOrders', $methods);
        $this->assertContains('receivables', $methods);
        $this->assertContains('assignedUser', $methods);
    }

    public function test_customer_has_scopes(): void
    {
        $methods = $this->getMethods(\App\Models\Customer::class);
        $this->assertContains('scopeActive', $methods, 'Customer::scopeActive 缺失');
        $this->assertContains('scopeOfCategory', $methods, 'Customer::scopeOfCategory 缺失');
    }
}
