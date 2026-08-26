<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 安全审计修复 (2026-08-26): 补建路由层缺失的权限点
 *
 * 背景:
 *  - finance 路由组中间件使用 finance.view / finance.pay / finance.receive / finance.approve,
 *    但权限字典(161 项)从未注册这些点 → CheckPermission 判定"权限未定义"直接 403,
 *    导致任何非 admin 角色无法使用财务模块 (之前只用 admin 账号测试未暴露)。
 *  - expenses 路由写操作使用 expense.create|expense.edit, 字典只有 expense.apply → 全员 403。
 *
 * 方案: 幂等补建权限点(insertOrIgnore), 跨环境安全; 角色绑定由部署后脚本单独执行。
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $defs = [
            'finance.view'    => ['财务-查看',   'finance'],
            'finance.pay'     => ['财务-操作',   'finance'],
            'finance.receive' => ['财务-收款',   'finance'],
            'finance.approve' => ['财务-审批',   'finance'],
            'expense.create'  => ['报销-新建',   'expense'],
            'expense.edit'    => ['报销-编辑',   'expense'],
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
            ->whereIn('name', ['finance.view', 'finance.pay', 'finance.receive',
                               'finance.approve', 'expense.create', 'expense.edit'])
            ->delete();
    }
};
