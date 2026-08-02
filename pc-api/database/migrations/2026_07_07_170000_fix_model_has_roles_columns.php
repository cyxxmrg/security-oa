<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2.10 — 修复 migration 000016 假阳性坑
 *
 * 背景:
 *   - 2026_06_25_000016_add_expires_at_to_model_has_roles_table.php 在 migrations 表登记已执行
 *   - 但 model_has_roles 表实际没加 expires_at/granted_by/reason 列
 *   - 根因: 000016 用 Schema::hasColumn 守卫, 在某些 PG 版本/部署场景下行为异常,
 *     或 migration 被标记为已执行但 up() 实际未生效
 *   - 后果: User::activeRoles() 抛 "Undefined column expires_at"
 *     → CheckPermission 中间件 catch 吞异常 → 用户失权 (403)
 *
 * 修复策略:
 *   1. 用原生 SQL (绕过 Schema::hasColumn 的潜在 bug) + IF NOT EXISTS
 *   2. 列已存在时 ADD COLUMN 会被 PG 跳过 (PG 9.6+ 支持 IF NOT EXISTS)
 *   3. 索引用 CREATE INDEX IF NOT EXISTS
 *   4. 幂等: 重复执行不报错
 *
 * 注: User::activeRoles() V1.2.10 已加 fallback, 此 migration 真正生效后 fallback 会被跳过
 */
return new class extends Migration
{
    public function up(): void
    {
        // 表不存在 → spatie 包未发布, 跳过 (User::activeRoles 已有 fallback)
        if (!Schema::hasTable('model_has_roles')) {
            return;
        }

        // 用原生 SQL 加列 — PG 支持 IF NOT EXISTS (9.6+)
        // 绕过 Schema::hasColumn 在某些场景下的潜在问题
        DB::statement('ALTER TABLE model_has_roles ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
        DB::statement('ALTER TABLE model_has_roles ADD COLUMN IF NOT EXISTS granted_by BIGINT NULL');
        DB::statement('ALTER TABLE model_has_roles ADD COLUMN IF NOT EXISTS reason VARCHAR(500) NULL');

        // 部分索引 — 清理任务按 expires_at < now() 扫
        // PG 支持 CREATE INDEX IF NOT EXISTS
        DB::statement('CREATE INDEX IF NOT EXISTS mhr_expires_at_index ON model_has_roles (expires_at) WHERE expires_at IS NOT NULL');

        // 给 granted_by 加外键 (可选, 失败不阻塞)
        try {
            $fkExists = DB::selectOne(
                "SELECT 1 FROM pg_constraint WHERE conname = 'model_has_roles_granted_by_foreign'"
            );
            if (!$fkExists) {
                DB::statement('ALTER TABLE model_has_roles ADD CONSTRAINT model_has_roles_granted_by_foreign FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL');
            }
        } catch (\Throwable $e) {
            // 外键加不上 (users 表不存在/类型不匹配) 不影响主流程
            \Illuminate\Support\Facades\Log::warning('V1.2.10 migration: granted_by 外键未加: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('model_has_roles')) {
            return;
        }
        try {
            DB::statement('ALTER TABLE model_has_roles DROP CONSTRAINT IF EXISTS model_has_roles_granted_by_foreign');
        } catch (\Throwable $e) { /* ignore */ }
        DB::statement('DROP INDEX IF EXISTS mhr_expires_at_index');
        DB::statement('ALTER TABLE model_has_roles DROP COLUMN IF EXISTS reason');
        DB::statement('ALTER TABLE model_has_roles DROP COLUMN IF EXISTS granted_by');
        DB::statement('ALTER TABLE model_has_roles DROP COLUMN IF EXISTS expires_at');
    }
};
