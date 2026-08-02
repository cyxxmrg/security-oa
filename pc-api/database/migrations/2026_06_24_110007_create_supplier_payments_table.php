<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.2 供应商付款记录 (supplier_payments)
 * 一笔付款可对应多张 supplier_payables（按金额分配）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_payments')) {
            return;
        }

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->decimal('amount', 14, 2)->comment('付款金额');
            $table->date('payment_date')->comment('付款日期');
            $table->enum('method', ['cash', 'bank', 'alipay', 'wechat', 'other'])
                ->default('bank')->comment('付款方式');
            $table->string('voucher_no', 50)->nullable()->comment('凭证号');
            $table->jsonb('allocations')->nullable()->comment('分摊到 supplier_payables.id 的金额明细');
            $table->string('bank_account', 50)->nullable()->comment('收款账号');
            $table->string('operator', 50)->nullable()->comment('操作人(姓名)');
            $table->text('remark')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->nullable()->comment('系统创建人 user_id');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('payment_date');
            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
