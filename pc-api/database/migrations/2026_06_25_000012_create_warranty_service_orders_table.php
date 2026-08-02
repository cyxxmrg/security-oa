<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.5 质保期管理 - 质保服务工单
 *
 * - 一份工单关联一份 warranty，可指定具体 customer/device
 * - service_type: inspect 巡检 / repair 维修 / clean 清洁 / calibrate 校准 / replace 更换
 * - priority: low / normal / high / urgent
 * - status 状态机: pending → assigned → in_progress → completed / cancelled
 * - customer_signature 存 base64 PNG（电子签名）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warranty_service_orders')) {
            return;
        }

        Schema::create('warranty_service_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('warranty_id');
            $table->foreign('warranty_id')->references('id')->on('warranties')->onDelete('cascade');

            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');

            $table->unsignedBigInteger('device_id')->nullable();
            $table->foreign('device_id')->references('id')->on('customer_devices')->onDelete('set null');

            $table->string('order_no', 50)->unique();

            $table->string('service_type', 30);
            // inspect 巡检 / repair 维修 / clean 清洁 / calibrate 校准 / replace 更换

            $table->string('priority', 20)->default('normal');
            // low / normal / high / urgent

            $table->string('title', 200);
            $table->text('description');

            $table->date('scheduled_date');
            $table->date('completed_date')->nullable();

            $table->unsignedBigInteger('technician_id')->nullable();
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('set null');

            $table->decimal('fee', 10, 2)->default(0);

            $table->string('status', 20)->default('pending');
            // pending 待派工 / assigned 已派工 / in_progress 进行中 / completed 已完成 / cancelled 已取消

            $table->text('result_notes')->nullable();
            $table->text('customer_signature')->nullable();

            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->unsignedBigInteger('completed_by')->nullable();
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['warranty_id', 'status']);
            $table->index(['scheduled_date', 'status']);
            $table->index(['technician_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_service_orders');
    }
};
