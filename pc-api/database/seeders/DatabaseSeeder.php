<?php

namespace Database\Seeders;

use App\Support\TemporaryPassword;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PermissionRoleSeeder::class,   // V1.0.2: spatie 权限矩阵 (project.view/inventory.view 等)
            DepartmentSeeder::class,
            UserSeeder::class,
            SkillTagSeeder::class,
            CustomerSeeder::class,
        ]);
    }
}

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 创建角色
        $roles = [
            ['name' => 'admin', 'description' => '超级管理员', 'is_system' => true],
            ['name' => 'manager', 'description' => '项目经理', 'is_system' => true],
            ['name' => 'finance', 'description' => '财务人员', 'is_system' => true],
            ['name' => 'user', 'description' => '普通员工', 'is_system' => true],
        ];
        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore(array_merge($role, ['guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]));
        }

        // 创建权限（按模块分组）
        $modules = [
            'dashboard' => ['view'],
            'attendance' => ['view', 'manage', 'report', 'approve'],
            'employee' => ['view', 'create', 'edit', 'delete'],
            'customer' => ['view', 'create', 'edit', 'delete'],
            'project' => ['view', 'create', 'edit', 'delete', 'manage_budget', 'manage_construction'],
            'service' => ['view', 'create', 'edit', 'delete', 'dispatch', 'repair'],
            'expense' => ['view', 'create', 'approve', 'pay'],
            'vehicle' => ['view', 'create', 'approve', 'dispatch'],
            'inventory' => ['view', 'manage', 'in', 'out'],
            'finance' => ['view', 'manage', 'approve'],
            'disk' => ['view', 'upload', 'manage'],
            'knowledge' => ['view', 'create', 'edit', 'delete'],
            'system' => ['settings', 'role', 'log', 'backup'],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                DB::table('permissions')->insertOrIgnore([
                    'name' => "$module.$action",
                    'display_name' => "$module $action",
                    'guard_name' => 'web',
                    'module' => $module,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 分配权限：admin 全部，manager 业务模块，user 基础查看
        $allPermissions = DB::table('permissions')->pluck('id')->toArray();
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        foreach ($allPermissions as $permId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permId, 'role_id' => $adminRole->id]);
        }

        $managerPerms = DB::table('permissions')->whereIn('name', array_merge(
            array_map(fn($a) => "dashboard.$a", ['view']),
            array_map(fn($a) => "attendance.$a", ['view']),
            array_map(fn($a) => "employee.$a", ['view']),
            array_map(fn($a) => "customer.$a", ['view', 'create', 'edit']),
            array_map(fn($a) => "project.$a", ['view', 'create', 'edit', 'manage_construction']),
            array_map(fn($a) => "service.$a", ['view', 'create', 'dispatch']),
            array_map(fn($a) => "expense.$a", ['view', 'create']),
            array_map(fn($a) => "vehicle.$a", ['view', 'create']),
        ))->pluck('id')->toArray();
        $managerRole = DB::table('roles')->where('name', 'manager')->first();
        foreach ($managerPerms as $permId) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permId, 'role_id' => $managerRole->id]);
        }
    }
}

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => '总经办', 'sort_order' => 0],
            ['name' => '技术部', 'sort_order' => 1],
            ['name' => '销售部', 'sort_order' => 2],
            ['name' => '售后部', 'sort_order' => 3],
            ['name' => '财务部', 'sort_order' => 4],
            ['name' => '行政部', 'sort_order' => 5],
        ];
        foreach ($departments as $dept) {
            DB::table('departments')->insertOrIgnore(array_merge($dept, ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]));
        }
    }
}

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 部门 id 可能在多次 seed 后自增, 用 name 反查
        $deptMap = DB::table('departments')->pluck('id', 'name');
        $configuredPassword = env('SEED_DEFAULT_PASSWORD');
        if ($configuredPassword && !preg_match('/^(?=.*[A-Za-z])(?=.*\d).{12,64}$/', $configuredPassword)) {
            throw new \RuntimeException('SEED_DEFAULT_PASSWORD 必须为 12-64 位且同时包含字母和数字');
        }
        $users = [
            // V1.2: system 账号替代 admin — 首次登录必须改密 (must_change_password=true)
            ['name' => '系统管理员', 'username' => 'system', 'email' => 'system@local', 'phone' => '13800000000', 'password' => $configuredPassword ?: TemporaryPassword::generate(), 'department' => '总经办', 'status' => 'active', 'gender' => 'male', 'is_system' => true, 'user_type' => 'system', 'must_change_password' => true],
            // V1.1: manager/user/finance/zhaodc 全是业务用户
            ['name' => '李明辉', 'username' => 'manager', 'email' => 'manager@security-oa.com', 'phone' => '13900139001', 'password' => $configuredPassword ?: TemporaryPassword::generate(), 'department' => '技术部', 'status' => 'active', 'gender' => 'male', 'is_system' => false, 'user_type' => 'business', 'must_change_password' => true],
            ['name' => '王小红', 'username' => 'user', 'email' => 'user@security-oa.com', 'phone' => '13700137002', 'password' => $configuredPassword ?: TemporaryPassword::generate(), 'department' => '销售部', 'status' => 'active', 'gender' => 'female', 'is_system' => false, 'user_type' => 'business', 'must_change_password' => true],
            ['name' => '赵大成', 'username' => 'zhaodc', 'email' => 'zhaodc@security-oa.com', 'phone' => '13600136003', 'password' => $configuredPassword ?: TemporaryPassword::generate(), 'department' => '技术部', 'status' => 'active', 'gender' => 'male', 'is_system' => false, 'user_type' => 'business', 'must_change_password' => true],
            ['name' => '陈静', 'username' => 'chenjing', 'email' => 'chenjing@security-oa.com', 'phone' => '13500135004', 'password' => $configuredPassword ?: TemporaryPassword::generate(), 'department' => '财务部', 'status' => 'active', 'gender' => 'female', 'is_system' => false, 'user_type' => 'business', 'must_change_password' => true],
        ];
        foreach ($users as $u) {
            $dept = $u['department']; unset($u['department']);
            $plainPassword = $u['password'];
            $u['password'] = Hash::make($plainPassword);
            $u['department_id'] = $deptMap[$dept] ?? null;
            $inserted = DB::table('users')->insertOrIgnore(array_merge($u, ['created_at' => now(), 'updated_at' => now()]));
            if ($inserted > 0) {
                $this->command?->warn("初始账号 {$u['username']} 的一次性密码: {$plainPassword}");
            }
        }

        // 分配角色 (按用户名查 role_id, 不写死 1/2/3/4)
        $roleMap = DB::table('roles')->pluck('id', 'name');
        $userMap = DB::table('users')->pluck('id', 'username');
        $userRole = [
            'system'   => 'admin',     // V1.2: system 账号拿 admin spatie 角色 (系统管理权限)
            'manager'  => 'manager',
            'user'     => 'user',
            'zhaodc'   => 'manager',
            'chenjing' => 'finance',
        ];
        $inserts = [];
        foreach ($userRole as $uname => $rname) {
            if (isset($userMap[$uname]) && isset($roleMap[$rname])) {
                $inserts[] = [
                    'role_id'    => $roleMap[$rname],
                    'model_type' => 'App\\Models\\User',
                    'model_id'   => $userMap[$uname],
                ];
            }
        }
        if ($inserts) {
            DB::table('model_has_roles')->insertOrIgnore($inserts);
        }
    }
}

class SkillTagSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            ['name' => '监控安装', 'category' => 'install', 'color' => '#409EFF'],
            ['name' => '门禁调试', 'category' => 'debug', 'color' => '#67C23A'],
            ['name' => '网络配置', 'category' => 'network', 'color' => '#E6A23C'],
            ['name' => '报警系统', 'category' => 'maintain', 'color' => '#F56C6C'],
            ['name' => '云平台部署', 'category' => 'cloud', 'color' => '#909399'],
            ['name' => '弱电施工', 'category' => 'install', 'color' => '#B37FEB'],
            ['name' => '综合布线', 'category' => 'install', 'color' => '#00C9A7'],
            ['name' => 'CAD设计', 'category' => 'other', 'color' => '#FF6B6B'],
        ];
        foreach ($skills as $skill) {
            DB::table('skill_tags')->insertOrIgnore(array_merge($skill, ['created_at' => now(), 'updated_at' => now()]));
        }
    }
}

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['name' => '阳光小学', 'category' => 'normal', 'industry' => '教育', 'province' => '广东', 'city' => '深圳', 'district' => '南山区', 'address' => '南山区学府路88号', 'tags' => json_encode(['学校'])],
            ['name' => '中心医院', 'category' => 'vip', 'industry' => '医疗', 'province' => '广东', 'city' => '深圳', 'district' => '福田区', 'address' => '福田区福华路1号', 'tags' => json_encode(['医院'])],
            ['name' => '科技园区A区', 'category' => 'vip', 'industry' => '园区', 'province' => '广东', 'city' => '深圳', 'district' => '宝安区', 'address' => '宝安区科技园路200号', 'tags' => json_encode(['园区'])],
            ['name' => '万达工厂', 'category' => 'normal', 'industry' => '工厂', 'province' => '广东', 'city' => '东莞', 'district' => '长安镇', 'address' => '长安镇工业大道50号', 'tags' => json_encode(['工厂'])],
            ['name' => '龙城商场', 'category' => 'potential', 'industry' => '商业', 'province' => '广东', 'city' => '深圳', 'district' => '龙岗区', 'address' => '龙岗区龙翔大道1号', 'tags' => json_encode(['商场'])],
        ];
        foreach ($customers as $cust) {
            DB::table('customers')->insertOrIgnore(array_merge($cust, ['status' => 'active', 'assigned_user_id' => 2, 'created_at' => now(), 'updated_at' => now()]));
        }
    }
}
