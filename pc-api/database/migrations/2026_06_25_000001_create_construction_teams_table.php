<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.3 施工团队
 *
 * - 按项目组织施工团队（自营 internal / 外包 outsource）
 * - 队长信息可冗余（即便 leader_user_id 为空也能展示）
 * - 软删：解散时打 deleted_at，历史施工日志仍能 JOIN 追溯
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('construction_teams')) {
            return;
        }

        Schema::create('construction_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('所属项目ID');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->string('team_name', 100)->comment('团队名称');
            $table->string('team_type', 20)->default('internal')
                ->comment('团队类型:internal 自营 / outsource 外包');
            $table->unsignedBigInteger('leader_user_id')->nullable()->comment('队长系统用户ID');
            $table->foreign('leader_user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('leader_name', 50)->nullable()->comment('队长姓名（冗余）');
            $table->string('leader_phone', 20)->nullable()->comment('队长电话');
            $table->unsignedInteger('member_count')->default(0)->comment('成员人数（冗余统计）');
            $table->string('specialty', 200)->nullable()->comment('专长:弱电/网络/安防/综合');

            $table->string('status', 20)->default('active')
                ->comment('状态:active 在用 / disbanded 已解散');

            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('status');
            $table->index('team_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_teams');
    }
};
