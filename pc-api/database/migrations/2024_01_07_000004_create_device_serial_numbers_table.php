<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('device_serial_numbers')) {
            return;
        }

        Schema::create('device_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id')->comment('物品ID');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->string('serial_number', 100)->unique()->comment('序列号');
            $table->enum('status', ['in_stock', 'installed', 'in_repair', 'scrapped'])
                ->default('in_stock')
                ->comment('状态');
            $table->unsignedBigInteger('project_id')->nullable()->comment('安装项目');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->unsignedBigInteger('customer_device_id')->nullable()->comment('关联客户设备');
            $table->foreign('customer_device_id')->references('id')->on('customer_devices')->onDelete('set null');
            $table->unsignedBigInteger('stock_record_id')->nullable()->comment('入库记录');
            $table->foreign('stock_record_id')->references('id')->on('stock_records')->onDelete('set null');
            $table->date('install_date')->nullable()->comment('安装日期');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('inventory_item_id');
            $table->index('status');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_serial_numbers');
    }
};
