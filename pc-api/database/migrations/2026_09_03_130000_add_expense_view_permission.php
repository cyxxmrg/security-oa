<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name'         => 'expense.view',
            'display_name' => '报销单查看',
            'guard_name'   => 'web',
            'module'       => '报销管理',
            'sort_order'   => 0,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $permissionId = DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'expense.view')
            ->value('id');
        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['user', 'manager', 'finance', 'admin'])
            ->pluck('id');

        if ($permissionId) {
            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id'       => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // 权限可能在迁移前已存在，无法安全区分后续新增数据，因此不自动删除。
    }
};
