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

    public function test_work_process_clauses_keep_common_processes_visible(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('work_processes', 86);
        $this->assertCount(1, $clauses);
        $this->assertSame('__raw__', $clauses[0][0]);
        $this->assertStringContainsString('work_processes.project_id IS NULL', $clauses[0][1]);
        $this->assertStringContainsString('work_processes.project_id', $clauses[0][1]);
    }

    public function test_project_budget_clauses_cover_creator_and_project_access(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('project_budgets', 86);
        $this->assertCount(2, $clauses);
        $this->assertSame(['created_by', '=', 86], $clauses[0]);
        $this->assertSame('__raw__', $clauses[1][0]);
        $this->assertStringContainsString('project_budgets.project_id', $clauses[1][1]);
    }

    public function test_construction_team_clauses_keep_common_teams_visible(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('construction_teams', 86);
        $this->assertCount(2, $clauses);
        $this->assertSame(['created_by', '=', 86], $clauses[0]);
        $this->assertSame('__raw__', $clauses[1][0]);
        $this->assertStringContainsString('construction_teams.project_id IS NULL', $clauses[1][1]);
        $this->assertStringContainsString('construction_teams.project_id', $clauses[1][1]);
    }

    public function test_repair_orders_clauses_cover_owner_and_project_access(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('repair_orders', 86);
        $this->assertCount(3, $clauses);
        $this->assertSame(['created_by', '=', 86], $clauses[0]);
        $this->assertSame(['received_by', '=', 86], $clauses[1]);
        $this->assertSame('__raw__', $clauses[2][0]);
        $this->assertStringContainsString('repair_orders.project_id', $clauses[2][1]);
    }

    public function test_work_orders_clauses_cover_owner_assignee_and_project_access(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('work_orders', 86);
        $this->assertCount(3, $clauses);
        $this->assertSame(['created_by', '=', 86], $clauses[0]);
        $this->assertSame(['assigned_to', '=', 86], $clauses[1]);
        $this->assertSame('__raw__', $clauses[2][0]);
        $this->assertStringContainsString('work_orders.project_id', $clauses[2][1]);
    }

    public function test_finance_payment_clauses_require_project_or_related_project_access(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('finance_payments', 86);
        $this->assertCount(3, $clauses);
        $this->assertStringContainsString('finance_payments.project_id', $clauses[0][1]);
        $this->assertStringContainsString('FROM receivables r', $clauses[1][1]);
        $this->assertStringContainsString('FROM payables pbl', $clauses[2][1]);
    }

    public function test_inspection_records_follow_task_and_plan_access(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('inspection_records', 86);
        $this->assertCount(2, $clauses);
        $this->assertSame(['user_id', '=', 86], $clauses[0]);
        $this->assertStringContainsString('FROM inspection_tasks it', $clauses[1][1]);
        $this->assertStringContainsString('FROM inspection_plans ip', $clauses[1][1]);
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
