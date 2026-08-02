<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_no', 30)->unique()->comment('项目编号');
            $table->string('name', 200)->comment('项目名称');
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('restrict');
            $table->string('type', 50)->default('camera')->comment('项目类型');
            $table->string('stage', 50)->default('initiation')->comment('项目阶段');
            $table->string('status', 50)->default('pending')->comment('项目状态');
            $table->text('description')->nullable()->comment('项目描述');
            $table->decimal('budget_device', 12, 2)->default(0)->comment('设备费');
            $table->decimal('budget_material', 12, 2)->default(0)->comment('材料费');
            $table->decimal('budget_labor', 12, 2)->default(0)->comment('人工费');
            $table->decimal('budget_outsource', 12, 2)->default(0)->comment('外包费');
            $table->decimal('budget_other', 12, 2)->default(0)->comment('其他费用');
            $table->unsignedSmallInteger('progress')->default(0)->comment('进度(%)');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('项目经理');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
            $table->date('start_date')->nullable()->comment('开始日期');
            $table->date('end_date')->nullable()->comment('预计完成日期');
            $table->date('actual_end_date')->nullable()->comment('实际完成日期');
            $table->string('priority', 50)->default('medium')->comment('优先级');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('stage');
            $table->index('status');
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
