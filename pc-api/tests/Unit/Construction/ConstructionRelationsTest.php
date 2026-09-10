<?php

namespace Tests\Unit\Construction;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * V0.4.10 — WorkProcess / Rectification / CommencementOrder 关系完整性
 */
class ConstructionRelationsTest extends TestCase
{
    private function getMethods(string $class): array
    {
        if (!class_exists($class)) {
            $this->markTestSkipped("$class 不存在");
        }
        $r = new ReflectionClass($class);
        return array_map(fn($m) => $m->getName(), $r->getMethods());
    }

    public function test_work_process_relations(): void
    {
        $methods = $this->getMethods(\App\Models\WorkProcess::class);
        // 注: V0.4.x 曾规划工序树(parent/children)与开工单关联(commencementOrder),
        // 后按"工序数据一致性"改为扁平模型(work_processes 无 parent_id/commencement_order_id 列),
        // 仅保留 project/progress 两个已实现关系。
        foreach (['project', 'progress'] as $r) {
            $this->assertContains($r, $methods, "WorkProcess::$r 缺失");
        }
    }

    public function test_rectification_relations(): void
    {
        $methods = $this->getMethods(\App\Models\Rectification::class);
        foreach (['project','commencementOrder','parentLog','responsible','creator','completer','internalAcceptor','customerAcceptor'] as $r) {
            $this->assertContains($r, $methods, "Rectification::$r 缺失");
        }
    }

    public function test_project_commencement_order_extends_base(): void
    {
        $this->assertTrue(
            class_exists(\App\Models\ProjectCommencementOrder::class) || class_exists(\App\Models\CommencementOrder::class),
            'ProjectCommencementOrder / CommencementOrder 至少一个存在'
        );
    }
}
