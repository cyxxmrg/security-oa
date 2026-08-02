<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2.14p: 去掉 stock_records.record_no 的 unique 约束
 * 原因: 一次入库多物料需共享同一 record_no (整单聚合展示)
 *      原 unique 约束限制只能一单一物料, 已无法满足按整单管理的业务需求
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_records')) {
            // 用 raw SQL 删除 unique index (兼容 PG / MySQL)
            DB::statement('ALTER TABLE stock_records DROP CONSTRAINT IF EXISTS stock_records_record_no_unique');
            DB::statement('DROP INDEX IF EXISTS stock_records_record_no_unique');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_records')) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS stock_records_record_no_unique ON stock_records (record_no)');
        }
    }
};