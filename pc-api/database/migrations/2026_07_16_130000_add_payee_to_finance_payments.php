<?php

/**
 * V1.2.16: 给 finance_payments 加 payee 字段
 * 用于单独存储付款单的收款方名称
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('finance_payments', 'payee')) {
                $table->string('payee', 200)->nullable()->after('voucher_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            if (Schema::hasColumn('finance_payments', 'payee')) {
                $table->dropColumn('payee');
            }
        });
    }
};