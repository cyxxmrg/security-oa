<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_requirements')) {
            return;
        }

        Schema::create('purchase_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('需求编号 REQ-YYYY-NNN');
            $table->foreignId('project_id')->nullable()->comment('关联项目')->constrained('projects')->nullOnDelete();
            $table->string('material', 200)->comment('需求物资名称');
            $table->string('spec', 200)->nullable()->comment('规格型号');
            $table->decimal('quantity', 12, 2)->default(0)->comment('数量');
            $table->string('unit', 20)->default('件')->comment('单位');
            $table->date('need_date')->nullable()->comment('需求日期');
            $table->string('priority', 20)->default('medium')->comment('优先级 low/medium/high/urgent');
            $table->string('status', 20)->default('pending')->comment('状态 pending/approved/rejected/cancelled');
            $table->string('creator', 50)->nullable()->comment('发起人');
            $table->text('remark')->nullable()->comment('备注');
            $table->text('review_remark')->nullable()->comment('审核备注');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requirements');
    }
};
