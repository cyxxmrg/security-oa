<?php

/**
 * V1.2.14p: 扩展 stock_records.type enum 加 'return' (退料入库)
 * enum 现状: ['in', 'out', 'transfer', 'check']
 * 新 enum: ['in', 'out', 'transfer', 'check', 'return']
 *
 * 注意: PG 的 ALTER TYPE enum ... ADD VALUE 不能放在事务内
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_records DROP CONSTRAINT IF EXISTS stock_records_type_check");
        DB::statement("ALTER TABLE stock_records ADD CONSTRAINT stock_records_type_check CHECK (type IN ('in','out','transfer','check','return','sale','scrap'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_records DROP CONSTRAINT IF EXISTS stock_records_type_check");
        DB::statement("ALTER TABLE stock_records ADD CONSTRAINT stock_records_type_check CHECK (type IN ('in','out','transfer','check'))");
    }
};