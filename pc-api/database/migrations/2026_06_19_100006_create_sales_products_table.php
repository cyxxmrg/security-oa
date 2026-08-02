<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('sales_products')) {
            return;
        }

        Schema::create('sales_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('产品编号 SP-2026-001');
            $table->string('name', 200)->comment('产品名称');
            $table->unsignedBigInteger('category_id')->nullable()->comment('产品分类ID');
            $table->foreign('category_id')->references('id')->on('inventory_categories')->onDelete('set null');
            $table->string('unit', 20)->default('件')->comment('计量单位');
            $table->string('spec', 200)->nullable()->comment('规格型号');
            $table->decimal('sale_price', 12, 2)->default(0)->comment('标准售价');
            $table->decimal('cost_price', 12, 2)->default(0)->comment('参考成本');
            $table->text('description')->nullable()->comment('产品描述');
            $table->string('status', 20)->default('active')->comment('active/inactive');
            $table->timestamps();

            $table->index('category_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_products');
    }
};
