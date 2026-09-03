<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name'         => 'process.view',
            'display_name' => '工序/验收查看',
            'guard_name'   => 'web',
            'module'       => '深化施工',
            'sort_order'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'process.view')
            ->where('guard_name', 'web')
            ->value('id');
        if (!$permissionId) {
            return;
        }

        $managerId = DB::table('roles')
            ->where('name', 'manager')
            ->where('guard_name', 'web')
            ->value('id');
        if ($managerId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id'       => $managerId,
            ]);
        }
    }

    public function down(): void
    {
        // 权限可能在迁移前已存在，无法安全区分后续新增数据，因此不自动删除。
    }
};
