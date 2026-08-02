<?php

/**
 * V1.2.16: 给 customer_receipts 加 account_id 字段
 * 用于支持收款时选择资金账户（入账到哪个银行/现金账户）
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_receipts', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('bank_account');
                $table->foreign('account_id')->references('id')->on('finance_accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('customer_receipts', 'account_id')) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
        });
    }
};