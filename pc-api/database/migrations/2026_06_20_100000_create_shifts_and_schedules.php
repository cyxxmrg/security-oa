<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('shifts')) {
            return;
        }

        // 1) 班次: 早/中/晚/夜 + 自定义
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('班次名（如：早班/中班/晚班/夜班）');
            $table->string('code', 20)->unique()->comment('班次编码（day/middle/night/evening）');
            $table->time('start_time')->comment('上班时间');
            $table->time('end_time')->comment('下班时间');
            $table->unsignedTinyInteger('late_threshold_minutes')->default(5)->comment('迟到阈值（分钟）');
            $table->unsignedTinyInteger('early_leave_threshold_minutes')->default(5)->comment('早退阈值（分钟）');
            $table->decimal('work_hours', 4, 1)->default(8.0)->comment('标准工时');
            $table->string('color', 20)->default('#0C447C')->comment('日历显示色');
            $table->boolean('is_overnight')->default(false)->comment('是否跨夜班（如22:00-06:00）');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // 2) 班组: 白班组/夜班组/外勤组 ...
        Schema::create('shift_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('班组名');
            $table->string('code', 20)->unique();
            $table->unsignedBigInteger('leader_id')->nullable()->comment('班组长');
            $table->string('color', 20)->default('#1D9E75');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('leader_id')->references('id')->on('users')->onDelete('set null');
        });

        // 3) 班组成员 (多对多)
        Schema::create('shift_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('user_id');
            $table->date('joined_at')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('shift_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['group_id', 'user_id']);
        });

        // 4) 排班记录 (user + date + shift, 可选 group_id)
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('shift_id');
            $table->date('date')->comment('排班日期');
            $table->enum('status', ['scheduled', 'rest', 'sick', 'leave', 'swapped'])->default('scheduled');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('shift_groups')->onDelete('set null');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['user_id', 'date']);
            $table->index(['date', 'shift_id']);
            $table->index(['group_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('shift_group_members');
        Schema::dropIfExists('shift_groups');
        Schema::dropIfExists('shifts');
    }
};
