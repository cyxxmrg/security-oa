<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2.7 P2-4: stock_records 表加 order_no 列
 *
 * 背景: InventoryService::stockOut 一直尝试写 'order_no' 字段 (model fillable 也写),
 *       但原始 migration 没建这列, 实际写入是 silently dropped.
 *       业务上 order_no 是关联销售/采购单号的关键字段, 必须有.
 *
 * 修复: 加 order_no varchar(100) nullable
 * 幂等: hasColumn 检查
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_records')) {
            return;
        }
        if (!Schema::hasColumn('stock_records', 'order_no')) {
            Schema::table('stock_records', function (Blueprint $table) {
                $table->string('order_no', 100)->nullable()->after('remaining_stock')
                    ->comment('关联销售/采购单号');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_records', 'order_no')) {
            Schema::table('stock_records', function (Blueprint $table) {
                $table->dropColumn('order_no');
            });
        }
    }
};
