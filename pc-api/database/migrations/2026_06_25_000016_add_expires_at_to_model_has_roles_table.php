<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.5.3 临时权限 — 给 spatie 的 model_has_roles 表加 3 个字段
 *
 * - expires_at: 角色过期时间（null = 永久）
 * - granted_by: 谁授予的（users.id，可空）
 * - reason: 授予理由（500 字内）
 *
 * 这张表 spatie 创建时只有 (role_id, model_type, model_id)，
 * 我们要保留主键 + 加字段。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('model_has_roles')) {
            return; // spatie 表都没建，先放弃（这种情况理论上不可能）
        }

        Schema::table('model_has_roles', function (Blueprint $table) {
            // expires_at: null = 永久
            if (!Schema::hasColumn('model_has_roles', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('model_id');
            }
            // granted_by: 哪个 admin 授予的
            if (!Schema::hasColumn('model_has_roles', 'granted_by')) {
                $table->unsignedBigInteger('granted_by')->nullable()->after('expires_at');
            }
            // reason: 理由（500 字内）
            if (!Schema::hasColumn('model_has_roles', 'reason')) {
                $table->string('reason', 500)->nullable()->after('granted_by');
            }
        });

        // 加索引 — 清理任务会按 expires_at < now() 扫
        if (!$this->indexExists('model_has_roles', 'mhr_expires_at_index')) {
            \Illuminate\Support\Facades\DB::statement(
                'CREATE INDEX mhr_expires_at_index ON model_has_roles (expires_at) WHERE expires_at IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('model_has_roles')) {
            return;
        }
        if ($this->indexExists('model_has_roles', 'mhr_expires_at_index')) {
            \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS mhr_expires_at_index');
        }
        Schema::table('model_has_roles', function (Blueprint $table) {
            if (Schema::hasColumn('model_has_roles', 'reason')) {
                $table->dropColumn('reason');
            }
            if (Schema::hasColumn('model_has_roles', 'granted_by')) {
                $table->dropColumn('granted_by');
            }
            if (Schema::hasColumn('model_has_roles', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $db = \Illuminate\Support\Facades\DB::getDatabaseName();
        $rows = \Illuminate\Support\Facades\DB::select(
            "SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND tablename = ? AND indexname = ?",
            [$table, $indexName]
        );
        return count($rows) > 0;
    }
};
