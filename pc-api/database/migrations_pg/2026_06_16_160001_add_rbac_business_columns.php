<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 给 RBAC 关键表加业务字段
     * - roles.color: 角色徽章色（前端 el-tag 用）
     * - permissions.module: 所属业务模块（用于前端分组）
     * - permissions.description: 业务描述
     * 同时兼容已部署的旧库（用 Schema::hasColumn 判重）
     */
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (!Schema::hasColumn('roles', 'color')) {
                    $table->string('color', 16)->nullable()->default('#0C447C')->after('description');
                }
            });
        }

        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                if (!Schema::hasColumn('permissions', 'module')) {
                    $table->string('module', 64)->nullable()->after('guard_name')->index();
                }
                if (!Schema::hasColumn('permissions', 'description')) {
                    $table->string('description', 255)->nullable()->after('module');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'color')) {
            Schema::table('roles', fn(Blueprint $t) => $t->dropColumn('color'));
        }
        if (Schema::hasColumn('permissions', 'module')) {
            Schema::table('permissions', fn(Blueprint $t) => $t->dropColumn('module'));
        }
        if (Schema::hasColumn('permissions', 'description')) {
            Schema::table('permissions', fn(Blueprint $t) => $t->dropColumn('description'));
        }
    }
};
