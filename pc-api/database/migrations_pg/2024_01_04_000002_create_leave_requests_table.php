<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('申请人');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('type', 50);
            $table->date('start_date')->comment('开始日期');
            $table->date('end_date')->comment('结束日期');
            $table->decimal('days', 4, 1)->comment('请假天数');
            $table->text('reason')->comment('请假事由');
            $table->string('status', 50);
            $table->unsignedBigInteger('approver_id')->nullable()->comment('审批人');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable()->comment('审批时间');
            $table->text('reject_reason')->nullable()->comment('拒绝原因');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
