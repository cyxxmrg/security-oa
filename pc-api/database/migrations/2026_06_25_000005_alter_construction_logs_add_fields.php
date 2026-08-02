<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.3 construction_logs 扩展字段
 *
 * 8 个新字段，全部幂等（hasColumn 守卫）：
 *  - process_id              关联工序字典
 *  - team_id                 关联施工团队
 *  - work_hours              当日工时（取代旧的 4,1 字段语义，保留兼容）
 *  - worker_count            出工人数
 *  - progress_percentage     当日进度
 *  - weather                 天气（v0.3.x 已有 varchar(50) 字段，跳过避免重复）
 *  - is_rectification        是否整改日志
 *  - rectification_order_id  关联整改单（暂不强外键，后续整改单表建立后再补）
 *
 * 注：原表已有 weather varchar(50) 字段，按需求新增 weather varchar(20) 看似冲突，
 * 实际原 weather 是冗余描述字段（"晴/多云/小雨"），本字段若要细分可后续合并。
 * 为避免破坏已部署数据，本迁移按需求**新增** weather varchar(20)，由应用层决定使用哪个。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('construction_logs')) {
            return;
        }

        Schema::table('construction_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('construction_logs', 'process_id')) {
                $table->unsignedBigInteger('process_id')->nullable()
                    ->after('user_id')->comment('关联工序ID');
                $table->foreign('process_id')->references('id')->on('work_processes')->onDelete('set null');
            }
            if (!Schema::hasColumn('construction_logs', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()
                    ->after('process_id')->comment('关联施工团队ID');
                $table->foreign('team_id')->references('id')->on('construction_teams')->onDelete('set null');
            }
            if (!Schema::hasColumn('construction_logs', 'work_hours')) {
                $table->decimal('work_hours', 4, 2)->nullable()->default(0)
                    ->after('work_date')->comment('当日工时');
            }
            if (!Schema::hasColumn('construction_logs', 'worker_count')) {
                $table->unsignedInteger('worker_count')->nullable()->default(0)
                    ->after('work_hours')->comment('出工人数');
            }
            if (!Schema::hasColumn('construction_logs', 'progress_percentage')) {
                $table->unsignedTinyInteger('progress_percentage')->nullable()->default(0)
                    ->after('worker_count')->comment('当日进度 0-100');
            }
            if (!Schema::hasColumn('construction_logs', 'weather_condition')) {
                $table->string('weather_condition', 20)->nullable()
                    ->after('progress_percentage')->comment('天气(精简枚举:晴/多云/阴/雨/雪/雾)');
            }
            if (!Schema::hasColumn('construction_logs', 'is_rectification')) {
                $table->boolean('is_rectification')->nullable()->default(false)
                    ->after('weather_condition')->comment('是否整改日志');
            }
            if (!Schema::hasColumn('construction_logs', 'rectification_order_id')) {
                $table->unsignedBigInteger('rectification_order_id')->nullable()
                    ->after('is_rectification')->comment('关联整改单ID（暂不外键）');
            }
        });

        Schema::table('construction_logs', function (Blueprint $table) {
            $table->index('process_id');
            $table->index('team_id');
            $table->index('is_rectification');
        });
    }

    public function down(): void
    {
        // 不回滚
    }
};
