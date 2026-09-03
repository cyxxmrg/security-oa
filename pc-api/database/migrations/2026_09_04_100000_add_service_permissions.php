<?php

use IlluminateDatabaseMigrationsMigration;
use IlluminateSupportFacadesDB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'service.view'     => '维修工单查看',
            'service.create'   => '维修工单创建',
            'service.edit'     => '维修工单编辑',
            'service.delete'   => '维修工单删除',
            'service.dispatch' => '维修工单派单',
            'service.repair'   => '维修工单转返修',
        ];

        foreach ($permissions as $name => $displayName) {
            DB::table('permissions')->insertOrIgnore([
                'name'         => $name,
                'display_name' => $displayName,
                'guard_name'   => 'web',
                'module'       => '售后服务',
                'sort_order'   => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        $managerId = DB::table('roles')
            ->where('name', 'manager')
            ->where('guard_name', 'web')
            ->value('id');
        if (!$managerId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', ['service.view', 'service.create', 'service.dispatch'])
            ->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id'       => $managerId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'service.view', 'service.create', 'service.edit',
                'service.delete', 'service.dispatch', 'service.repair',
            ])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
