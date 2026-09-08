<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 安全审计修复 (V1.4.3, 对应 REVIEW_security-audit.md P1-3):
 * 新增油卡管理写操作权限点 vehicle.fuel.edit
 *
 * 背景:
 *  - routes/api/finance.php fuel-cards 组原先整组仅挂 vehicle.fuel (读写同权):
 *    能查看油卡余额/充值流水者即可新增/编辑/删除油卡与充值流水 (财务敏感)。
 *  - 路由已拆分为: 读 = vehicle.fuel, 写 = vehicle.fuel.edit。
 *
 * 方案: 幂等补建权限点(存在即跳过); 不做角色预设授权, 由权限矩阵 UI 按需授予。
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('permissions')
            ->where('name', 'vehicle.fuel.edit')
            ->where('guard_name', 'web')
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('permissions')->insert([
            'name'         => 'vehicle.fuel.edit',
            'display_name' => '油卡管理操作',
            'guard_name'   => 'web',
            'module'       => 'vehicle',
            'sort_order'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'vehicle.fuel.edit')
            ->delete();
    }
};
