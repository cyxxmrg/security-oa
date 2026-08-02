<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.2 客户收款记录 (customer_receipts)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_receipts')) {
            return;
        }

        Schema::create('customer_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->decimal('amount', 14, 2)->comment('收款金额');
            $table->date('receipt_date')->comment('收款日期');
            $table->enum('method', ['cash', 'bank', 'alipay', 'wechat', 'check', 'other'])
                ->default('bank')->comment('收款方式');
            $table->string('voucher_no', 50)->nullable()->comment('凭证号');
            $table->jsonb('allocations')->nullable()->comment('分摊到 customer_receivables.id 的金额');
            $table->string('bank_account', 50)->nullable()->comment('客户付款账号');
            $table->string('operator', 50)->nullable()->comment('操作人');
            $table->text('remark')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->nullable()->comment('系统创建人 user_id');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('receipt_date');
            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_receipts');
    }
};
