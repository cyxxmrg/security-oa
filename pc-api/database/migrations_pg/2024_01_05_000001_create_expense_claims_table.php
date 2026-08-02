<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_no', 30)->unique()->comment('报销单号');
            $table->unsignedBigInteger('user_id')->comment('申请人');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->string('category', 50);
            $table->decimal('total_amount', 12, 2)->default(0)->comment('报销总金额');
            $table->unsignedBigInteger('project_id')->nullable()->comment('关联项目');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->text('description')->comment('报销事由');
            $table->string('status', 50);
            $table->unsignedBigInteger('approver_id')->nullable()->comment('审批人');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable()->comment('审批时间');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            $table->decimal('paid_amount', 12, 2)->nullable()->comment('实付金额');
            $table->text('reject_reason')->nullable()->comment('拒绝原因');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('category');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_claims');
    }
};
