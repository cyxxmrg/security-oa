<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.3 施工发包（外包工程招标）
 *
 * - code 唯一：ECW-2026-001
 * - 状态机: open -> bidding -> evaluating -> awarded -> completed / cancelled
 * - awarded_bid_id / awarded_supplier_id / awarded_amount 授标后回填
 *   awarded_bid_id 与 external_construction_bids 是逻辑外键（避免相互循环），由应用层维护
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_construction_works')) {
            return;
        }

        Schema::create('external_construction_works', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('所属项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->string('code', 30)->unique()->comment('发包单号 ECW-YYYY-NNN');
            $table->string('title', 200)->comment('发包标题');

            $table->text('work_scope')->nullable()->comment('工作范围');
            $table->jsonb('required_skills')->nullable()->comment('所需技能 ["弱电","高空作业证",...]');
            $table->decimal('estimated_budget', 12, 2)->nullable()->comment('预估金额(元)');

            $table->date('start_date')->nullable()->comment('计划开工日');
            $table->date('end_date')->nullable()->comment('计划完工日');
            $table->date('bid_deadline')->nullable()->comment('投标截止日期');

            $table->unsignedInteger('bid_count')->default(0)->comment('已投标数（冗余）');

            $table->string('status', 20)->default('open')
                ->comment('状态:open 待发布 / bidding 投标中 / evaluating 评标中 / awarded 已定标 / cancelled 已取消 / completed 已完成');

            $table->unsignedBigInteger('awarded_bid_id')->nullable()->comment('中标投标ID（逻辑外键）');
            $table->unsignedBigInteger('awarded_supplier_id')->nullable()->comment('中标供应商ID');
            $table->foreign('awarded_supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->decimal('awarded_amount', 12, 2)->nullable()->comment('中标金额(元)');

            $table->jsonb('attachments')->nullable()->comment('附件 [{name,path,size}]');

            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
            $table->index('bid_deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_construction_works');
    }
};
