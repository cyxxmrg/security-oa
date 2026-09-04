<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $definitions = [
            'purchase.requirement' => '采购需求',
            'purchase.order' => '采购计划与订单',
            'purchase.detail' => '采购合同、付款与收货',
            'purchase.supplier' => '供应商库',
            'purchase.portal' => '门户管理',
        ];

        foreach ($definitions as $name => $displayName) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'display_name' => $displayName,
                'guard_name' => 'web',
                'module' => 'purchase',
                'description' => $displayName,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys($definitions))
            ->pluck('id', 'name');
        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['manager', 'finance', 'admin'])
            ->pluck('id');

        $rows = [];
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ];
            }
        }
        if ($rows !== []) {
            DB::table('role_has_permissions')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
    }
};
