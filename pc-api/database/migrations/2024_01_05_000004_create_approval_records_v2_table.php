<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 审批中心记录表 v2 — 聚合财务/运营/项目 3 大类审批
     * 与现有 approval_records (多态关联) 共存，本表为审批中心专用
     */
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('approval_records_v2')) {
            return;
        }

        Schema::create('approval_records_v2', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique()->comment('审批单号 (FIN/OPS/PRJ-YYYY-NNNN)');
            $table->string('type', 50)->comment('审批大类: finance|operation|project');
            $table->string('sub_type', 50)->comment('审批子类: expense|leave|...');
            $table->string('title', 255)->comment('审批标题');
            $table->string('priority', 20)->default('normal')->comment('优先级: urgent|high|normal|low');
            $table->string('status', 20)->default('pending')->comment('状态: pending|approved|rejected|transferred|cancelled');
            $table->decimal('amount', 14, 2)->default(0)->comment('金额 (财务类专用)');
            $table->string('bank_account', 200)->nullable()->comment('收款账户');
            $table->date('start_date')->nullable()->comment('开始日期 (运营/项目)');
            $table->date('end_date')->nullable()->comment('结束日期');
            $table->string('to_stage', 50)->nullable()->comment('目标阶段 (项目类)');
            $table->unsignedBigInteger('applicant_id')->comment('发起人');
            $table->foreign('applicant_id')->references('id')->on('users')->onDelete('restrict');
            $table->unsignedBigInteger('current_approver_id')->nullable()->comment('当前审批人');
            $table->foreign('current_approver_id')->references('id')->on('users')->onDelete('set null');
            $table->json('payload')->nullable()->comment('业务载荷/审批详情');
            $table->json('flow')->nullable()->comment('审批流转记录 (流程时间线)');
            $table->json('cc')->nullable()->comment('抄送人ID列表');
            $table->text('comment')->nullable()->comment('最终审批意见');
            $table->timestamps();

            $table->index('type');
            $table->index('sub_type');
            $table->index('status');
            $table->index('priority');
            $table->index('applicant_id');
            $table->index('current_approver_id');
            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_records_v2');
    }
};
