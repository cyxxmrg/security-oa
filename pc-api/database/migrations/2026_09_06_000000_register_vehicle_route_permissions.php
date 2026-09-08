<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 安全审计补充修复 (V1.4.3, 对应 REVIEW_security-audit.md 第七节):
 * 补建路由层缺失的 vehicle.create / vehicle.edit 权限点
 *
 * 背景:
 *  - routes/api/finance.php 车辆写路由使用 permission:vehicle.create (store) 与
 *    permission:vehicle.edit (update/destroy), 但这两个点只存在于 PermissionRoleSeeder
 *    (全新安装路径), 缺少增量注册迁移 → 存量部署经 migrate 升级后权限表无此两点,
 *    CheckPermission 判定"权限未定义"直接 403, 车辆新增/编辑/删除对非 admin 静默不可用。
 *
 * 方案: 幂等补建权限点(存在即跳过), 跨环境安全; 不做角色预设授权,
 *       由权限矩阵 UI 按需授予 (与 admin 角色旁路语义一致)。
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $defs = [
            'vehicle.create' => ['车辆信息创建编辑', 'vehicle'],
            'vehicle.edit'   => ['车辆信息编辑',     'vehicle'],
        ];
        $rows = [];
        foreach ($defs as $name => [$displayName, $module]) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
            if (!$exists) {
                $rows[] = [
                    'name'         => $name,
                    'display_name' => $displayName,
                    'guard_name'   => 'web',
                    'module'       => $module,
                    'sort_order'   => 0,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }
        if (!empty($rows)) {
            DB::table('permissions')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', ['vehicle.create', 'vehicle.edit'])
            ->delete();
    }
};
