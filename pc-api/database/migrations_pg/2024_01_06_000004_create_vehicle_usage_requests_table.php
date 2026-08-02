<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_usage_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->nullable()->comment('车辆ID');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('set null');
            $table->unsignedBigInteger('applicant_id')->comment('申请人');
            $table->foreign('applicant_id')->references('id')->on('users')->onDelete('cascade');
            $table->date('usage_date')->comment('用车日期');
            $table->time('start_time')->comment('开始时间');
            $table->time('end_time')->comment('结束时间');
            $table->string('destination', 200)->comment('目的地');
            $table->text('purpose')->comment('用车事由');
            $table->unsignedInteger('passengers')->default(1)->comment('乘车人数');
            $table->boolean('self_drive')->default(false)->comment('是否自驾');
            $table->string('status', 50);
            $table->unsignedBigInteger('approver_id')->nullable()->comment('审批人');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable()->comment('审批时间');
            $table->unsignedBigInteger('actual_mileage')->nullable()->comment('实际里程');
            $table->decimal('actual_fuel', 10, 2)->nullable()->comment('实际油耗');
            $table->unsignedBigInteger('start_mileage')->nullable()->comment('出车里程');
            $table->unsignedBigInteger('end_mileage')->nullable()->comment('还车里程');
            $table->timestamps();

            $table->index('applicant_id');
            $table->index('vehicle_id');
            $table->index('status');
            $table->index('usage_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_usage_requests');
    }
};
