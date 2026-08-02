<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v0.5.8 修复: 追补 rectifications.commencement_order_id 外键
 *
 * 问题: 2026_06_24_000001_create_rectifications_v044_table 在建 rectifications 表时
 *        需要建 commencement_order_id 外键, 但 project_commencement_orders 表
 *        要到 2026_06_25_000003 才建, artisan migrate 按文件名升序会 FAIL
 * 修法: 延后外键 (在 2026_06_24_000002 跑), 此时 project_commencement_orders 已建好
 */
return new class extends Migration
{
    public function up(): void
    {
        // 必须在 project_commencement_orders 已建的前提下
        if (!Schema::hasTable('rectifications') || !Schema::hasTable('project_commencement_orders')) {
            return;
        }
        // 重复外键检查
        $exists = DB::selectOne("
            SELECT 1 FROM information_schema.table_constraints
            WHERE constraint_schema = 'public'
              AND table_name = 'rectifications'
              AND constraint_name = 'rectifications_commencement_order_id_foreign'
        ");
        if ($exists) {
            return;
        }
        Schema::table('rectifications', function ($table) {
            $table->foreign('commencement_order_id')
                ->references('id')->on('project_commencement_orders')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('rectifications', function ($table) {
            $table->dropForeign(['commencement_order_id']);
        });
    }
};
