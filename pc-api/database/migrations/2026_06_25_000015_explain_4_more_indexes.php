<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.9 B1 - EXPLAIN ANALYZE 找出的 4 个剩余 Seq Scan 优化
 *
 * 8 大热 query EXPLAIN 结果:
 *   ✓ projects.stage 合同 ORDER BY created_at DESC  → Seq Scan (118 行小表 seq 也快, 不加)
 *   ✗ construction_logs.work_date >= '2026-06-01'  → Seq Scan (445 行)
 *   ✗ service_orders.status IN (...)  → Seq Scan (?)
 *   ✗ customer_receivables.status != 'paid'  → Seq Scan
 *   ✓ warranties WHERE active+end_date  → Index Scan (已有)
 *   ✗ rectifications.status='pending' ORDER BY created_at DESC  → Seq Scan
 *   ✓ warranties.customer_id=1  → Index Scan (已有)
 *   ✓ construction_logs.project_id=1  → Index Scan (已有)
 *
 * 加 4 个索引:
 *   1) construction_logs_status_date_idx (status, work_date DESC)
 *   2) service_orders_status_created_idx (status, created_at DESC)
 *   3) customer_receivables_status_due_idx (status, due_date)
 *   4) rectifications_status_created_idx (status, created_at DESC)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) construction_logs: 状态+日期 复合
        if (!$this->indexExists('construction_logs', 'construction_logs_status_date_idx')) {
            DB::statement('CREATE INDEX construction_logs_status_date_idx ON construction_logs (status, work_date DESC)');
        }
        // 2) service_orders: 状态+创建时间
        if (!$this->indexExists('service_orders', 'service_orders_status_created_idx')) {
            DB::statement('CREATE INDEX service_orders_status_created_idx ON service_orders (status, created_at DESC)');
        }
        // 3) customer_receivables: 状态+到期日
        if (!$this->indexExists('customer_receivables', 'customer_receivables_status_due_idx')) {
            DB::statement('CREATE INDEX customer_receivables_status_due_idx ON customer_receivables (status, due_date)');
        }
        // 4) rectifications: 状态+创建时间
        if (!$this->indexExists('rectifications', 'rectifications_status_created_idx')) {
            DB::statement('CREATE INDEX rectifications_status_created_idx ON rectifications (status, created_at DESC)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS construction_logs_status_date_idx');
        DB::statement('DROP INDEX IF EXISTS service_orders_status_created_idx');
        DB::statement('DROP INDEX IF EXISTS customer_receivables_status_due_idx');
        DB::statement('DROP INDEX IF EXISTS rectifications_status_created_idx');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $row = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE schemaname='public' AND tablename=? AND indexname=?",
            [$table, $indexName]
        );
        return $row !== null;
    }
};
