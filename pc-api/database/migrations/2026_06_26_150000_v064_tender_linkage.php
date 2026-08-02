<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * V0.6.4 招标联动 — 字段补齐
 *
 * 1) tender_projects.awarded_po_id (nullable)
 *    中标自动落账后, 反向记录生成的 PO id, 方便前端 1-次查询拿到下游
 *    (PO 表已有 tender_id, 这是冗余但避免 N+1)
 *
 * 2) purchase_orders.tender_id 已经存在 (V0.6.0), 不需要再加
 * 3) payables.po_id + tender_id 已经存在 (V0.6.0), 不需要再加
 *
 * 幂等保护: 用 information_schema 检测列是否存在
 */
return new class extends Migration {
    public function up(): void
    {
        // tender_projects.awarded_po_id
        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'tender_projects'
               AND column_name = 'awarded_po_id'"
        );
        if (!$exists) {
            Schema::table('tender_projects', function (Blueprint $table) {
                $table->unsignedBigInteger('awarded_po_id')->nullable()->after('awarded_at')->comment('V0.6.4: 中标自动生成的 PO id');
                $table->index('awarded_po_id');
            });
        }
    }

    public function down(): void
    {
        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'tender_projects'
               AND column_name = 'awarded_po_id'"
        );
        if ($exists) {
            Schema::table('tender_projects', function (Blueprint $table) {
                $table->dropIndex(['awarded_po_id']);
                $table->dropColumn('awarded_po_id');
            });
        }
    }
};
