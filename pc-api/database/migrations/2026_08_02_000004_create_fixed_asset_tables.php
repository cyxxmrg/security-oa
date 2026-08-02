<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.4.0: 固定资产管理（全生命周期）
 *
 * 1) asset_categories        资产分类树
 * 2) fixed_assets            资产台账 (含财务折旧字段; tool_id 打通工具使用单)
 * 3) asset_depreciations     折旧明细 (每月一条, asset_id+period 唯一)
 * 4) asset_maintenances      维修保养记录
 * 5) asset_inventories       盘点单 + asset_inventory_items 盘点明细
 * 6) asset_disposals         报废处置
 * 7) asset_transfers         调拨记录
 *
 * 幂等: hasTable 守卫
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_categories')) {
            Schema::create('asset_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable()->comment('父分类');
                $table->string('name', 100)->comment('分类名称');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index('parent_id');
            });
        }

        if (!Schema::hasTable('fixed_assets')) {
            Schema::create('fixed_assets', function (Blueprint $table) {
                $table->id();
                $table->string('asset_no', 40)->unique()->comment('固定资产编号 GD-YYYYMMDD-NNNN');
                $table->unsignedBigInteger('category_id')->nullable()->comment('资产分类');
                $table->foreign('category_id')->references('id')->on('asset_categories')->onDelete('set null');
                $table->string('name', 200)->comment('资产名称');
                $table->string('specification', 255)->nullable()->comment('规格型号');
                $table->string('unit', 20)->nullable()->comment('单位');
                $table->unsignedInteger('quantity')->default(1)->comment('数量');
                $table->string('source', 20)->default('manual')->comment('来源: manual=手动录入 tool=工具使用单打通');
                $table->unsignedBigInteger('tool_id')->nullable()->unique()->comment('关联工具台账(打通)');
                $table->unsignedBigInteger('inventory_item_id')->nullable()->comment('来源库存商品');
                $table->decimal('original_value', 14, 2)->default(0)->comment('原值');
                $table->decimal('net_residual_value', 14, 2)->default(0)->comment('净残值');
                $table->unsignedInteger('useful_life_months')->default(60)->comment('使用年限(月)');
                $table->date('acquisition_date')->nullable()->comment('购置日期');
                $table->string('depreciation_method', 30)->default('straight_line')->comment('折旧方法: 直线法');
                $table->decimal('accumulated_depreciation', 14, 2)->default(0)->comment('累计折旧');
                $table->decimal('net_book_value', 14, 2)->default(0)->comment('净值');
                $table->string('status', 20)->default('in_use')->comment('in_use=使用中 idle=闲置 repair=维修中 scrapped=已报废');
                $table->string('location', 200)->nullable()->comment('存放地/使用部门');
                $table->unsignedBigInteger('keeper_id')->nullable()->comment('保管人/责任人');
                $table->foreign('keeper_id')->references('id')->on('users')->onDelete('set null');
                $table->text('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->timestamps();

                $table->index('category_id');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('asset_depreciations')) {
            Schema::create('asset_depreciations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
                $table->string('period', 7)->comment('折旧期间 YYYY-MM');
                $table->decimal('month_depreciation', 14, 2)->comment('当月折旧');
                $table->decimal('accumulated_after', 14, 2)->comment('计提后累计折旧');
                $table->decimal('net_value_after', 14, 2)->comment('计提后净值');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['asset_id', 'period']);
            });
        }

        if (!Schema::hasTable('asset_maintenances')) {
            Schema::create('asset_maintenances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
                $table->date('date')->nullable()->comment('日期');
                $table->string('type', 20)->default('repair')->comment('repair=维修 maintain=保养 inspect=检测');
                $table->decimal('cost', 14, 2)->default(0)->comment('费用');
                $table->text('description')->nullable()->comment('内容');
                $table->text('result')->nullable()->comment('结果');
                $table->unsignedBigInteger('handler_id')->nullable()->comment('经办人');
                $table->timestamps();
                $table->index('asset_id');
            });
        }

        if (!Schema::hasTable('asset_inventories')) {
            Schema::create('asset_inventories', function (Blueprint $table) {
                $table->id();
                $table->string('no', 40)->unique()->comment('盘点单号 PD-YYYYMMDD-NNNN');
                $table->date('date')->nullable()->comment('盘点日期');
                $table->string('status', 20)->default('pending')->comment('pending=盘点中 done=已完成');
                $table->text('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });

            Schema::create('asset_inventory_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventory_id');
                $table->foreign('inventory_id')->references('id')->on('asset_inventories')->onDelete('cascade');
                $table->unsignedBigInteger('asset_id');
                $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
                $table->unsignedInteger('book_qty')->comment('账面数量');
                $table->unsignedInteger('actual_qty')->default(0)->comment('实盘数量');
                $table->integer('difference')->default(0)->comment('差异');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('asset_disposals')) {
            Schema::create('asset_disposals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
                $table->date('date')->nullable()->comment('处置日期');
                $table->string('method', 20)->default('scrap')->comment('scrap=报废 sell=出售 donate=捐赠');
                $table->decimal('amount', 14, 2)->default(0)->comment('残值收入');
                $table->text('reason')->nullable()->comment('处置原因');
                $table->unsignedBigInteger('handler_id')->nullable()->comment('经办人');
                $table->text('remark')->nullable();
                $table->timestamps();
                $table->index('asset_id');
            });
        }

        if (!Schema::hasTable('asset_transfers')) {
            Schema::create('asset_transfers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->foreign('asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
                $table->date('date')->nullable()->comment('调拨日期');
                $table->string('from_location', 200)->nullable()->comment('调出地');
                $table->string('to_location', 200)->nullable()->comment('调入地');
                $table->unsignedBigInteger('from_keeper_id')->nullable()->comment('原保管人');
                $table->unsignedBigInteger('to_keeper_id')->nullable()->comment('新保管人');
                $table->text('remark')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('asset_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['asset_transfers', 'asset_disposals', 'asset_inventory_items', 'asset_inventories',
                  'asset_maintenances', 'asset_depreciations', 'fixed_assets', 'asset_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
