<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.3 工序字典
 *
 * - project_id NULL: 通用工序（系统预置，全公司复用）
 * - project_id 非空: 项目自定义工序（同名不能重复）
 * - PostgreSQL 通过 partial unique index 实现「项目内同名不重复」+「通用层不冲突」
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_processes')) {
            return;
        }

        Schema::create('work_processes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable()->comment('项目ID，NULL 表示通用工序');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->string('name', 50)->comment('工序名称:线管敷设/线缆穿管/设备安装/调试/...');
            $table->unsignedInteger('sequence')->default(0)->comment('排序');
            $table->text('description')->nullable()->comment('工序说明');
            $table->decimal('estimated_hours', 8, 2)->nullable()->comment('预估工时');

            $table->string('status', 20)->default('active')
                ->comment('状态:active 启用 / disabled 停用');

            $table->timestamps();

            $table->index('project_id');
        });

        // 项目维度 partial unique index：同项目内不允许重名，通用层（NULL）不限
        DB::statement(
            'CREATE UNIQUE INDEX work_processes_project_id_name_unique '
            . 'ON work_processes (project_id, name) WHERE project_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('work_processes');
    }
};
