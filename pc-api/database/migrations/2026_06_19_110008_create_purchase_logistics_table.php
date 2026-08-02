<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_logistics')) {
            return;
        }

        Schema::create('purchase_logistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('purchase_shipments')->cascadeOnDelete();
            $table->string('tracking_no', 100)->nullable()->comment('运单号');
            $table->dateTime('event_at')->comment('事件时间');
            $table->string('location', 200)->nullable()->comment('位置');
            $table->string('status', 30)->nullable()->comment('状态描述');
            $table->text('description')->nullable()->comment('详情');
            $table->string('operator', 50)->nullable()->comment('操作方');
            $table->timestamps();
            $table->index(['shipment_id', 'event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_logistics');
    }
};
