<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：列已存在则跳过
        if (Schema::hasColumn('finance_payments', 'account_id')) {
            return;
        }

        // finance_payments 早期建表时未加 FK（避免与 finance_accounts 顺序冲突），此处补齐
        Schema::table('finance_payments', function (Blueprint $table) {
            $table->foreign('account_id', 'finance_payments_account_id_foreign')
                ->references('id')->on('finance_accounts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // v0.3.18 幂等保护：列不存在则跳过 rollback
        if (!Schema::hasColumn('finance_payments', 'account_id')) {
            return;
        }

        Schema::table('finance_payments', function (Blueprint $table) {
            $table->dropForeign('finance_payments_account_id_foreign');
        });
    }
};
