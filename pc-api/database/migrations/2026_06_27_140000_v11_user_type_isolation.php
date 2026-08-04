<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * V1.1 admin 隔离 — users 加 is_system + user_type 两列
     *
     * user_type 枚举: system / admin / business
     *   - system:   系统工具人（db:seed 创建的 admin），不参与任何业务
     *   - admin:    业务管理员（销售总监等），有部分业务管理权
     *   - business: 普通员工，参与业务
     *
     * is_system: 简化的 system 标记（与 user_type='system' 等价，方便快速判断）
     * 永远只会有 1 个 user_type='system' 的账号。
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return; // users 还没建（极早期），等基线 migration
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('is_active')
                    ->comment('系统工具人标记，true=不能参与业务');
            }
            if (!Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type', 20)->default('business')->after('is_system')
                    ->comment('system/admin/business');
            }
            if (!Schema::hasIndex('users', 'users_user_type_index')) {
                $table->index('user_type');
            }
            if (!Schema::hasIndex('users', 'users_is_system_index')) {
                $table->index('is_system');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'user_type')) {
                $table->dropIndex(['user_type']);
                $table->dropColumn('user_type');
            }
            if (Schema::hasColumn('users', 'is_system')) {
                $table->dropIndex(['is_system']);
                $table->dropColumn('is_system');
            }
        });
    }
};
