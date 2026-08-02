<?php

namespace Tests\Unit\Scopes;

use PHPUnit\Framework\TestCase;

/**
 * V0.4.7 — DataScope::tableClauses 拼 SQL 单测
 * 不连数据库, 纯逻辑测
 */
class DataScopeTest extends TestCase
{
    public function test_projects_clauses(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('projects', 86);
        $this->assertCount(1, $clauses);
        $this->assertSame('__raw__', $clauses[0][0]);
        $this->assertStringContainsString('projects.manager_id = 86', $clauses[0][1]);
        $this->assertStringContainsString('EXISTS (SELECT 1 FROM project_members', $clauses[0][1]);
    }

    public function test_construction_logs_clauses(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('construction_logs', 82);
        $this->assertCount(2, $clauses);
        // [0] = user_id = 82
        $this->assertSame('user_id', $clauses[0][0]);
        $this->assertSame(82, $clauses[0][2]);
        // [1] = raw subquery
        $this->assertStringContainsString('construction_logs.project_id', $clauses[1][1]);
    }

    public function test_warranty_service_orders_uses_warranty_subquery(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('warranty_service_orders', 86);
        $this->assertCount(3, $clauses);
        // 第 3 个是 raw, 引用 warranties 表
        $this->assertSame('__raw__', $clauses[2][0]);
        $this->assertStringContainsString('FROM warranties w', $clauses[2][1]);
    }

    public function test_unknown_table_returns_empty(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('nonexistent_table', 1);
        $this->assertSame([], $clauses);
    }
}
