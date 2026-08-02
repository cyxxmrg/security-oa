<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_order_id')->comment('工单ID');
            $table->foreign('service_order_id')->references('id')->on('service_orders')->onDelete('cascade');
            $table->unsignedBigInteger('inventory_item_id')->nullable()->comment('库存物品ID');
            $table->string('part_name', 200)->comment('备件名称');
            $table->integer('quantity')->comment('数量');
            $table->decimal('unit_cost', 10, 2)->default(0)->comment('单价');
            $table->decimal('total_cost', 10, 2)->comment('总费用');
            $table->timestamps();

            $table->index('service_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_parts');
    }
};
