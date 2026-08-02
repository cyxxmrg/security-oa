<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('物品名称');
            $table->string('code', 50)->unique()->comment('物品编码');
            $table->string('category', 100)->comment('分类');
            $table->string('specification', 200)->nullable()->comment('规格型号');
            $table->string('unit', 20)->comment('单位');
            $table->unsignedInteger('safety_stock')->default(0)->comment('安全库存');
            $table->unsignedInteger('current_stock')->default(0)->comment('当前库存');
            $table->decimal('cost_price', 10, 2)->default(0)->comment('成本价');
            $table->decimal('sell_price', 10, 2)->default(0)->comment('销售价');
            $table->unsignedBigInteger('warehouse_id')->nullable()->comment('默认仓库');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->string('location', 100)->nullable()->comment('库位');
            $table->boolean('has_serial')->default(false)->comment('是否序列号管理');
            $table->string('status', 50);
            $table->timestamps();

            $table->index('category');
            $table->index('warehouse_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
