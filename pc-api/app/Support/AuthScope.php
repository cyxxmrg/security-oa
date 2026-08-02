<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 鉴权上下文 (V0.5.0 — 替换 username 前缀方案)
 *
 * 角色判定改为**读 spatie model_has_roles** (DB 单一真相源):
 *   - 用户绑定到 spatie roles 表的 name 字段
 *   - 角色常量保持向后兼容, 但 classify() 改走 user->roles
 *   - admin/finance 仍然放行所有数据
 *
 * V0.4.6 → V0.5.0 迁移说明:
 *   - 老 username 前缀逻辑 (admin*, fin_, sales_, tech_mgr, proj_mgr) 保留为 fallback
 *   - 优先用 spatie role name 匹配: admin / finance / manager / user
 *   - seeder 已为 19 个 demo 用户建好 model_has_roles (V0.3.14)
 */
class AuthScope
{
    public const ROLE_ADMIN    = 'admin';
    public const ROLE_FINANCE  = 'finance';
    public const ROLE_MANAGER  = 'manager';
    public const ROLE_USER     = 'user';

    /** 全量通过的角色 (admin/finance 直接放行) */
    public const UNRESTRICTED_ROLES = [self::ROLE_ADMIN, self::ROLE_FINANCE];

    /**
     * V0.5.0: 把 user 映射到角色 — 优先 spatie, fallback username 前缀
     *
     * V0.5.3 临时权限: 改用 activeRoles()（过滤过期）替代 roles，
     * 避免一个被授了临时 admin 的人在过期后还享受 admin 待遇。
     *
     * @param  \App\Models\User|null  $user
     */
    public static function classify($user): string
    {
        if (!$user) return self::ROLE_USER;

        // 1) 优先: spatie 有效角色 (V0.5.3 加过期过滤)
        try {
            $roleNames = $user->activeRoles()->pluck('roles.name')->all();
            foreach ($roleNames as $r) {
                $r = (string) $r;
                if (in_array($r, self::UNRESTRICTED_ROLES, true)) {
                    return $r; // admin/finance 直接
                }
            }
            if (in_array(self::ROLE_MANAGER, $roleNames, true)) return self::ROLE_MANAGER;
            if (in_array(self::ROLE_USER, $roleNames, true))    return self::ROLE_USER;
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // spatie 关系未就绪时继续 fallback
        }

        // 2) fallback: 老 username 前缀逻辑 (向后兼容旧测试/seed)
        $username = (string) ($user->username ?? '');
        if ($username === 'admin' || str_starts_with($username, 'admin')) {
            return self::ROLE_ADMIN;
        }
        if (str_starts_with($username, 'fin_')) {
            return self::ROLE_FINANCE;
        }
        if (str_starts_with($username, 'sales_') || in_array($username, ['tech_mgr','proj_mgr','sales_mgr'], true)) {
            return self::ROLE_MANAGER;
        }
        return self::ROLE_USER;
    }

    public static function isAdmin($user): bool   { return self::classify($user) === self::ROLE_ADMIN; }
    public static function isFinance($user): bool { return self::classify($user) === self::ROLE_FINANCE; }
    public static function isManager($user): bool { return self::classify($user) === self::ROLE_MANAGER; }
    public static function isUnrestricted($user): bool {
        return in_array(self::classify($user), self::UNRESTRICTED_ROLES, true);
    }

    /**
     * 拼 "我作为创建者/负责/参与" 的可访问项目 id 列表
     * 用于"通过 project_id 关联的表"(warranties/rectifications/...)的 scope
     *
     * @param string $outerTable  外层表名(如 warranties, rectifications)
     * @return string 拼好的 SQL 整串, 形如:
     *   (EXISTS (SELECT 1 FROM projects p WHERE p.id = warranties.project_id AND (...)))
     */
    public static function myProjectsByProjectIdSubquery(int $userId, string $outerTable): string
    {
        return sprintf(
            "(EXISTS (SELECT 1 FROM projects p WHERE p.id = %s.project_id AND (p.manager_id = %d OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.status = 'active'))))",
            $outerTable, $userId, $userId
        );
    }
}
