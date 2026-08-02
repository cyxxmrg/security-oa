<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.3 工序进度
 *
 * - 三元唯一 (project_id, process_id, team_id)：同一项目下同一工序对同一团队只一条
 * - planned/completed_quantity 同单位 unit（如"米/台/点位"）
 * - 团队解散时连级 cascade（与 construction_teams.onDelete cascade 协同）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_process_progress')) {
            return;
        }

        Schema::create('work_process_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->unsignedBigInteger('process_id')->comment('工序ID');
            $table->foreign('process_id')->references('id')->on('work_processes')->onDelete('restrict');

            $table->unsignedBigInteger('team_id')->comment('施工团队ID');
            $table->foreign('team_id')->references('id')->on('construction_teams')->onDelete('cascade');

            $table->decimal('planned_quantity', 10, 2)->nullable()->comment('计划工程量');
            $table->decimal('completed_quantity', 10, 2)->default(0)->comment('已完工程量');
            $table->string('unit', 20)->nullable()->comment('单位:米/台/点位/套');

            $table->unsignedTinyInteger('progress_percentage')->default(0)->comment('进度 0-100');
            $table->string('status', 20)->default('pending')
                ->comment('状态:pending 未开始 / in_progress 进行中 / completed 已完成 / blocked 阻塞');
            $table->date('start_date')->nullable()->comment('实际开工日');
            $table->date('end_date')->nullable()->comment('实际完工日');
            $table->text('block_reason')->nullable()->comment('阻塞原因');

            $table->timestamps();

            $table->unique(['project_id', 'process_id', 'team_id'], 'wpp_project_process_team_unique');
            $table->index('project_id');
            $table->index('process_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_process_progress');
    }
};
