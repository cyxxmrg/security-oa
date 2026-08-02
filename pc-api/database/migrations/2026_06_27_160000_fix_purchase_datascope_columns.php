<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.0.2 — 修复 DataScope 引用不存在列导致 500
 *
 * BUG: DataScope::tableClauses() 对 purchase_plans 用 created_by 列 (不存在),
 *      对 purchase_contracts 用 signed_by 列 (不存在, 实际叫 signer_id)
 *      非 admin/finance 用户访问 /api/purchase/plans 或 /api/purchase/contracts 时 500
 *
 * 修复:
 *   1. purchase_plans 加 created_by 列 (references users.id)
 *   2. DataScope purchase_contracts 的 signed_by 改 signer_id (列已存在, 改代码即可)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. purchase_plans 加 created_by
        if (!Schema::hasColumn('purchase_plans', 'created_by')) {
            Schema::table('purchase_plans', function (Blueprint $t) {
                $t->foreignId('created_by')->nullable()
                    ->after('approver_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('创建人 (DataScope 用)');
            });
        }

        // 2. 回填: submitter_id → created_by (有 submitter 的说明是本人创建+提交)
        DB::table('purchase_plans')
            ->whereNull('created_by')
            ->whereNotNull('submitter_id')
            ->update(['created_by' => DB::raw('submitter_id')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_plans', 'created_by')) {
            Schema::table('purchase_plans', function (Blueprint $t) {
                $t->dropForeign(['created_by']);
                $t->dropColumn('created_by');
            });
        }
    }
};
