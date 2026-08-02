<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('finance_payments')) {
            return;
        }

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receivable_id')->nullable()->comment('应收单ID（部分或全额收款）');
            $table->foreign('receivable_id')->references('id')->on('receivables')->onDelete('cascade');
            $table->unsignedBigInteger('payable_id')->nullable()->comment('应付单ID（部分或全额付款）');
            $table->foreign('payable_id')->references('id')->on('payables')->onDelete('cascade');
            $table->unsignedBigInteger('account_id')->nullable()->comment('收/付款资金账户ID');
            // FK 推迟到 finance_accounts 表建好后再添加（独立迁移）
            $table->decimal('amount', 12, 2)->comment('本次收/付金额');
            $table->date('payment_date')->comment('收/付款日期');
            $table->string('method', 50)->nullable()->comment('收/付款方式(银行转账/现金/支付宝/微信/支票)');
            $table->string('voucher_no', 100)->nullable()->comment('凭证号');
            $table->string('operator', 50)->nullable()->comment('经办人');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('receivable_id');
            $table->index('payable_id');
            $table->index('account_id');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payments');
    }
};
