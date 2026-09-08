<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        ['employee.onboarding.manage', 'employee', '入职档案管理'],
        ['employee.resignation.view', 'employee', '离职档案查看'],
        ['employee.resignation.manage', 'employee', '离职申请经办'],
        ['employee.resignation.approve', 'employee', '离职申请审批'],
        ['employee.resignation.complete', 'employee', '离职办结'],
        ['schedule.view', 'attendance', '排班查看'],
        ['schedule.manage', 'attendance', '排班管理'],
        ['construction.budget.view', 'construction', '施工预算查看'],
        ['construction.budget.manage', 'construction', '施工预算编制'],
        ['construction.budget.approve', 'construction', '施工预算审批'],
        ['warranty.deposit.view', 'finance', '质保金查看'],
        ['warranty.deposit.manage', 'finance', '质保金收退与没收'],
        ['analytics.view', 'finance', '经营分析查看'],
    ];

    private array $revokedFromUser = [
        'attendance.leave', 'attendance.overtime', 'attendance.report',
    ];

    public function up(): void
    {
        foreach ($this->permissions as [$name, $module, $label]) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'module' => $module,
                'description' => $label,
                'display_name' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('permissions')
                ->where(['name' => $name, 'guard_name' => 'web'])
                ->update([
                    'module' => $module,
                    'description' => $label,
                    'display_name' => $label,
                    'updated_at' => now(),
                ]);
        }

        $grants = [
            'user' => ['schedule.view'],
            'manager' => [
                'schedule.view', 'schedule.manage',
                'attendance.leave', 'attendance.overtime', 'attendance.report',
                'employee.onboarding.manage', 'employee.resignation.view',
                'employee.resignation.manage', 'employee.resignation.approve',
                'construction.budget.view', 'construction.budget.manage', 'analytics.view',
                'tender.view', 'tender.create', 'tender.submit', 'tender.withdraw',
                'tender.cancel', 'tender.award',
            ],
            'finance' => [
                'schedule.view', 'employee.resignation.view',
                'construction.budget.view', 'construction.budget.approve',
                'warranty.deposit.view', 'warranty.deposit.manage', 'analytics.view',
                'tender.view', 'tender.approve', 'deposit.manage',
            ],
            'admin' => array_merge(array_column($this->permissions, 0), [
                'tender.view', 'tender.create', 'tender.submit', 'tender.approve',
                'tender.withdraw', 'tender.cancel', 'tender.award', 'deposit.manage',
            ]),
        ];

        foreach ($grants as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
            if (!$roleId) {
                continue;
            }
            $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $userRoleId = DB::table('roles')->where('name', 'user')->where('guard_name', 'web')->value('id');
        if ($userRoleId) {
            $revokedIds = DB::table('permissions')->whereIn('name', $this->revokedFromUser)->pluck('id');
            DB::table('role_has_permissions')
                ->where('role_id', $userRoleId)
                ->whereIn('permission_id', $revokedIds)
                ->delete();
        }
    }

    public function down(): void
    {
        $userRoleId = DB::table('roles')->where('name', 'user')->where('guard_name', 'web')->value('id');
        if ($userRoleId) {
            $permissionIds = DB::table('permissions')->whereIn('name', $this->revokedFromUser)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $userRoleId,
                ]);
            }
        }
        DB::table('permissions')->whereIn('name', array_column($this->permissions, 0))->delete();
    }
};
