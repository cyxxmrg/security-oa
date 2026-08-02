<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.3 施工发包投标
 *
 * - 投标与发包一对多；发包取消时投标级联删除
 * - bidder_user_id 记录当时操作投标的 user，supplier 帐号体系独立时便于追溯
 * - 状态: submitted -> shortlisted -> accepted / rejected
 * - suppliers 是 V0.4.2 已建表，此处引用即可
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_construction_bids')) {
            return;
        }

        Schema::create('external_construction_bids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_id')->comment('所属发包ID');
            $table->foreign('work_id')->references('id')->on('external_construction_works')->onDelete('cascade');

            $table->unsignedBigInteger('supplier_id')->comment('投标供应商ID');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');

            $table->unsignedBigInteger('bidder_user_id')->nullable()->comment('投标操作人 user_id');
            $table->foreign('bidder_user_id')->references('id')->on('users')->onDelete('set null');

            $table->decimal('bid_amount', 12, 2)->comment('投标报价(元)');
            $table->unsignedInteger('bid_days')->nullable()->comment('承诺工期(天)');

            $table->text('technical_proposal')->nullable()->comment('技术方案');
            $table->text('construction_plan')->nullable()->comment('施工组织计划');
            $table->jsonb('team_info')->nullable()->comment('拟派团队 [{name,role,cert}]');
            $table->jsonb('attachments')->nullable()->comment('附件');

            $table->string('status', 20)->default('submitted')
                ->comment('状态:submitted 已投标 / shortlisted 入围 / accepted 中标 / rejected 未中标');

            $table->timestamp('evaluated_at')->nullable()->comment('评标时间');
            $table->unsignedBigInteger('evaluator_id')->nullable()->comment('评标人');
            $table->foreign('evaluator_id')->references('id')->on('users')->onDelete('set null');
            $table->decimal('evaluation_score', 4, 2)->nullable()->comment('评标得分 0-100');
            $table->text('evaluation_comment')->nullable()->comment('评标意见');

            $table->timestamps();

            $table->index('work_id');
            $table->index('supplier_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_construction_bids');
    }
};
