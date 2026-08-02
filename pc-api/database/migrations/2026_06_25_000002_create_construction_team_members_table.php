<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.3 施工团队成员
 *
 * - user_id 可空：纯外包临时工无系统账号也能记录
 * - 身份证 id_number 用于特种作业（电工/高空）合规留档
 * - join/leave_date 用于人力成本归集
 * - 团队解散时连级 cascade 删除成员
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('construction_team_members')) {
            return;
        }

        Schema::create('construction_team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->comment('所属团队ID');
            $table->foreign('team_id')->references('id')->on('construction_teams')->onDelete('cascade');

            $table->unsignedBigInteger('user_id')->nullable()->comment('系统用户ID（无账号可空）');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->string('name', 50)->comment('成员姓名');
            $table->string('phone', 20)->nullable()->comment('联系电话');
            $table->string('role', 20)->default('worker')
                ->comment('角色:foreman 工长 / worker 工人 / safety 安全员');
            $table->string('id_number', 20)->nullable()->comment('身份证号（特种作业留档）');
            $table->date('join_date')->nullable()->comment('加入日期');
            $table->date('leave_date')->nullable()->comment('离开日期');

            $table->string('status', 20)->default('active')
                ->comment('状态:active 在职 / left 已离开');

            $table->timestamps();

            $table->index('team_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_team_members');
    }
};
