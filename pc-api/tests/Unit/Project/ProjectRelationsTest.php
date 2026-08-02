<?php

namespace Tests\Unit\Project;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * V0.4.10 — Project Model 关系完整性测试
 *
 * 不依赖 Laravel boot, 用反射验证类已声明的关键关系方法
 * 防止后续重构误删关系
 */
class ProjectRelationsTest extends TestCase
{
    private function getMethods(string $class): array
    {
        if (!class_exists($class)) {
            $this->markTestSkipped("$class 不存在");
        }
        $r = new ReflectionClass($class);
        return array_map(fn($m) => $m->getName(), $r->getMethods());
    }

    public function test_project_has_essential_relations(): void
    {
        $methods = $this->getMethods(\App\Models\Project::class);

        // V0.4.10 D2 补的关系
        $this->assertContains('processInstances', $methods, 'Project::processInstances 缺失');
        $this->assertContains('rectifications', $methods, 'Project::rectifications 缺失');
        $this->assertContains('warranties', $methods, 'Project::warranties 缺失');
        $this->assertContains('commencementOrder', $methods, 'Project::commencementOrder 缺失');
        $this->assertContains('budgets', $methods, 'Project::budgets 缺失');
        $this->assertContains('budget', $methods, 'Project::budget 缺失');
        $this->assertContains('actualCosts', $methods, 'Project::actualCosts 缺失');
        $this->assertContains('receivables', $methods, 'Project::receivables 缺失');
        $this->assertContains('settlements', $methods, 'Project::settlements 缺失');
        $this->assertContains('followUps', $methods, 'Project::followUps 缺失');

        // 老关系保留
        $this->assertContains('customer', $methods);
        $this->assertContains('manager', $methods);
        $this->assertContains('members', $methods);
        $this->assertContains('constructionLogs', $methods);
        $this->assertContains('serviceOrders', $methods);
        $this->assertContains('devices', $methods);
    }

    public function test_project_total_budget_accessor(): void
    {
        $methods = $this->getMethods(\App\Models\Project::class);
        $this->assertContains('getTotalBudgetAttribute', $methods);
        $this->assertContains('getTotalActualCostAttribute', $methods, 'Project::getTotalActualCostAttribute 缺失');
    }

    public function test_total_budget_sums_all_5_components(): void
    {
        // 用纯类构造, 不 boot Eloquent
        $p = new \App\Models\Project();
        $p->setRawAttributes([
            'budget_device' => 100, 'budget_material' => 200,
            'budget_labor' => 300, 'budget_outsource' => 400, 'budget_other' => 500,
        ], true);
        // budget_* 是 decimal cast → string, accessor 强转 float
        $this->assertSame(1500.0, $p->total_budget);
    }

    public function test_total_budget_handles_null(): void
    {
        $p = new \App\Models\Project();
        $p->setRawAttributes([], true);
        // 缺字段 → null cast → null + null = 0.0
        $this->assertSame(0.0, $p->total_budget);
    }
}
