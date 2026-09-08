<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 安全审计修复 (V1.4.3, 对应 REVIEW_security-audit.md P1):
 * 新增质保金资金释放/没收权限点 deposit.release
 *
 * 背景:
 *  - warranty-deposits 组原先组级仅 deposit.manage, 新增/释放/没收(资金流出)同权;
 *  - 拆出 deposit.release(部分/全额释放、没收 = 资金动作), 组级 deposit.manage 保留读与建档;
 *  - 路由: 释放/没收动作叠加 permission:deposit.release (AND 语义)。
 *
 * 方案: 幂等补建权限点(存在即跳过); 角色绑定由部署脚本把当前持有 deposit.manage 的角色同步授予。
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('permissions')
            ->where('name', 'deposit.release')
            ->where('guard_name', 'web')
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('permissions')->insert([
            'name'         => 'deposit.release',
            'display_name' => '质保金释放/没收',
            'guard_name'   => 'web',
            'module'       => 'finance',
            'sort_order'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'deposit.release')
            ->delete();
    }
};
