<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('purchase_plans')) {
            return;
        }

        Schema::create('purchase_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('计划编号 PP-YYYY-NNN');
            $table->foreignId('requirement_id')->nullable()->comment('来源采购需求')->constrained('purchase_requirements')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('title', 200)->comment('计划标题');
            $table->decimal('total_amount', 14, 2)->default(0)->comment('预算总额');
            $table->date('plan_date')->nullable()->comment('计划采购日期');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('draft')->comment('draft/submitted/approved/rejected/cancelled');
            $table->foreignId('submitter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approve_remark')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_plans');
    }
};
