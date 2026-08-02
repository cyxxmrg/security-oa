<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.3 项目开工单
 *
 * - code 唯一：业务编号 COMM-2026-001
 * - 现场联系人和附件用 jsonb 数组（多值场景不必再开子表）
 * - 状态机: draft -> approved -> in_progress -> completed / cancelled
 * - 团队解散时团队相关开工单 set null（保留历史单据）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_commencement_orders')) {
            return;
        }

        Schema::create('project_commencement_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->string('code', 30)->unique()->comment('开工单号 COMM-YYYY-NNN');
            $table->unsignedBigInteger('team_id')->nullable()->comment('施工团队ID');
            $table->foreign('team_id')->references('id')->on('construction_teams')->onDelete('set null');

            $table->date('commencement_date')->comment('计划开工日期');
            $table->date('planned_end_date')->nullable()->comment('计划完工日期');
            $table->date('actual_end_date')->nullable()->comment('实际完工日期');

            $table->text('work_content')->nullable()->comment('主要施工内容');
            $table->string('work_location', 200)->nullable()->comment('施工地点');
            $table->text('safety_requirements')->nullable()->comment('安全要求');
            $table->text('quality_requirements')->nullable()->comment('质量要求');

            $table->jsonb('on_site_contacts')->nullable()->comment('现场联系人 [{name, phone, role}]');
            $table->jsonb('attachments')->nullable()->comment('附件 [{name, path, size}]');

            $table->string('status', 20)->default('draft')
                ->comment('状态:draft 草稿 / approved 已审批 / in_progress 施工中 / completed 已完工 / cancelled 已取消');

            $table->unsignedBigInteger('approved_by')->nullable()->comment('审批人');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable()->comment('审批时间');

            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();

            $table->index('project_id');
            $table->index('team_id');
            $table->index('status');
            $table->index('commencement_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_commencement_orders');
    }
};
