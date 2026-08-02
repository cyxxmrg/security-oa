<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.2 供应商评价记录
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_evaluations')) {
            return;
        }

        Schema::create('supplier_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->unsignedBigInteger('project_id')->nullable()->comment('关联项目');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->comment('关联采购单');
            // purchase_orders 表是 2024_01_02_000006_create_purchase_orders_table
            $table->tinyInteger('quality_score')->default(0)->comment('质量评分(1-5)');
            $table->tinyInteger('delivery_score')->default(0)->comment('交付评分(1-5)');
            $table->tinyInteger('service_score')->default(0)->comment('服务评分(1-5)');
            $table->tinyInteger('price_score')->default(0)->comment('价格评分(1-5)');
            $table->decimal('overall_score', 3, 1)->default(0)->comment('综合评分(自动计算)');
            $table->text('pros')->nullable()->comment('优点');
            $table->text('cons')->nullable()->comment('不足');
            $table->date('eval_date')->comment('评价日期');
            $table->unsignedBigInteger('evaluator_id')->nullable()->comment('评价人');
            $table->foreign('evaluator_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('project_id');
            $table->index('eval_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluations');
    }
};
