<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.1 项目预算模块 - 主表
 *
 * - 业务对象:项目的预算版本(支持多版本修订)
 * - code 格式: BUD-YYYY-NNN,全局唯一
 * - 状态机: draft -> approved -> revised -> voided
 * - *_actual 字段由 service 层从 project_actual_costs 实时聚合,不持久化写入
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_budgets')) {
            return;
        }

        Schema::create('project_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete()
                ->comment('所属项目');
            $table->string('code', 32)
                ->unique()
                ->comment('预算编号 BUD-YYYY-NNN');
            $table->unsignedInteger('version')
                ->default(1)
                ->comment('修订版本号');

            $table->string('status', 16)
                ->default('draft')
                ->comment('状态 draft/approved/revised/voided');

            // ----- 预算金额(四类 + 合计) -----
            $table->decimal('material_budget', 15, 2)
                ->default(0)
                ->comment('材料预算');
            $table->decimal('labor_budget', 15, 2)
                ->default(0)
                ->comment('人工预算');
            $table->decimal('outsource_budget', 15, 2)
                ->default(0)
                ->comment('外包预算');
            $table->decimal('other_budget', 15, 2)
                ->default(0)
                ->comment('其他预算');
            $table->decimal('total_budget', 15, 2)
                ->default(0)
                ->comment('预算合计');

            // ----- 实际成本(service 实时聚合) -----
            $table->decimal('material_actual', 15, 2)
                ->default(0)
                ->comment('材料实际');
            $table->decimal('labor_actual', 15, 2)
                ->default(0)
                ->comment('人工实际');
            $table->decimal('outsource_actual', 15, 2)
                ->default(0)
                ->comment('外包实际');
            $table->decimal('other_actual', 15, 2)
                ->default(0)
                ->comment('其他实际');
            $table->decimal('total_actual', 15, 2)
                ->default(0)
                ->comment('实际合计');

            // ----- 审批信息 -----
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('审批人');
            $table->timestamp('approved_at')
                ->nullable()
                ->comment('审批时间');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('创建人');

            $table->timestamps();
            $table->text('remark')->nullable()->comment('备注');

            // 常用查询索引:按项目 + 状态筛选
            $table->index(['project_id', 'status'], 'idx_pb_project');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budgets');
    }
};
