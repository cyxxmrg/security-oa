<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_payments')) {
            return;
        }

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('付款单号 PAY-YYYY-NNN');
            $table->foreignId('payment_request_id')->nullable()->comment('关联付款申请')->constrained('purchase_payment_requests')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('purchase_contracts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0)->comment('实付金额');
            $table->string('payment_method', 30)->default('transfer')->comment('transfer/cash/check/other');
            $table->date('paid_at')->nullable()->comment('付款日期');
            $table->string('voucher_no', 80)->nullable()->comment('银行流水号 / 凭证号');
            $table->string('operator', 50)->nullable()->comment('经办人');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('success')->comment('success/failed/reversed');
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index(['status', 'payment_method']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};
