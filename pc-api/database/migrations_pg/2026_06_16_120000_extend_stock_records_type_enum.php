<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v0.3.7.3 扩展 stock_records.type 枚举
 * 旧值: in, out, transfer, check
 * 新增: inbound, return, outbound, sale, scrap
 * 目的: 与 InventoryController::stockIn/stockOut 的业务规则保持一致
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL ENUM 扩展需要 MODIFY COLUMN
        DB::statement("ALTER TABLE stock_records ALTER COLUMN type TYPE VARCHAR(50)");
        DB::statement("ALTER TABLE stock_records ALTER COLUMN type SET DEFAULT 'in'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_records ALTER COLUMN type TYPE VARCHAR(50)");
        DB::statement("ALTER TABLE stock_records ALTER COLUMN type SET DEFAULT 'in'");
    }
};
