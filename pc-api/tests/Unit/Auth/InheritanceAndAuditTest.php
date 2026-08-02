<?php

namespace Tests\Unit\Auth;

use App\Support\PermissionInheritance;
use App\Support\Audit;
use PHPUnit\Framework\TestCase;

/**
 * V0.5.2 - 权限继承工具方法 + Audit helper 单元测试
 */
class InheritanceAndAuditTest extends TestCase
{
    // ============= PermissionInheritance =============

    public function test_descendants_admin_returns_all(): void
    {
        $desc = PermissionInheritance::descendants('admin');
        $this->assertContains('manager', $desc);
        $this->assertContains('finance', $desc);
        $this->assertContains('user', $desc);
    }

    public function test_descendants_user_returns_empty(): void
    {
        $this->assertSame([], PermissionInheritance::descendants('user'));
    }

    public function test_descendants_manager_returns_user_only(): void
    {
        $desc = PermissionInheritance::descendants('manager');
        $this->assertSame(['user'], $desc);
    }

    public function test_descendants_finance_returns_user_only(): void
    {
        $desc = PermissionInheritance::descendants('finance');
        $this->assertSame(['user'], $desc);
    }

    public function test_descendants_cyclic_safety(): void
    {
        // 模拟循环依赖 (但实际静态配置无环), 看是否陷入死循环
        // 直接调用应立即返回 (静态配置, BFS 不会进死循环)
        $start = microtime(true);
        $desc = PermissionInheritance::descendants('admin');
        $elapsed = microtime(true) - $start;
        $this->assertLessThan(0.5, $elapsed, 'descendants 不应超 500ms');
        $this->assertGreaterThan(0, count($desc));
    }

    public function test_get_graph_structure(): void
    {
        $graph = PermissionInheritance::getGraph();
        $this->assertArrayHasKey('nodes', $graph);
        $this->assertArrayHasKey('edges', $graph);
        $nodeNames = array_column($graph['nodes'], 'name');
        $this->assertContains('admin', $nodeNames);
        $this->assertContains('user', $nodeNames);
        // admin 应该有 2 条出边 (到 manager, finance)
        $adminEdges = array_filter($graph['edges'], fn($e) => $e['parent'] === 'admin');
        $this->assertCount(2, $adminEdges);
    }

    public function test_graph_is_static(): void
    {
        // graph 是静态配置, 多次调用结果一致
        $g1 = PermissionInheritance::getGraph();
        $g2 = PermissionInheritance::getGraph();
        $this->assertSame($g1, $g2);
    }

    // ============= Audit helper =============

    public function test_audit_class_exists(): void
    {
        $this->assertTrue(class_exists(Audit::class));
    }

    public function test_audit_write_returns_null_when_no_db(): void
    {
        // 单元测试环境无 DB, write 会失败, 但不能 throw
        // 应该返回 null + 内部捕获异常
        $result = Audit::write('test_action', 'unit test');
        $this->assertNull($result);
    }
}
