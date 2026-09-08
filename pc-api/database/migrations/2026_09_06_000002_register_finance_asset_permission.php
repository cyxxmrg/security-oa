<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 安全审计修复 (V1.4.3, 对应 REVIEW_security-audit.md P1):
 * 新增固定资产管理写操作权限点 finance.asset
 *
 * 背景:
 *  - routes/api/finance.php 固定资产(分类/折旧/盘点/处置/调拨/维护/建档)
 *    全部写路由复用 permission:finance.pay(付款权限) → 权限点语义混用,
 *    被授"付款"权限者即可处置固定资产。
 *  - 拆出独立点 finance.asset; finance/admin 角色按字典前缀自动纳入, 授权面不变;
 *    仅"只授付款权"的受限角色不再自动获得资产处置权。
 *
 * 方案: 幂等补建权限点(存在即跳过)。
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('permissions')
            ->where('name', 'finance.asset')
            ->where('guard_name', 'web')
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('permissions')->insert([
            'name'         => 'finance.asset',
            'display_name' => '固定资产管理',
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
            ->where('name', 'finance.asset')
            ->delete();
    }
};
