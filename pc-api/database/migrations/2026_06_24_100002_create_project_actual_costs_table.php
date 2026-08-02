<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.1 项目预算模块 - 实际成本流水表
 *
 * - 由各类源单据(purchase_in / stock_out / expense / payroll / outsource_payment)反写生成
 * - UNIQUE(source_type, source_id, category):同一源单据同一类别只能落账一次
 * - metadata:JSONB 存源单据快照,便于追溯和对账
 * - project_budgets.*_actual 由 service 实时按 (project_id, category) 聚合此表得到
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_actual_costs')) {
            return;
        }

        Schema::create('project_actual_costs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete()
                ->comment('所属项目');

            $table->string('source_type', 32)
                ->comment('来源单据类型 purchase_in/stock_out/expense/payroll/outsource_payment');
            $table->unsignedBigInteger('source_id')
                ->comment('来源单据 ID');

            $table->string('category', 16)
                ->comment('费用类别 material/labor/outsource/other');

            $table->decimal('amount', 15, 2)
                ->comment('实际金额');

            $table->date('cost_date')
                ->comment('费用发生日期');

            $table->string('description', 255)
                ->nullable()
                ->comment('摘要/说明');

            $table->jsonb('metadata')
                ->nullable()
                ->comment('源单据快照 JSON');

            $table->timestamps();

            // 防重:同一源单据 + 同一类别 只能有一条成本流水
            $table->unique(
                ['source_type', 'source_id', 'category'],
                'uq_pac_source_category'
            );

            // 项目维度按日期查询/统计
            $table->index(['project_id', 'cost_date'], 'idx_pac_project');
            // 源单据反查
            $table->index(['source_type', 'source_id'], 'idx_pac_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_actual_costs');
    }
};
