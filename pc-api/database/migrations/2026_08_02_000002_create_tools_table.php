<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.3.4: 工具使用单简化版
 *
 * 1) 新建 tools 表 — 工具(固定资产)台账:
 *    - 由库存商品"库存转工具"转换而来, inventory_item_id 唯一
 *    - fixed_asset_no 固定资产编号, 自动生成 GD-YYYYMMDD-NNNN
 * 2) 删除 V1.3.3 引入的 tool_usage_orders 单据头表 (简化设计不再需要,
 *    领用/归还直接落 stock_records, 单号=record_no)
 *
 * 幂等: hasTable 守卫
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tools')) {
            Schema::create('tools', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventory_item_id')->unique()->comment('关联库存商品');
                $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
                $table->string('fixed_asset_no', 40)->unique()->comment('固定资产编号 GD-YYYYMMDD-NNNN');
                $table->string('name', 200)->comment('工具名称');
                $table->string('code', 64)->nullable()->comment('原库存编码');
                $table->string('specification', 255)->nullable()->comment('规格');
                $table->string('unit', 20)->nullable()->comment('单位');
                $table->unsignedBigInteger('warehouse_id')->nullable()->comment('所在仓库');
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
                $table->string('status', 20)->default('in_stock')->comment('in_stock=在库 out=已领用');
                $table->text('remark')->nullable()->comment('备注');
                $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->timestamps();

                $table->index('status');
                $table->index('warehouse_id');
            });
        }

        // 简化设计移除单据头表
        if (Schema::hasTable('tool_usage_orders')) {
            Schema::dropIfExists('tool_usage_orders');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tools')) {
            Schema::dropIfExists('tools');
        }
        // 还原 V1.3.3 单据头表 (供回滚)
        if (!Schema::hasTable('tool_usage_orders')) {
            Schema::create('tool_usage_orders', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->unsignedBigInteger('warehouse_id');
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('applicant_id')->nullable();
                $table->string('status', 20)->default('active');
                $table->text('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }
};
