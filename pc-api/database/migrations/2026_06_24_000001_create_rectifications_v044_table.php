<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.4 整改工单主表 (rectification_orders → 简化为 rectifications)
 *
 * 字段集 (V0.4.4):
 *  - project_id, commencement_order_id (optional), construction_log_id (父日志)
 *  - source_type (inspection/patrol/complaint/audit/other), source_id
 *  - title, description, severity (low/medium/high/critical)
 *  - responsible_id (nullable), deadline
 *  - status (pending/in_progress/completed/verified/rejected)
 *  - internal_acceptance_at, internal_acceptance_by
 *  - customer_acceptance_at, customer_acceptance_by
 *  - images (json), remark
 *
 * 全部幂等 (hasTable/hasColumn 守卫)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rectifications')) {
            return;
        }

        Schema::create('rectifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->unsignedBigInteger('commencement_order_id')->nullable()->comment('关联开工单ID');
            // v0.5.8 修复: project_commencement_orders 在更晚的 migration 建, 这里只加列不加外键
            $table->unsignedBigInteger('construction_log_id')->nullable()->comment('父施工日志ID');
            $table->foreign('construction_log_id')->references('id')->on('construction_logs')->onDelete('set null');

            $table->string('code', 30)->unique()->comment('整改单号 RECT-YYYY-NNNN');
            $table->string('source_type', 30)->default('other')->comment('来源:inspection/patrol/complaint/audit/other');
            $table->unsignedBigInteger('source_id')->nullable()->comment('来源记录ID');
            $table->string('title', 200)->comment('整改标题');
            $table->text('description')->comment('整改内容');
            $table->string('severity', 20)->default('medium')->comment('严重度:low/medium/high/critical');

            $table->unsignedBigInteger('responsible_id')->nullable()->comment('责任人ID');
            $table->foreign('responsible_id')->references('id')->on('users')->onDelete('set null');
            $table->date('deadline')->nullable()->comment('整改期限');

            $table->string('status', 20)->default('pending')
                ->comment('状态:pending/in_progress/completed/verified/rejected');

            // 内部验收 (V0.4.4)
            $table->timestamp('internal_acceptance_at')->nullable();
            $table->unsignedBigInteger('internal_acceptance_by')->nullable();
            $table->foreign('internal_acceptance_by')->references('id')->on('users')->onDelete('set null');
            $table->text('internal_acceptance_remark')->nullable();

            // 客户验收 (V0.4.4)
            $table->timestamp('customer_acceptance_at')->nullable();
            $table->unsignedBigInteger('customer_acceptance_by')->nullable();
            $table->foreign('customer_acceptance_by')->references('id')->on('users')->onDelete('set null');
            $table->text('customer_acceptance_remark')->nullable();

            $table->jsonb('images')->nullable()->comment('现场照片');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->text('remark')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('status');
            $table->index('severity');
            $table->index('deadline');
            $table->index('responsible_id');
        });

        // v0.5.8 修复: 延后补 commencement_order_id 外键 (依赖表在后续 migration 建)
        // artisan migrate 按文件名升序跑, 此 migration 在 2026_06_25_000003 之前
        // 这里只补列, 真正的外键等 2026_06_25_000003 之后建
    }

    public function down(): void
    {
        Schema::dropIfExists('rectifications');
    }
};
