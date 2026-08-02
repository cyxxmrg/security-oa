<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * V0.6.5 招标中心 Sprint 4 — 保证金规则 + 收退记录
 *
 * 两张表：
 * 1) tender_deposit_rules — 每个招标 1 条规则 (1:1)
 *    - required (bool) - 是否必须缴纳
 *    - amount (decimal 14,2) - 保证金金额
 *    - deadline_hours (int) - 距开标 N 小时前必须缴清
 *    - refund_policy (jsonb) - {auto_refund_days, forfeit_on_no_contract_sign_days}
 *    - bank_account (text, nullable) - 收款银行账户
 *
 * 2) tender_deposits — 每 (招标, 供应商) 1 条 (1:N)
 *    - status (string) - pending/paid/refunded/forfeited/partial_refund
 *    - amount (decimal 14,2)
 *    - paid_at, refunded_at, refund_amount
 *    - refund_reason, paid_voucher_path (凭证文件)
 *
 * 幂等保护: hasTable/hasColumn 检测
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) 保证金规则
        if (!Schema::hasTable('tender_deposit_rules')) {
            Schema::create('tender_deposit_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tender_project_id')->unique()->constrained('tender_projects')->onDelete('cascade');
                $table->boolean('required')->default(true)->comment('是否必须缴纳保证金');
                $table->decimal('amount', 14, 2)->default(0)->comment('保证金金额');
                $table->integer('deadline_hours_before_open')->default(24)->comment('距开标 N 小时前必须缴清');
                $table->jsonb('refund_policy')->nullable()->comment('{auto_refund_days: 7, forfeit_on_no_contract_sign_days: 14}');
                $table->string('bank_account', 200)->nullable()->comment('收款银行账户');
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
            });
        }

        // 2) 保证金缴纳/退还记录
        if (!Schema::hasTable('tender_deposits')) {
            Schema::create('tender_deposits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tender_project_id')->constrained('tender_projects')->onDelete('cascade');
                $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
                $table->decimal('amount', 14, 2)->default(0)->comment('保证金金额 (冗余, 通常 = rule.amount)');
                $table->string('status', 20)->default('pending')->comment('pending/paid/refunded/forfeited/partial_refund');
                // 缴费
                $table->timestamp('paid_at')->nullable();
                $table->string('paid_voucher_path')->nullable()->comment('付款凭证文件路径');
                $table->foreignId('marked_paid_by')->nullable()->constrained('users')->onDelete('set null')->comment('财务确认收款人');
                // 退款
                $table->timestamp('refunded_at')->nullable();
                $table->decimal('refund_amount', 14, 2)->nullable()->comment('实际退款金额 (可能 < amount, 例如部分扣)');
                $table->string('refunded_voucher_path')->nullable();
                $table->foreignId('refunded_by')->nullable()->constrained('users')->onDelete('set null');
                $table->text('refund_reason')->nullable();
                $table->string('refund_method', 50)->nullable()->comment('bank_transfer/cash/original_channel');
                // 没收
                $table->timestamp('forfeited_at')->nullable();
                $table->foreignId('forfeited_by')->nullable()->constrained('users')->onDelete('set null');
                $table->text('forfeit_reason')->nullable();
                $table->timestamps();

                // 每个供应商对每个招标只能有一条
                $table->unique(['tender_project_id', 'supplier_id'], 'tender_deposits_tender_supplier_unique');
                // 按状态查询
                $table->index(['tender_project_id', 'status'], 'tender_deposits_tender_status_idx');
                $table->index(['status', 'paid_at'], 'tender_deposits_status_paid_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_deposits');
        Schema::dropIfExists('tender_deposit_rules');
    }
};
