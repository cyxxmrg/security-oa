<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('project_settlements')) {
            return;
        }

        Schema::create('project_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->unique()->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('restrict');
            $table->decimal('total_income', 12, 2)->default(0)->comment('总收入');
            $table->decimal('total_cost', 12, 2)->default(0)->comment('总成本');
            $table->decimal('cost_labor', 12, 2)->default(0)->comment('人工成本');
            $table->decimal('cost_material', 12, 2)->default(0)->comment('材料成本');
            $table->decimal('cost_outsource', 12, 2)->default(0)->comment('外包成本');
            $table->decimal('cost_other', 12, 2)->default(0)->comment('其他费用');
            $table->decimal('profit', 12, 2)->default(0)->comment('利润');
            $table->decimal('profit_rate', 5, 2)->default(0)->comment('利润率(%)');
            $table->date('settlement_date')->nullable()->comment('结算日期');
            $table->enum('status', ['draft', 'confirmed'])->default('draft')->comment('结算状态');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_settlements');
    }
};
