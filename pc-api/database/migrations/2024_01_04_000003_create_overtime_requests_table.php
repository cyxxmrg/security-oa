<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('overtime_requests')) {
            return;
        }

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('申请人');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->date('overtime_date')->comment('加班日期');
            $table->time('start_time')->comment('开始时间');
            $table->time('end_time')->comment('结束时间');
            $table->decimal('hours', 4, 1)->comment('加班小时');
            $table->text('reason')->comment('加班原因');
            $table->enum('compensation_type', ['pay', 'leave', 'default_pay'])
                ->default('leave')
                ->comment('加班补偿方式');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending')
                ->comment('审批状态');
            $table->unsignedBigInteger('approver_id')->nullable()->comment('审批人');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable()->comment('审批时间');
            $table->decimal('timesheet_leave_hours', 4, 1)->default(0)->comment('已调休小时');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('overtime_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
