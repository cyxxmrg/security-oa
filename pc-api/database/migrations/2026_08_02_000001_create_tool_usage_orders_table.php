<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.3.3: 工具使用单
 *
 * 1) 新建 tool_usage_orders 单据头表（施工工具领用/归还跟踪单）
 * 2) 扩展 stock_records.type CHECK 约束, 新增:
 *      - tool_checkout  工具领用 (出库方向, 扣减库存)
 *      - tool_return    工具退还 (入库方向, 增加库存)
 * 3) 给 stock_records.order_no 建索引 (工具使用明细按单号聚合查询)
 *
 * 幂等: hasTable/hasColumn 守卫 + 索引 IF NOT EXISTS
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tool_usage_orders')) {
            Schema::create('tool_usage_orders', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique()->comment('单号 TU-YYYYMMDD-NNNN');
                $table->unsignedBigInteger('warehouse_id')->comment('领用仓库');
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
                $table->unsignedBigInteger('project_id')->nullable()->comment('关联项目(可选)');
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
                $table->unsignedBigInteger('applicant_id')->nullable()->comment('领用人');
                $table->foreign('applicant_id')->references('id')->on('users')->onDelete('set null');
                $table->string('status', 20)->default('active')->comment('active=使用中 closed=已关闭');
                $table->text('remark')->nullable()->comment('用途/备注');
                $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->timestamps();

                $table->index('status');
                $table->index('warehouse_id');
            });
        }

        // 2) 扩展 stock_records.type 枚举约束 (幂等: 先 drop 再重建)
        if (Schema::hasTable('stock_records')) {
            DB::statement('ALTER TABLE stock_records DROP CONSTRAINT IF EXISTS stock_records_type_check');
            DB::statement("ALTER TABLE stock_records ADD CONSTRAINT stock_records_type_check CHECK (type IN ('in','out','transfer','check','return','sale','scrap','tool_checkout','tool_return'))");
        }

        // 3) order_no 索引 (工具使用明细按单号聚合)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_stock_records_order_no ON stock_records(order_no)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_usage_orders');
        DB::statement('DROP INDEX IF EXISTS idx_stock_records_order_no');
        DB::statement('ALTER TABLE stock_records DROP CONSTRAINT IF EXISTS stock_records_type_check');
        DB::statement("ALTER TABLE stock_records ADD CONSTRAINT stock_records_type_check CHECK (type IN ('in','out','transfer','check','return','sale','scrap'))");
    }
};
