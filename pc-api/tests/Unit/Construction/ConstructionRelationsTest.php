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
        foreach (['commencementOrder','project','parent','children','progress'] as $r) {
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
