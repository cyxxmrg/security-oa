<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 30)->unique()->comment('工单号');
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('restrict');
            $table->unsignedBigInteger('project_id')->nullable()->comment('关联项目');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->unsignedBigInteger('customer_device_id')->nullable()->comment('关联设备');
            $table->text('fault_description')->comment('故障描述');
            $table->json('fault_photos')->nullable()->comment('故障照片');
            $table->string('urgency', 50)->default('normal')->comment('紧急程度');
            $table->string('service_type', 50)->default('warranty')->comment('维修类型');
            $table->string('status', 50)->default('pending')->comment('工单状态');
            $table->unsignedBigInteger('assigned_to')->nullable()->comment('维修人员');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable()->comment('派单时间');
            $table->timestamp('started_at')->nullable()->comment('开始维修时间');
            $table->timestamp('completed_at')->nullable()->comment('维修完成时间');
            $table->timestamp('confirmed_at')->nullable()->comment('客户确认时间');
            $table->unsignedTinyInteger('rating')->nullable()->comment('评分(1-5)');
            $table->text('review')->nullable()->comment('评价内容');
            $table->unsignedBigInteger('created_by')->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->unsignedSmallInteger('sla_hours')->default(24)->comment('SLA响应时效(小时)');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
            $table->index('assigned_to');
            $table->index('urgency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
