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

    public function test_tender_clauses_follow_tender_project_access(): void
    {
        $projects = \App\Scopes\DataScope::tableClauses('tender_projects', 86);
        $this->assertCount(2, $projects);
        $this->assertSame(['created_by', '=', 86], $projects[0]);
        $this->assertStringContainsString('tender_projects.created_by = 86', $projects[1][1]);
        $this->assertStringContainsString('tender_projects.project_id', $projects[1][1]);
        $this->assertStringContainsString('external_quote_requests erq', $projects[1][1]);

        $bids = \App\Scopes\DataScope::tableClauses('tender_bids', 86);
        $this->assertStringContainsString('tender_bids.submitter_user_id = 86', $bids[0][1]);
        $this->assertStringContainsString('FROM tender_projects tp', $bids[0][1]);

        $items = \App\Scopes\DataScope::tableClauses('tender_bid_items', 86);
        $this->assertStringContainsString('FROM tender_bids tb', $items[0][1]);
        $this->assertStringContainsString('tb.tender_project_id', $items[0][1]);
    }

    public function test_tender_sensitive_children_follow_parent_access(): void
    {
        $attachments = \App\Scopes\DataScope::tableClauses('tender_attachments', 86);
        $this->assertSame(['uploaded_by_user_id', '=', 86], $attachments[0]);
        $this->assertStringContainsString('FROM tender_projects tp', $attachments[1][1]);
        $this->assertStringContainsString('FROM tender_bids tb', $attachments[1][1]);

        foreach (['tender_deposit_rules', 'tender_deposits'] as $table) {
            $clauses = \App\Scopes\DataScope::tableClauses($table, 86);
            $this->assertStringContainsString('FROM tender_projects tp', $clauses[0][1]);
            $this->assertStringContainsString('tp.project_id', $clauses[0][1]);
        }
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

    public function test_commencement_order_clauses_cover_creator_and_project_access(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('project_commencement_orders', 86);
        $this->assertCount(2, $clauses);
        $this->assertSame(['created_by', '=', 86], $clauses[0]);
        $this->assertSame('__raw__', $clauses[1][0]);
        $this->assertStringContainsString('project_commencement_orders.project_id', $clauses[1][1]);
    }

    public function test_external_construction_work_clauses_cover_creator_and_project_access(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('external_construction_works', 86);
        $this->assertCount(2, $clauses);
        $this->assertSame(['created_by', '=', 86], $clauses[0]);
        $this->assertSame('__raw__', $clauses[1][0]);
        $this->assertStringContainsString('external_construction_works.project_id', $clauses[1][1]);
    }

    public function test_external_construction_bid_clauses_follow_bidder_or_parent_work_access(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('external_construction_bids', 86);
        $this->assertCount(2, $clauses);
        $this->assertSame(['bidder_user_id', '=', 86], $clauses[0]);
        $this->assertStringContainsString('FROM external_construction_works ecw', $clauses[1][1]);
        $this->assertStringContainsString('ecw.project_id', $clauses[1][1]);
    }

    public function test_construction_child_clauses_follow_parent_access(): void
    {
        $teamMembers = \App\Scopes\DataScope::tableClauses('construction_team_members', 86);
        $this->assertStringContainsString('FROM construction_teams ct', $teamMembers[0][1]);
        $this->assertStringContainsString('ct.project_id', $teamMembers[0][1]);

        $budgetItems = \App\Scopes\DataScope::tableClauses('project_budget_items', 86);
        $this->assertStringContainsString('FROM project_budgets pb', $budgetItems[0][1]);
        $this->assertStringContainsString('pb.project_id', $budgetItems[0][1]);

        $dailyRequired = \App\Scopes\DataScope::tableClauses('rectification_daily_required', 86);
        $this->assertStringContainsString('rectification_daily_required.project_id', $dailyRequired[0][1]);

        $progress = \App\Scopes\DataScope::tableClauses('work_process_progress', 86);
        $this->assertStringContainsString('work_process_progress.project_id', $progress[0][1]);

        $actualCosts = \App\Scopes\DataScope::tableClauses('project_actual_costs', 86);
        $this->assertStringContainsString('project_actual_costs.project_id', $actualCosts[0][1]);

        $stageLogs = \App\Scopes\DataScope::tableClauses('project_stage_logs', 86);
        $this->assertStringContainsString('project_stage_logs.project_id', $stageLogs[0][1]);
    }

    public function test_project_finance_children_follow_project_access(): void
    {
        foreach (['project_contracts', 'project_materials', 'project_settlements'] as $table) {
            $clauses = \App\Scopes\DataScope::tableClauses($table, 86);
            $this->assertCount(1, $clauses);
            $this->assertSame('__raw__', $clauses[0][0]);
            $this->assertStringContainsString("FROM projects p WHERE p.id = {$table}.project_id", $clauses[0][1]);
        }
    }

    public function test_stock_records_keep_shared_and_operator_records_visible(): void
    {
        $clauses = \App\Scopes\DataScope::tableClauses('stock_records', 86);
        $this->assertCount(2, $clauses);
        $this->assertSame(['operator_id', '=', 86], $clauses[0]);
        $this->assertSame('__raw__', $clauses[1][0]);
        $this->assertStringContainsString('stock_records.project_id IS NULL', $clauses[1][1]);
        $this->assertStringContainsString('stock_records.project_id', $clauses[1][1]);
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
