<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_items')) {
            return;
        }

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id')->comment('采购单ID');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->string('item_name', 200)->comment('物品名称');
            $table->string('specification', 200)->nullable()->comment('规格型号');
            $table->decimal('quantity', 10, 2)->comment('数量');
            $table->string('unit', 20)->comment('单位');
            $table->decimal('unit_price', 12, 2)->comment('单价');
            $table->decimal('total_price', 12, 2)->comment('合计');
            $table->decimal('received_quantity', 10, 2)->default(0)->comment('已到货数量');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
