<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2.7 P2-4: 修复 v12 migration 假设 system_settings.group_name 存在但建表 migration 未加
 *
 * 背景: 2026_06_17_000001_create_system_settings_table.php 建表时只有 key/value/description/updated_at/updated_by
 *        2026_06_27 v12 migration insert 时用了 group_name
 *        production 当时跑过老版本有这列所以 OK, 但 fresh migrate 必然失败
 *
 * 修复: 在 v12 之前补这列, 同时给所有 v12+ 走 fresh migrate 的环境 (test DB) 提供兼容
 * 幂等: hasColumn 检查, 已存在则跳过
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        // 防御性: 兜底 v12_migration.php (老版本) 用的 type 列
        if (!Schema::hasColumn('system_settings', 'type')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->string('type', 30)->default('string')->after('value')
                    ->comment('值的类型: string/boolean/integer/json');
            });
        }

        // 主修复: v12 new 用 group_name 列
        if (!Schema::hasColumn('system_settings', 'group_name')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->string('group_name', 50)->default('general')->after('value')
                    ->comment('设置分组 (general/notification/license/...)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_settings', 'group_name')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('group_name');
            });
        }
        if (Schema::hasColumn('system_settings', 'type')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
