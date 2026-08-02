<?php

/**
 * V1.2.16: 给 stock_records 加 source_warehouse_id / target_warehouse_id 两列
 * 用于支持仓库调拨单（同一 record_no 一对记录，分别是源仓出库 + 目标仓入库）。
 * 让 paginateStockRecords 在 type='transfer' 时能直接返回源/目标仓库名。
 *
 * 加 is_transfer (boolean) 标识是否为调拨记录，让查询能快速区分普通 out/in 和调拨用的 out/in。
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // V1.2.16 fix: 用 try/catch + hasColumn 守卫，避免重复迁移报错
        Schema::table('stock_records', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_records', 'source_warehouse_id')) {
                $table->unsignedBigInteger('source_warehouse_id')->nullable()->after('warehouse_id');
                $table->foreign('source_warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_records', 'target_warehouse_id')) {
                $table->unsignedBigInteger('target_warehouse_id')->nullable()->after('source_warehouse_id');
                $table->foreign('target_warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_records', 'is_transfer')) {
                $table->boolean('is_transfer')->default(false)->after('target_warehouse_id');
            }
        });

        // 历史数据迁移: 找出所有调拨记录（type='out' 且 remark 包含 "调拨至"），
        // 把它和配对的 type='in' 记录关联起来，填入 source_warehouse_id / target_warehouse_id
        // V1.2.16 fix: 分两步更新 'out' 和 'in' 两端, 确保两条记录都标记为 transfer
        DB::statement("
            UPDATE stock_records src
            SET is_transfer = true,
                source_warehouse_id = src.warehouse_id,
                target_warehouse_id = dst.warehouse_id
            FROM stock_records dst
            WHERE src.record_no = dst.record_no
              AND src.type = 'out'
              AND dst.type = 'in'
              AND src.warehouse_id != dst.warehouse_id
              AND src.remark LIKE '调拨至%'
              AND src.is_transfer = false
        ");
        DB::statement("
            UPDATE stock_records dst
            SET is_transfer = true,
                source_warehouse_id = src.warehouse_id,
                target_warehouse_id = dst.warehouse_id
            FROM stock_records src
            WHERE dst.record_no = src.record_no
              AND src.type = 'out'
              AND dst.type = 'in'
              AND src.warehouse_id != dst.warehouse_id
              AND src.remark LIKE '调拨至%'
              AND dst.is_transfer = false
        ");
    }

    public function down(): void
    {
        Schema::table('stock_records', function (Blueprint $table) {
            if (Schema::hasColumn('stock_records', 'is_transfer')) {
                $table->dropColumn('is_transfer');
            }
            if (Schema::hasColumn('stock_records', 'target_warehouse_id')) {
                $table->dropForeign(['target_warehouse_id']);
                $table->dropColumn('target_warehouse_id');
            }
            if (Schema::hasColumn('stock_records', 'source_warehouse_id')) {
                $table->dropForeign(['source_warehouse_id']);
                $table->dropColumn('source_warehouse_id');
            }
        });
    }
};