<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.1 项目预算模块 - 明细表
 *
 * - 每个预算版本下的费用明细行
 * - category: material/labor/outsource/other
 * - planned_amount = quantity * unit_price(由 model 事件/observer 维护,DB 层仅存基础字段)
 * - item_id/item_type: 软关联物料库/服务库(不强 FK,允许跨模块空挂)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_budget_items')) {
            return;
        }

        Schema::create('project_budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')
                ->constrained('project_budgets')
                ->cascadeOnDelete()
                ->comment('所属预算版本');

            $table->string('category', 16)
                ->comment('费用类别 material/labor/outsource/other');

            $table->string('item_name', 128)
                ->comment('明细项名称');
            $table->string('specification', 255)
                ->nullable()
                ->comment('规格型号');
            $table->string('unit', 16)
                ->nullable()
                ->comment('计量单位 台/米/个/项/人天');

            $table->decimal('quantity', 10, 2)
                ->default(1)
                ->comment('数量');
            $table->decimal('unit_price', 12, 2)
                ->default(0)
                ->comment('单价');
            $table->decimal('planned_amount', 15, 2)
                ->default(0)
                ->comment('计划金额 = quantity * unit_price');

            // 软关联:可选指向物料/服务主数据(不强 FK,跨模块兼容)
            $table->unsignedBigInteger('item_id')
                ->nullable()
                ->comment('关联物料/服务 ID');
            $table->string('item_type', 32)
                ->nullable()
                ->comment('关联类型 material/service/...');

            $table->text('remark')->nullable()->comment('备注');
            $table->unsignedInteger('sort_order')
                ->default(0)
                ->comment('排序');

            $table->timestamps();

            // 常用查询:按预算版本 + 类别聚合
            $table->index(['budget_id', 'category'], 'idx_pbi_budget');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_items');
    }
};
