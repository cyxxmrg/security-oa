<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.3 整改日志要求（按项目×日期）
 *
 * - 由系统在某些项目（涉及验收不通过等）写入，强制要求上传当日施工日志
 * - status: pending 待提交 / submitted 已提交 / overdue 超时
 * - submitted_log_id 反向关联 construction_logs，log 删除时清空（不删本行）
 * - 复合唯一 (project_id, work_date) 防止同一天重复创建
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rectification_daily_required')) {
            return;
        }

        Schema::create('rectification_daily_required', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->date('work_date')->comment('要求日期');
            $table->boolean('is_required')->default(true)->comment('是否必须提交');
            $table->string('status', 20)->default('pending')
                ->comment('状态:pending 待提交 / submitted 已提交 / overdue 超时');

            $table->unsignedBigInteger('submitted_log_id')->nullable()->comment('已提交日志ID');
            $table->foreign('submitted_log_id')->references('id')->on('construction_logs')->onDelete('set null');

            $table->timestamp('overdue_notified_at')->nullable()->comment('超时通知发送时间');

            $table->timestamps();

            $table->unique(['project_id', 'work_date'], 'rdr_project_date_unique');
            $table->index(['work_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rectification_daily_required');
    }
};
