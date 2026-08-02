<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 推荐人居间费结算表 (v0.3.11 P0)
 *
 * 触发：商机 oppsMarkWon 时，若 lead.referrer_id 非空，自动建 pending 记录
 * 状态机：pending → approved (财务审核) → paid (财务发放 + 上传回单)
 * 唯一约束：opportunity_id + referrer_id 防止同一商机同一推荐人重复结算
 */
return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('referral_settlements')) {
            return;
        }

        Schema::create('referral_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->comment('商机ID');
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->onDelete('cascade');
            $table->unsignedBigInteger('referrer_id')->comment('推荐人ID');
            $table->foreign('referrer_id')->references('id')->on('referrers')->onDelete('restrict');
            $table->unsignedBigInteger('lead_id')->nullable()->comment('关联线索ID(历史追溯, leads 表已废弃, 无外键)');
            $table->decimal('amount', 12, 2)->comment('结算金额（推荐人 commission_rate × 商机合同金额）');
            $table->decimal('commission_rate', 5, 2)->comment('结算时使用的佣金比例（快照）');
            $table->decimal('contract_amount', 12, 2)->comment('对应商机合同金额（快照）');
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending')->comment('状态机');
            $table->unsignedBigInteger('created_by')->nullable()->comment('建单用户ID（通常=oppsMarkWon 操作人）');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('审核人ID（财务）');
            $table->timestamp('approved_at')->nullable()->comment('审核时间');
            $table->unsignedBigInteger('paid_by')->nullable()->comment('发放人ID（财务）');
            $table->timestamp('paid_at')->nullable()->comment('发放时间');
            $table->string('payment_voucher', 500)->nullable()->comment('回单文件路径（disk/sales/referral/{year}/{month}/）');
            $table->string('payment_no', 100)->nullable()->comment('流水号/银行流水号');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            // 唯一约束：同一商机同一推荐人只能有一条结算（防止重复建单）
            $table->unique(['opportunity_id', 'referrer_id'], 'rs_opp_referrer_unique');

            // 常用查询索引
            $table->index('status');
            $table->index('referrer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settlements');
    }
};
