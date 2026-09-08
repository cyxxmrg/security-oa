<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * V1.4.3 安全审计 — 清理 RoleGuardrailApiTest 遗留的临时角色
 *
 * 背景:
 *   tests/Feature/RoleGuardrailApiTest.php 会创建 guardrail_* 前缀的临时角色
 *   (roles 路由表无 DELETE 端点, 测试内无法清理), 每次运行后残留 1-2 个。
 *   本命令一键清理 guardrail_* 角色及其 role_has_permissions / model_has_roles 关联。
 *
 * 用法:
 *   php artisan oa:clean-guardrail-roles          # 清理并输出统计
 *   php artisan oa:clean-guardrail-roles --dry-run  # 只预览, 不删除
 */
class CleanGuardrailTempRoles extends Command
{
    protected $signature = 'oa:clean-guardrail-roles {--dry-run : 只预览匹配角色, 不执行删除}';

    protected $description = '清理 RoleGuardrailApiTest 遗留的 guardrail_* 临时角色';

    public function handle(): int
    {
        $ids = DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'like', 'guardrail_%')
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            $this->info('没有 guardrail_* 临时角色, 无需清理。');
            return self::SUCCESS;
        }

        $this->info(sprintf('匹配到 %d 个 guardrail_* 角色: %s', count($ids), implode(', ', $ids)));

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: 未执行删除。');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids) {
            DB::table('role_has_permissions')->whereIn('role_id', $ids)->delete();
            DB::table('permission_role')->whereIn('role_id', $ids)->delete();
            DB::table('model_has_roles')->whereIn('role_id', $ids)->delete();
            DB::table('roles')->whereIn('id', $ids)->delete();
        });

        $this->info(sprintf('已清理 %d 个 guardrail_* 临时角色 (含关联的 role_has_permissions / permission_role / model_has_roles)。', count($ids)));

        return self::SUCCESS;
    }
}
