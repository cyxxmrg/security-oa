<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_payment_requests')) {
            return;
        }

        Schema::create('purchase_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('付款申请单号 PR-YYYY-NNN');
            $table->foreignId('contract_id')->nullable()->comment('关联采购合同')->constrained('purchase_contracts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0)->comment('申请金额');
            $table->string('payment_type', 30)->default('full')->comment('full/advance/progress/retention');
            $table->date('request_date')->nullable()->comment('申请日期');
            $table->string('status', 20)->default('pending')->comment('pending/approved/rejected/paid');
            $table->string('applicant', 50)->nullable();
            $table->foreignId('applicant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable()->comment('付款事由');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approve_remark')->nullable();
            $table->timestamps();
            $table->index(['status', 'payment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payment_requests');
    }
};
