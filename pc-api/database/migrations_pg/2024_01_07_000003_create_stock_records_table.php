<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_no', 30)->unique()->comment('单号');
            $table->unsignedBigInteger('inventory_item_id')->comment('物品ID');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->unsignedBigInteger('warehouse_id')->comment('仓库ID');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->string('type', 50);
            $table->unsignedInteger('quantity')->comment('数量');
            $table->unsignedInteger('remaining_stock')->comment('剩余库存');
            $table->unsignedBigInteger('related_id')->nullable()->comment('关联单号ID');
            $table->string('related_type', 100)->nullable()->comment('关联单号类型');
            $table->unsignedBigInteger('operator_id')->comment('操作人');
            $table->foreign('operator_id')->references('id')->on('users')->onDelete('restrict');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('inventory_item_id');
            $table->index('warehouse_id');
            $table->index('type');
            $table->index('operator_id');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_records');
    }
};
