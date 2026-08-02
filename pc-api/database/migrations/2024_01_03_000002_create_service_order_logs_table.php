<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('service_order_logs')) {
            return;
        }

        Schema::create('service_order_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_order_id')->comment('工单ID');
            $table->foreign('service_order_id')->references('id')->on('service_orders')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->comment('操作人');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->string('action', 50)->comment('操作类型');
            $table->text('content')->comment('操作内容');
            $table->json('photos')->nullable()->comment('照片');
            $table->text('location')->nullable()->comment('位置');
            $table->decimal('gps_lat', 10, 7)->nullable()->comment('GPS纬度');
            $table->decimal('gps_lng', 10, 7)->nullable()->comment('GPS经度');
            $table->timestamps();

            $table->index('service_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_logs');
    }
};
