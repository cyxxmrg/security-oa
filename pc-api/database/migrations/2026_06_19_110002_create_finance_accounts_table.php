<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('finance_accounts')) {
            return;
        }

        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('账户名称');
            $table->enum('type', ['bank', 'cash', 'alipay', 'wechat', 'other'])
                ->default('bank')
                ->comment('账户类型：银行/现金/支付宝/微信/其他');
            $table->decimal('balance', 14, 2)->default(0)->comment('当前余额');
            $table->string('bank_name', 100)->nullable()->comment('开户行');
            $table->string('account_no', 50)->nullable()->comment('账号');
            $table->string('currency', 10)->default('CNY')->comment('币种');
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active')->comment('账户状态');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_accounts');
    }
};
