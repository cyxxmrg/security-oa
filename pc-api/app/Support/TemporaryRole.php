<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * V0.5.3 临时角色（Temporary Role）
 *
 * 不破坏 spatie 的 roles() / hasRoleTo / etc 已有契约（保留永久角色兼容性），
 * 只在 model_has_roles 表加 3 列 (expires_at, granted_by, reason) + 一个 helper 类。
 *
 * 用法:
 *   TemporaryRole::grant($user, 'finance', now()->addDays(7), '项目借调', $grantedBy);
 *   TemporaryRole::revoke($user, 'finance', 'project done');
 *   TemporaryRole::expired();   // 清理过期角色（artisan oa:clean-expired-roles）
 */
class TemporaryRole
{
    /**
     * 授予一个用户一个角色（可指定过期时间）
     *
     * @param  User    $user
     * @param  string  $roleName
     * @param  \DateTimeInterface|null  $expiresAt  null = 永久
     * @param  string|null  $reason
     * @param  int|null  $grantedBy  操作人 user.id
     * @return bool  true=新增 false=已是同状态
     */
    public static function grant(
        User $user,
        string $roleName,
        ?\DateTimeInterface $expiresAt = null,
        ?string $reason = null,
        ?int $grantedBy = null
    ): bool {
        $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
        if (!$roleId) {
            throw new \InvalidArgumentException("角色不存在: {$roleName}");
        }

        $existing = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->first();

        $newExpires = $expiresAt ? \Carbon\Carbon::parse($expiresAt)->toDateTimeString() : null;

        if ($existing) {
            // V0.5.3 修: 如果用户已永久持有该角色 (expires_at IS NULL), 不能再授"临时"
            // 避免永久角色被 update 成临时 — 业务上无意义, 应当先 revoke
            if ($existing->expires_at === null) {
                throw new \LogicException(sprintf(
                    '用户「%s」已永久持有角色「%s」, 请先 revoke 再授临时版本',
                    $user->username ?? '#'.$user->id, $roleName
                ));
            }
            // 已有临时 — "续期"
            DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->update([
                    'expires_at' => $newExpires,
                    'granted_by' => $grantedBy,
                    'reason'     => $reason !== null ? mb_substr($reason, 0, 500) : null,
                ]);
            // spatie 内置 cache 失效
            app()['cache']->forget('spatie.permission.cache');
            return false;
        }

        DB::table('model_has_roles')->insert([
            'role_id'    => $roleId,
            'model_type' => User::class,
            'model_id'   => $user->id,
            'expires_at' => $newExpires,
            'granted_by' => $grantedBy,
            'reason'     => $reason !== null ? mb_substr($reason, 0, 500) : null,
        ]);

        // spatie cache 失效（hasRole / hasPermissionTo 走 cache）
        app()['cache']->forget('spatie.permission.cache');

        return true;
    }

    /**
     * 撤销一个用户的一个角色
     * 物理删除 model_has_roles 行（与 spatie removeRole 一致）
     */
    public static function revoke(User $user, string $roleName, ?string $reason = null, ?int $revokedBy = null): bool
    {
        $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
        if (!$roleId) {
            return false;
        }
        $deleted = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->delete();

        if ($deleted) {
            app()['cache']->forget('spatie.permission.cache');
        }
        return (bool) $deleted;
    }

    /**
     * 给一个用户设置一组「临时」角色（替换语义）
     * 不影响永久角色（其它没在 $roles 里指定的永久角色会保留）
     * 实际语义: 移除该用户所有 expires_at IS NOT NULL 的角色，授予新的临时角色
     * —— 这种"只动临时"语义符合"临时授权"的产品定位
     */
    public static function syncTemporary(
        User $user,
        array $temporaryRoles, // [['name' => 'finance', 'expires_at' => '2026-07-01', 'reason' => 'xx'], ...]
        ?int $grantedBy = null
    ): int {
        // 1) 移除所有过期时间非空的
        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->whereNotNull('expires_at')
            ->delete();
        app()['cache']->forget('spatie.permission.cache');

        // 2) 授予新的
        $count = 0;
        foreach ($temporaryRoles as $entry) {
            $name = $entry['name'] ?? null;
            if (!$name) continue;
            $expiresAt = $entry['expires_at'] ?? null;
            $reason = $entry['reason'] ?? null;
            if (self::grant($user, $name, $expiresAt, $reason, $grantedBy)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 清理已过期角色（artisan oa:clean-expired-roles 调用）
     * 返回清理行数
     */
    public static function cleanExpired(): int
    {
        $count = DB::table('model_has_roles')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
        if ($count > 0) {
            app()['cache']->forget('spatie.permission.cache');
        }
        return $count;
    }

    /**
     * 用户的「即将过期」角色（7 天内）
     * 用于 dashboard 提醒
     */
    public static function expiringSoon(int $withinDays = 7): array
    {
        $now = now();
        $deadline = now()->addDays($withinDays);
        return DB::table('model_has_roles')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereNotNull('model_has_roles.expires_at')
            ->where('model_has_roles.expires_at', '>', $now)
            ->where('model_has_roles.expires_at', '<=', $deadline)
            ->select(
                'users.id as user_id', 'users.name', 'users.username',
                'roles.name as role_name',
                'model_has_roles.expires_at', 'model_has_roles.reason', 'model_has_roles.granted_by'
            )
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
