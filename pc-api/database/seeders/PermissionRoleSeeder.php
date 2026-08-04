<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * V0.5.0 — 授权中心 Seeder
 *
 * 数据结构:
 *  - 4 个核心角色 (admin/finance/manager/user)  ← AuthScope 直接读
 *  - 51 个细粒度权限 (按 module.action 命名)
 *  - 4 角色默认权限矩阵
 *  - 19 个 demo 用户的 role 绑定
 */
class PermissionRoleSeeder extends Seeder
{
    /**
     * V0.5.0 — 业务权限字典 (module.action 英文, 与已有 51 个 permission 对齐)
     * 前端 role/Index.vue 树用中文 label, 但 DB name 是英文点号
     */
    private array $modules = [
        '系统管理' => [
            ['name' => 'system.config',   'label' => '系统参数配置'],
            ['name' => 'system.log',      'label' => '系统日志查看'],
            ['name' => 'system.backup',   'label' => '数据备份管理'],
            ['name' => 'system.role',     'label' => '角色权限管理'],
        ],
        '员工管理' => [
            ['name' => 'employee.view',   'label' => '员工列表查看'],
            ['name' => 'employee.create', 'label' => '员工信息编辑'],
            ['name' => 'employee.org',    'label' => '组织架构管理'],
            ['name' => 'employee.skill',  'label' => '技能标签管理'],
            // P0-1 安全修复: 用户管理 (改任意用户、reset 密码等高敏操作) 必须有 user.manage 权限
            ['name' => 'user.manage',     'label' => '用户管理（重置密码 / 改 roles）'],
            ['name' => 'employee.onboarding.manage',  'label' => '入职档案管理'],
            ['name' => 'employee.resignation.view',   'label' => '离职档案查看'],
            ['name' => 'employee.resignation.manage', 'label' => '离职申请经办'],
            ['name' => 'employee.resignation.approve','label' => '离职申请审批'],
            ['name' => 'employee.resignation.complete','label' => '离职办结'],
        ],
        '考勤管理' => [
            ['name' => 'attendance.view',    'label' => '考勤总览'],
            ['name' => 'attendance.record',  'label' => '打卡记录查看'],
            ['name' => 'attendance.leave',   'label' => '请假审批'],
            ['name' => 'attendance.overtime','label' => '加班审批'],
            ['name' => 'attendance.report',  'label' => '考勤报表'],
            ['name' => 'schedule.view',       'label' => '排班查看'],
            ['name' => 'schedule.manage',     'label' => '排班管理'],
        ],
        '项目管理' => [
            ['name' => 'project.view',        'label' => '项目列表查看'],
            ['name' => 'project.view.own',    'label' => '仅查看我负责的项目'],
            ['name' => 'project.create',      'label' => '项目创建编辑'],
            ['name' => 'project.assign',      'label' => '任务分配管理'],
            ['name' => 'project.report',      'label' => '项目报表'],
            ['name' => 'project.report.own',  'label' => '仅看我负责的项目报表'],
            ['name' => 'construction.budget.view',    'label' => '施工预算查看'],
            ['name' => 'construction.budget.manage',  'label' => '施工预算编制'],
            ['name' => 'construction.budget.approve', 'label' => '施工预算审批'],
        ],
        '客户管理' => [
            ['name' => 'customer.view',  'label' => '客户列表查看'],
            ['name' => 'customer.edit',  'label' => '客户信息编辑'],
            ['name' => 'customer.map',   'label' => '客户分布地图'],
        ],
        '财务管理' => [
            ['name' => 'finance.view',     'label' => '财务概览'],
            ['name' => 'finance.receive',  'label' => '应收账款'],
            ['name' => 'finance.pay',      'label' => '应付账款'],
            ['name' => 'finance.approve',  'label' => '报销审批'],
            ['name' => 'warranty.deposit.view',   'label' => '质保金查看'],
            ['name' => 'warranty.deposit.manage', 'label' => '质保金收退与没收'],
            ['name' => 'analytics.view',           'label' => '经营分析查看'],
        ],
        '库存管理' => [
            ['name' => 'inventory.view',     'label' => '库存总览'],
            ['name' => 'inventory.create',   'label' => '库存物品创建'],
            ['name' => 'inventory.transfer', 'label' => '出入库记录'],
            ['name' => 'inventory.alert',    'label' => '库存预警设置'],
        ],
        '审批流程' => [
            ['name' => 'approval.template', 'label' => '流程模板管理'],
            ['name' => 'approval.mine',     'label' => '我的审批'],
            ['name' => 'approval.config',   'label' => '审批配置'],
        ],
        // V0.6.5 Sprint 4: 招标中心权限
        '招标中心' => [
            ['name' => 'tender.view',     'label' => '招标项目查看'],
            ['name' => 'tender.create',   'label' => '招标项目创建编辑'],
            ['name' => 'tender.submit',   'label' => '提交审核'],
            ['name' => 'tender.approve',  'label' => '招标审核/驳回'],
            ['name' => 'tender.withdraw', 'label' => '招标撤回'],
            ['name' => 'tender.cancel',   'label' => '招标废标'],
            ['name' => 'tender.award',    'label' => '招标定标'],
            ['name' => 'deposit.manage',  'label' => '保证金收退/没收'],
        ],
        // V1.0 公司网盘权限
        '网盘管理' => [
            ['name' => 'disk.view',   'label' => '网盘查看'],
            ['name' => 'disk.create', 'label' => '网盘创建'],
            ['name' => 'disk.edit',   'label' => '网盘编辑'],
            ['name' => 'disk.upload', 'label' => '网盘上传/编辑'],
            ['name' => 'disk.manage', 'label' => '网盘管理（建/删共享目录）'],
        ],
        // V1.2.12: 销售管理权限
        '销售管理' => [
            ['name' => 'sales.create', 'label' => '销售线索/商机创建'],
            ['name' => 'sales.edit',   'label' => '销售线索/商机编辑'],
        ],
        // V1.2.12: 报销管理权限
        '报销管理' => [
            ['name' => 'expense.create',   'label' => '报销单创建/编辑'],
            ['name' => 'expense.edit',     'label' => '报销单编辑'],
            ['name' => 'expense.approve',  'label' => '报销审批/付款'],
        ],
        // V1.2.12: 车辆管理权限
        '车辆管理' => [
            ['name' => 'vehicle.create', 'label' => '车辆信息创建编辑'],
            ['name' => 'vehicle.edit',   'label' => '车辆信息编辑'],
        ],
        // V1.2.12: 深化施工权限
        '深化施工' => [
            ['name' => 'process.create',  'label' => '工序/验收创建'],
            ['name' => 'process.edit',    'label' => '工序/验收编辑'],
            ['name' => 'process.approve', 'label' => '工序验收审核'],
        ],
        // V1.2.12: 知识库权限
        '知识库' => [
            ['name' => 'knowledge.create', 'label' => '知识库创建编辑'],
            ['name' => 'knowledge.edit',   'label' => '知识库编辑'],
        ],
    ];

