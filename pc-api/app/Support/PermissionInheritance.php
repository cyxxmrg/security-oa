<?php

namespace App\Support;

use Spatie\Permission\Models\Role;

/**
 * V0.5.2 - 角色权限继承工具方法
 *
 * 设计原则:
 *  - 静态配置继承关系 (类似权限矩阵, 写死在代码里, 不上 DB)
 *  - 业务侧"给角色赋权限"时, 自动同步给子角色
 *  - 同步用 union, 不覆盖子角色自己额外加的权限
 *  - 也提供 remove: 父角色移除某权限时, 同步从子角色移除 (可选)
 *
 * 用法:
 *   $role->syncPermissions($perms);
 *   PermissionInheritance::propagateToChildren($role->name, $perms);
 */
class PermissionInheritance
{
    /**
     * 继承图: parent => [child1, child2, ...]
     * 反向遍历 (DFS): 找所有"直接或间接继承自 parent"的子角色
     *
     * 继承链 (与 PermissionRoleSeeder 对齐):
     *   admin   > manager, finance
     *   manager > user
     *   finance > user
     *   user    > (none)
     */
    public static array $graph = [
        'admin'   => ['manager', 'finance'],
        'manager' => ['user'],
        'finance' => ['user'],
        'user'    => [],
    ];

    /**
     * 拿到所有"直接或间接继承自 $parentName"的子角色名
     * 用 BFS 防止循环依赖
     */
    public static function descendants(string $parentName): array
    {
        $out = [];
        $queue = [$parentName];
        $seen = [$parentName => true];
        while ($queue) {
            $current = array_shift($queue);
            $children = self::$graph[$current] ?? [];
            foreach ($children as $child) {
                if (isset($seen[$child])) continue;
                $seen[$child] = true;
                $out[] = $child;
                $queue[] = $child;
            }
        }
        return $out;
    }

    /**
     * 给"parent 角色"赋了 $perms 权限, 自动同步给所有子孙
     * - 取 child 已有权限 union 新 perms, syncPermissions 回去
     * - 这样不丢 child 自己另外加的权限
     */
    public static function propagateToChildren(string $parentName, array $perms): void
    {
        $descendants = self::descendants($parentName);
        if (!$descendants) return;

        foreach ($descendants as $childName) {
            $child = Role::where('name', $childName)->where('guard_name', 'web')->first();
            if (!$child) continue;
            $existing = $child->permissions->pluck('name')->all();
            $merged = array_values(array_unique(array_merge($existing, $perms)));
            sort($merged);
            $child->syncPermissions($merged);
        }
    }

    /**
     * 从"parent 角色"移除 $perms 权限, 同步从子孙移除
     * - 业务: 父角色减权限, 子角色也减
     * - 不影响子孙自己额外加的权限
     */
    public static function revokeFromChildren(string $parentName, array $perms): void
    {
        $descendants = self::descendants($parentName);
        if (!$descendants) return;

        foreach ($descendants as $childName) {
            $child = Role::where('name', $childName)->where('guard_name', 'web')->first();
            if (!$child) continue;
            $existing = $child->permissions->pluck('name')->all();
            $remaining = array_values(array_diff($existing, $perms));
            sort($remaining);
            $child->syncPermissions($remaining);
        }
    }

    /**
     * 返回角色继承图 (前端可视化)
     * GET /api/permissions/inheritance
     */
    public static function getGraph(): array
    {
        $nodes = [];
        $edges = [];
        foreach (self::$graph as $parent => $children) {
            $nodes[$parent] = ['name' => $parent, 'children' => $children];
            foreach ($children as $child) {
                $edges[] = ['parent' => $parent, 'child' => $child];
            }
        }
        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ];
    }
}
