<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.4.2: finance_payments 加 type 列 (付款单类型)
 *
 * 背景: ProjectController::paymentCalendar 用 where('type','standalone') 筛选"独立付款单",
 *       但 finance_payments 表从未建 type 列 -> GET /api/projects/payment-calendar 500。
 *       同时 FinanceController::storePayment 写入 'type'=>'standalone' 因不在 fillable 被静默丢弃。
 * 修复: 补 type 列 default 'linked', 并把 type 加进 FinancePayment::fillable,
 *       新独立付款单 type=standalone, 付款日历恢复正常。
 *
 * 幂等: hasColumn 守卫
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_payments') && !Schema::hasColumn('finance_payments', 'type')) {
            Schema::table('finance_payments', function (Blueprint $table) {
                $table->string('type', 30)->default('linked')->after('project_id')
                    ->comment('付款单类型: linked=关联单据 standalone=独立付款');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('finance_payments') && Schema::hasColumn('finance_payments', 'type')) {
            Schema::table('finance_payments', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