    public function run(): void
    {
        // 1) 清空 + 重建 permissions（保留 roles / model_has_* 关系）
        Permission::query()->delete();

        // 2) 写权限 (英文 name + 中文 label + display_name)
        $allPerms = [];
        foreach ($this->modules as $mod => $perms) {
            foreach ($perms as $p) {
                $perm = Permission::create([
                    'name'         => $p['name'],
                    'guard_name'   => 'web',
                    'module'       => $mod,
                    'description'  => $p['label'],
                    'display_name' => $p['label'],
                ]);
                $allPerms[] = $perm->name;
            }
        }

        // 3) 4 核心角色 + 默认权限矩阵
        $presets = [
            'admin' => [
                'description' => '系统最高权限，所有模块',
                'color' => '#A32D2D',
                'perms' => $allPerms, // 全部
            ],
            'finance' => [
                'description' => '财务模块 + 全局查看',
                'color' => '#534AB7',
                'perms' => array_values(array_filter($allPerms, fn($n) =>
                    str_starts_with($n, 'finance.') || str_starts_with($n, 'approval.')
                )),
            ],
            'user' => [
                'description' => '普通员工：考勤+个人',
                'color' => '#909399',
                // user 是基础角色 - 给自己独有的 attendance.* + approval.mine
                // manager/finance/admin 通过继承链自动获得 user 权限
                // V1.0: 加 disk.view (所有员工能看网盘)
                // V1.0.2: 加 inventory.view (所有员工能查看库存)
                'perms' => array_values(array_filter($allPerms, fn($n) =>
                    in_array($n, ['attendance.view', 'attendance.record', 'schedule.view'], true) ||
                    str_starts_with($n, 'approval.mine') ||
                    $n === 'disk.view' ||
                    $n === 'inventory.view'
                )),
            ],
            'manager' => [
                'description' => '项目经理/部门经理（继承 user）',
                'color' => '#0C447C',
                // manager 自己独有的: project.* / employee.* / customer.* / approval.template
                // V0.6.5: 加 tender.view/create/submit/withdraw/cancel/award
                //         (approve 走 admin/finance; deposit.manage 走 finance)
                // V1.0: 加 disk.upload (manager 可以上传/编辑)
                // V1.0.2: 加 inventory.create (manager 可以创建库存物品)
                // V1.2.3: 加 customer.* (manager 负责客户跟进, 必须能增删改查)
                // V1.2.12: 加 sales.* / vehicle.* / process.* / knowledge.* / expense.create|expense.edit + disk.create|disk.edit
                // attendance.* / approval.mine / disk.view / inventory.view 通过继承 user 自动获得
                'perms' => array_values(array_filter($allPerms, fn($n) =>
                    str_starts_with($n, 'project.') ||
                    str_starts_with($n, 'employee.') && $n !== 'employee.resignation.complete' ||
                    in_array($n, ['attendance.leave', 'attendance.overtime', 'attendance.report'], true) ||
                    $n === 'schedule.manage' ||
                    str_starts_with($n, 'construction.budget.') && $n !== 'construction.budget.approve' ||
                    $n === 'analytics.view' ||
                    str_starts_with($n, 'customer.') ||
                    str_starts_with($n, 'sales.') ||
                    str_starts_with($n, 'vehicle.') ||
                    str_starts_with($n, 'process.') ||
                    str_starts_with($n, 'knowledge.') ||
                    str_starts_with($n, 'expense.') && $n !== 'expense.approve' ||
                    $n === 'approval.template' ||
                    in_array($n, ['disk.create', 'disk.edit', 'disk.upload'], true) ||
                    $n === 'inventory.create' ||
                    in_array($n, ['tender.view', 'tender.create', 'tender.submit', 'tender.withdraw', 'tender.cancel', 'tender.award'], true)
                )),
            ],
            'finance' => [
                'description' => '财务（继承 user）',
                'color' => '#534AB7',
                // finance 自己独有的: finance.* + 保证金 + 招标审核 (财务部主导) + 报销审批
                // V1.2.12: 加 expense.approve
                'perms' => array_values(array_filter($allPerms, fn($n) =>
                    str_starts_with($n, 'finance.') ||
                    $n === 'deposit.manage' ||
                    str_starts_with($n, 'warranty.deposit.') ||
                    in_array($n, ['construction.budget.view', 'construction.budget.approve', 'employee.resignation.view', 'analytics.view', 'tender.view'], true) ||
                    $n === 'tender.approve' ||
                    $n === 'expense.approve'
                )),
            ],
            'admin' => [
                'description' => '系统最高权限（继承 manager+finance）',
                'color' => '#A32D2D',
                // admin 自己独有的: system.* / approval.config / disk.manage / user.manage
                // 通过继承链: project.* / employee.* / tender.* / finance.* / deposit.* / disk.* 都自动有
                // P0-1: user.manage 是高敏权限, 仅 admin (system 账号) 可改任意 user / role / 重置密码
                'perms' => array_values(array_filter($allPerms, fn($n) =>
                    str_starts_with($n, 'system.') ||
                    $n === 'approval.config' ||
                    $n === 'disk.manage' ||
                    $n === 'user.manage'
                )),
            ],
        ];

        // V0.5.1 继承链 (spatie Role 继承模型):
        //   admin    > manager, finance  (admin 自动有 manager+finance 的所有权限)
        //   manager  > user
        //   finance  > user
        //   user     > (无)
        // 这样:
        //   - 给 user 加新权限, manager/finance/admin 自动有
        //   - 给 admin 加权限不会污染低层 (admin 自身已有)
        $inheritMap = [
            'manager' => 'user',
            'finance' => 'user',
            'admin'   => 'manager',  // 间接继承 user, finance 通过 manager -> user
        ];
        // admin 也直接继承 finance
        $adminInheritsExtra = ['finance'];

        foreach ($presets as $name => $cfg) {
            $role = Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $cfg['description'], 'color' => $cfg['color']]
            );
            $role->syncPermissions($cfg['perms']);
        }

        // 配置继承关系 (用 spatie 内部表 role_has_permissions 反向; 这里用 model 层方法)
        $managerRole = Role::where('name', 'manager')->where('guard_name', 'web')->first();
        $financeRole = Role::where('name', 'finance')->where('guard_name', 'web')->first();
        $adminRole   = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $userRole    = Role::where('name', 'user')->where('guard_name', 'web')->first();

        if ($managerRole && $userRole) {
            // 把 manager 没有的 user 权限也写到 role_has_permissions
            $userPerms = $userRole->permissions()->pluck('name')->all();
            $managerPerms = $managerRole->permissions()->pluck('name')->all();
            $missing = array_diff($userPerms, $managerPerms);
            if ($missing) {
                $managerRole->givePermissionTo($missing);
            }
        }
        if ($financeRole && $userRole) {
            $userPerms = $userRole->permissions()->pluck('name')->all();
            $financePerms = $financeRole->permissions()->pluck('name')->all();
            $missing = array_diff($userPerms, $financePerms);
            if ($missing) {
                $financeRole->givePermissionTo($missing);
            }
        }
        if ($adminRole && $managerRole && $financeRole) {
            // admin 继承 manager + finance 的所有权限
            $mgrPerms = $managerRole->permissions()->pluck('name')->all();
            $finPerms = $financeRole->permissions()->pluck('name')->all();
            $adminPerms = $adminRole->permissions()->pluck('name')->all();
            $missing = array_diff(array_unique(array_merge($mgrPerms, $finPerms)), $adminPerms);
            if ($missing) {
                $adminRole->givePermissionTo($missing);
            }
        }

        // 4) 演示用户绑定 (覆盖清理已有绑定, 一对一)
        // V1.2.3: system 单独不挂任何业务角色 (走 is_system=1 中间件路线)
        //        administrator 用 admin 角色 (走 spatie)
        //        wizard 建好的 admin 账号会通过 wizard 写入, 此处只兜底
        $bindings = [
            'manager'   => 'manager',
            'user'      => 'user',
            'zhaodc'    => 'manager',
            'chenjing'  => 'finance',
        ];

        foreach ($bindings as $username => $roleName) {
            $user = \App\Models\User::where('username', $username)->first();
            if ($user) {
                // syncRoles 会清掉所有旧绑定, 然后只挂这一个
                $user->syncRoles([$roleName]);
            }
        }

        // 5) 清理: admin1/admin 双绑定 (上面 syncRoles 已清, 此处防万一)
        \Spatie\Permission\Models\Role::where('name', 'UI测试-角色名称')->delete();

        // 6) V1.0.2: 清 spatie 权限缓存, 确保变更立即生效
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
