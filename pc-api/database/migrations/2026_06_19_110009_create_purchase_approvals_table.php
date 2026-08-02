<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_approvals')) {
            return;
        }

        Schema::create('purchase_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('审批单号 PA-YYYY-NNN');
            $table->string('target_type', 30)->comment('目标类型 plan/contract/payment_request');
            $table->unsignedBigInteger('target_id')->comment('目标 ID');
            $table->string('title', 200)->comment('审批标题');
            $table->foreignId('applicant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('applicant', 50)->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending/approved/rejected/cancelled');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('approve_remark')->nullable();
            $table->text('reason')->nullable()->comment('申请事由');
            $table->decimal('amount', 14, 2)->nullable()->comment('涉及金额');
            $table->timestamps();
            $table->index(['status', 'target_type']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_approvals');
    }
};
