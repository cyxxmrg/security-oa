<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * V0.6.5 招标中心 Sprint 4 — 状态机扩展 + 审核/撤回字段
 *
 * tender_projects.status 扩展：
 *   - draft (草稿)
 *   - pending_review (待审核) ← NEW
 *   - open (审核通过/已发布) ← 重命名 published → open，更符合语义
 *   - withdrawn (已撤回) ← NEW
 *   - rejected (驳回) ← NEW (带 reject_reason)
 *   - cancelled (废标) ← 重命名 cancelled → cancelled
 *   - closed (已定标/已截止)
 *
 * 注意：原 migration 用了 'published/bidding/evaluating/awarded/closed' 五种状态
 * V0.6.5 兼容迁移：published → open，bidding/evaluating → open
 *
 * 新增字段：
 *   - reject_reason (text, nullable)
 *   - reviewer_id (users.id, nullable)
 *   - reviewed_at (timestamp, nullable)
 *   - withdrawn_at (timestamp, nullable)
 *   - withdrawn_by (users.id, nullable)
 *   - cancelled_at (timestamp, nullable)
 *   - cancelled_by (users.id, nullable)
 *   - cancelled_reason (text, nullable)
 *
 * 幂等保护: information_schema 检测
 */
return new class extends Migration {
    public function up(): void
    {
        $cols = DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'tender_projects'"
        );
        $existing = array_column($cols, 'column_name');

        Schema::table('tender_projects', function (Blueprint $table) use ($existing) {
            // 审核字段
            if (!in_array('reject_reason', $existing)) {
                $table->text('reject_reason')->nullable()->after('status')->comment('V0.6.5: 驳回原因');
            }
            if (!in_array('reviewer_id', $existing)) {
                $table->foreignId('reviewer_id')->nullable()->after('reject_reason')->constrained('users')->onDelete('set null')->comment('审核人');
            }
            if (!in_array('reviewed_at', $existing)) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewer_id')->comment('审核时间');
            }
            // 撤回字段
            if (!in_array('withdrawn_at', $existing)) {
                $table->timestamp('withdrawn_at')->nullable()->after('reviewed_at')->comment('撤回时间');
            }
            if (!in_array('withdrawn_by', $existing)) {
                $table->foreignId('withdrawn_by')->nullable()->after('withdrawn_at')->constrained('users')->onDelete('set null')->comment('撤回人');
            }
            if (!in_array('withdraw_reason', $existing)) {
                $table->text('withdraw_reason')->nullable()->after('withdrawn_by')->comment('撤回原因');
            }
            // 废标字段
            if (!in_array('cancelled_at', $existing)) {
                $table->timestamp('cancelled_at')->nullable()->after('withdraw_reason')->comment('废标时间');
            }
            if (!in_array('cancelled_by', $existing)) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->onDelete('set null')->comment('废标操作人');
            }
            if (!in_array('cancelled_reason', $existing)) {
                $table->text('cancelled_reason')->nullable()->after('cancelled_by')->comment('废标原因');
            }
        });

        // 索引优化: 审核队列 (按 status + reviewed_at)
        if (!DB::selectOne("SELECT 1 FROM pg_indexes WHERE indexname = 'tender_projects_status_reviewed_at_index'")) {
            DB::statement('CREATE INDEX tender_projects_status_reviewed_at_index ON tender_projects (status, reviewed_at DESC)');
        }
        if (!DB::selectOne("SELECT 1 FROM pg_indexes WHERE indexname = 'tender_projects_status_deadline_index'")) {
            DB::statement('CREATE INDEX tender_projects_status_deadline_index ON tender_projects (status, deadline)');
        }

        // 数据迁移: 旧状态 → 新状态
        DB::statement("UPDATE tender_projects SET status = 'open' WHERE status IN ('published', 'bidding', 'evaluating')");
        // 已 award 的就是 closed
        DB::statement("UPDATE tender_projects SET status = 'closed' WHERE status = 'awarded'");
    }

    public function down(): void
    {
        // 不回滚数据迁移（已经发生的）
        DB::statement("UPDATE tender_projects SET status = 'published' WHERE status = 'open' AND awarded_bid_id IS NULL");

        Schema::table('tender_projects', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['reject_reason', 'reviewer_id', 'reviewed_at', 'withdrawn_at', 'withdrawn_by', 'withdraw_reason', 'cancelled_at', 'cancelled_by', 'cancelled_reason'] as $col) {
                if (Schema::hasColumn('tender_projects', $col)) {
                    $dropColumns[] = $col;
                }
            }
            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });

        DB::statement('DROP INDEX IF EXISTS tender_projects_status_reviewed_at_index');
        DB::statement('DROP INDEX IF EXISTS tender_projects_status_deadline_index');
    }
};
