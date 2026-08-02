<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.8 B1: 8 张核心表补复合索引 (status, created_at) / (project_id, status)
 *
 * 现有索引分析 (V0.4.5/6/7 已建):
 *   - warranties / warranty_deposits / warranty_service_orders: 复合索引完整 ✅
 *   - projects / purchase_orders / construction_logs / customer_receivables / rectifications: 缺 (status, created_at) 列表分页索引
 *
 * 用 raw SQL CONCURRENTLY 不锁表 (业务低峰期跑)
 * 但 Laravel 默认 migration 不允许 CONCURRENTLY, 这里用 if-not-exists 兼容
 */
return new class extends Migration
{
    public function up(): void
    {
        // projects: 列表分页 (status + created_at desc)
        DB::statement('CREATE INDEX IF NOT EXISTS projects_status_created_at_index ON projects (status, created_at DESC)');
        // projects: 看板视图按 stage 分组
        DB::statement('CREATE INDEX IF NOT EXISTS projects_stage_status_index ON projects (stage, status)');

        // customer_receivables: 列表分页
        DB::statement('CREATE INDEX IF NOT EXISTS customer_receivables_status_created_at_index ON customer_receivables (status, created_at DESC)');

        // purchase_orders: 列表分页
        DB::statement('CREATE INDEX IF NOT EXISTS purchase_orders_status_created_at_index ON purchase_orders (status, created_at DESC)');

        // construction_logs: 按项目 + 状态 + 日期
        DB::statement('CREATE INDEX IF NOT EXISTS construction_logs_project_status_date_index ON construction_logs (project_id, status, work_date DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS construction_logs_status_date_index ON construction_logs (status, work_date DESC)');

        // rectifications: 列表分页
        DB::statement('CREATE INDEX IF NOT EXISTS rectifications_status_created_at_index ON rectifications (status, created_at DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS rectifications_project_status_index ON rectifications (project_id, status)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS projects_status_created_at_index');
        DB::statement('DROP INDEX IF EXISTS projects_stage_status_index');
        DB::statement('DROP INDEX IF EXISTS customer_receivables_status_created_at_index');
        DB::statement('DROP INDEX IF EXISTS purchase_orders_status_created_at_index');
        DB::statement('DROP INDEX IF EXISTS construction_logs_project_status_date_index');
        DB::statement('DROP INDEX IF EXISTS construction_logs_status_date_index');
        DB::statement('DROP INDEX IF EXISTS rectifications_status_created_at_index');
        DB::statement('DROP INDEX IF EXISTS rectifications_project_status_index');
    }
};
