<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\AssignPermissionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    /**
     * 角色列表（分页 + 搜索）
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()->with('permissions:id,name');

        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('description', 'like', "%{$kw}%");
            });
        }

        $perPage = (int) ($request->per_page ?? 20);
        $page = $query->orderBy('id')->paginate($perPage);

        // 注入 memberCount / permCount / createTime / color 给前端表格
        $userCounts = DB::table('model_has_roles')
            ->select('role_id', DB::raw('COUNT(*) as cnt'))
            ->where('model_type', 'App\\Models\\User')
            ->groupBy('role_id')
            ->pluck('cnt', 'role_id');

        $data = collect($page->items())->map(function (Role $role) use ($userCounts) {
            return [
                'id'           => $role->id,
                'name'         => $role->name,
                'display_name' => $this->roleDisplayName($role),
                'description'  => $role->description ?? '',
                'color'        => $role->color ?? '#0C447C',
                'memberCount'  => (int) ($userCounts[$role->id] ?? 0),
                'permCount'    => $role->permissions->count(),
                'createTime'   => $role->created_at?->format('Y-m-d H:i:s'),
                'permissionNames' => $role->permissions->pluck('name'),
            ];
        });

        return response()->json([
            'code' => 0,
            'data' => [
                'data' => $data,
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * V1.2.7 P0 fix: 接受 name (admin/manager/...) 而非只 id
     * 路由用 whereNumber 限制会 404, 现在去掉, 自动按 name 查
     */
    public function show(string $role): JsonResponse
    {
        $r = is_numeric($role)
            ? Role::findOrFail((int) $role)
            : Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $r->load('permissions:id,name');
        return response()->json([
            'code' => 0,
            'data' => [
                'id'          => $r->id,
                'name'        => $r->name,
                'display_name'=> $this->roleDisplayName($r),
                'description' => $r->description,
                'color'       => $r->color ?? '#0C447C',
                'permissions' => $r->permissions->pluck('name'),
                'createTime'  => $r->created_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    private function roleDisplayName(Role $role): string
    {
        $map = [
            'system_admin' => '系统管理员',
            'admin' => '业务管理员',
            'manager' => '部门经理',
            'finance' => '财务',
            'user' => '普通员工',
            'sales_manager' => '销售经理',
        ];

        return $map[$role->name] ?? ($role->description ?: $role->name);
    }
    /**
     * 创建角色
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name'        => $data['name'],
            'guard_name'  => 'web',
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? '#0C447C',
        ]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json(['code' => 0, 'message' => '角色已创建', 'data' => ['id' => $role->id]]);
    }

    /**
     * 更新角色 (接受 name 或 id)
     */
    public function update(Request $request, string $role): JsonResponse
    {
        $r = is_numeric($role)
            ? Role::findOrFail((int) $role)
            : Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:64', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($r->id)],
            'description'  => ['nullable', 'string', 'max:255'],
            'color'        => ['nullable', 'string', 'max:16'],
            'permissions'  => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $r->fill([
            'name'        => $data['name']        ?? $r->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $r->description,
            'color'       => $data['color']       ?? $r->color,
        ])->save();

        if (array_key_exists('permissions', $data)) {
            $r->syncPermissions($data['permissions']);
        }

        return response()->json(['code' => 0, 'message' => '角色已更新']);
    }

    /**
     * 删除角色（强制级联：关联的 model_has_roles + role_has_permissions + permission_role 全部清掉）(接受 name 或 id)
     * V1.2.9g: 大哥要求 "直接删掉, 关联的设置和数据也删除" — 不再问是否解除绑定
     *   同时干掉:
     *     - model_has_roles  (该角色绑定的所有用户)
     *     - role_has_permissions (老 spatie 表, 跟 permission_role 之一存在, 兼容)
     *     - permission_role  (新 spatie 表)
     */
    public function destroy(string $role): JsonResponse
    {
        $r = is_numeric($role)
            ? Role::findOrFail((int) $role)
            : Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();

        // 系统保护: system / system_admin 是 system 账号绑定的角色, 不能删
        if (in_array($r->name, ['system', 'system_admin'], true)) {
            return response()->json([
                'code'    => 1003,
                'message' => "角色「{$r->name}」是系统保留角色, 不能删除",
            ], 403);
        }

        $roleId = $r->id;

        // 事务: 全部级联干掉
        DB::transaction(function () use ($roleId) {
            // 1. 解除用户绑定 (model_has_roles)
            DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', 'App\Models\User')
                ->delete();

            // 2. 删权限关联 (两个 spatie 表都试一下, 兼容历史数据)
            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->delete();
            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->delete();

            // 3. 最后删角色本身
            DB::table('roles')->where('id', $roleId)->delete();
        });

        return response()->json([
            'code'    => 0,
            'message' => '角色已删除, 关联的用户绑定和权限关联也已清除',
        ]);
    }
    /**
     * 同步角色的权限 (含继承链自动同步) (接受 name 或 id)
     * POST /api/roles/{role}/permissions
     * body: { permissions: ["perm.name1", "perm.name2"] }
     */
    public function assignPermissions(AssignPermissionsRequest $request, string $role): JsonResponse
    {
        $r = is_numeric($role)
            ? Role::findOrFail((int) $role)
            : Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $data = $request->validated();
        $perms = $data['permissions'] ?? [];

        // V0.5.2 业务侧赋权限时, 自动同步继承链
        $r->syncPermissions($perms);
        \App\Support\PermissionInheritance::propagateToChildren($r->name, $perms);

        return response()->json([
            'code' => 0,
            'message' => '权限配置已保存',
            'data' => ['count' => count($perms)],
        ]);
    }

    /**
     * 业务权限树（来自业务模块字典，与前端 hardcoded 树一一对应）
     * GET /api/permissions/tree
     */
    public function permissionTree(): JsonResponse
    {
        $tree = $this->buildPermissionTree();
        return response()->json(['code' => 0, 'data' => $tree]);
    }

    /**
     * V1.2.9l 菜单矩阵
     * GET /api/roles/menu-matrix
     * 返回: { roles, menus: [ { path, title, icon, leaves: [ { path, name, title, perm_key, perm_exists, checked } ] } ] }
     *
     * 菜单树写死 (跟 pc-web/src/router/index.ts 同步, 改两边一起改)
     * perm_key 为该页面对应的 permissions.name, 无 meta.permission 的页面为 null
     * checked = 该角色是否拥有该 perm_key (兼容 module-level fallback: 'customer' 命中 'customer.view')
     */
    public function menuMatrix(): JsonResponse
    {
        $roles = Role::where('guard_name', 'web')->orderBy('id')->get(['id', 'name', 'description', 'color']);

        // 角色 -> 权限集合
        $rolePerms = [];
        foreach ($roles as $r) {
            $p = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $r->id)
                ->pluck('permissions.name')
                ->all();
            $rolePerms[$r->name] = $p;
        }

        // 已知 perm 集合 (用于前端显示 "perm 不存在" 警告)
        $allPermNames = Permission::pluck('name')->flip()->all();

        $menus = $this->buildMenuTree();
        // V1.2.10: 给没有 perm_key 的叶子自动生成权限名 + 确保 permissions 表有记录
        $autoPermsToCreate = [];
        foreach ($menus as &$menu) {
            foreach ($menu['leaves'] as &$leaf) {
                if (empty($leaf['perm_key'])) {
                    $leaf['perm_key'] = $menu['path'] . '.' . $leaf['path'];
                }
                $leaf['perm_exists'] = isset($allPermNames[$leaf['perm_key']]);
                if (!$leaf['perm_exists']) {
                    $autoPermsToCreate[] = [
                        'name' => $leaf['perm_key'],
                        'display_name' => $leaf['title'] ?? $leaf['perm_key'],
                        'guard_name' => 'web',
                        'module' => $menu['path'],
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            unset($leaf);
        }
        unset($menu);
        // 批量创建缺失的权限记录
        if (!empty($autoPermsToCreate)) {
            $existing = Permission::whereIn('name', array_column($autoPermsToCreate, 'name'))->pluck('name')->all();
            $newPerms = array_filter($autoPermsToCreate, fn($p) => !in_array($p['name'], $existing));
            if (!empty($newPerms)) {
                Permission::insertOrIgnore($newPerms);
            }
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'roles'     => $roles->map(fn($r) => [
                    'id'          => $r->id,
                    'name'        => $r->name,
                    'description' => $r->description,
                    'color'       => $r->color ?? '#0C447C',
                ])->all(),
                'menus'     => $menus,
                'rolePerms' => $rolePerms, // 给前端直接拿来算 checked
            ],
        ]);
    }

    /**
     * V1.2.9l 菜单矩阵保存
     * POST /api/roles/{role}/menu-permissions
     * body: { leaves: ["parent_path:child_path", ...] }
     * 根据每个 leaf 查到对应的 perm_key, 一次性 syncPermissions
     * 角色没传 perm_key 的叶子 (perm_key=null) 自动忽略
     */
    public function saveMenuPermissions(Request $request, string $role): JsonResponse
    {
        $r = is_numeric($role)
            ? Role::findOrFail((int) $role)
            : Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();

        $data = $request->validate([
            'leaves'   => ['array'],
            'leaves.*' => ['string'],
        ]);
        $selectedLeaves = $data['leaves'] ?? [];

        // V1.2.10: 前端直接传 perm_key 列表, 不再需要 buildMenuTree 映射
        $perms = array_values(array_unique($selectedLeaves));

        $r->syncPermissions($perms);
        \App\Support\PermissionInheritance::propagateToChildren($r->name, $perms);

        return response()->json([
            'code'    => 0,
            'message' => "已保存「{$r->name}」的 " . count($perms) . " 项页面权限",
            'data'    => ['count' => count($perms)],
        ]);
    }

    /**
     * V1.2.9l 菜单树定义 — 跟 pc-web/src/router/index.ts 同步
     * 改菜单时同时改前端 router 和这里
     */
    private function buildMenuTree(): array
    {
        return [
            ['path' => 'dashboard', 'title' => '工作台', 'icon' => 'Odometer', 'leaves' => [
                ['path' => 'dashboard', 'name' => 'Dashboard', 'title' => '工作台', 'perm_key' => null],
            ]],
            ['path' => 'project-overview', 'title' => '总览看板', 'icon' => 'DataBoard', 'leaves' => [
                ['path' => 'project-overview', 'name' => 'ProjectOverview', 'title' => '总览看板', 'perm_key' => null],
            ]],
            ['path' => 'analytics', 'title' => 'BI 报表', 'icon' => 'TrendCharts', 'leaves' => [
                ['path' => 'analytics', 'name' => 'Analytics', 'title' => 'BI 报表', 'perm_key' => null],
            ]],
            ['path' => 'attendance', 'title' => '考勤管理', 'icon' => 'Calendar', 'leaves' => [
                ['path' => 'overview',     'name' => 'AttendanceOverview',    'title' => '考勤总览', 'perm_key' => null],
                ['path' => 'record',       'name' => 'AttendanceRecord',      'title' => '打卡记录', 'perm_key' => null],
                ['path' => 'leave',        'name' => 'AttendanceLeave',       'title' => '请假管理', 'perm_key' => null],
                ['path' => 'overtime',     'name' => 'AttendanceOvertime',    'title' => '加班管理', 'perm_key' => null],
                ['path' => 'report',       'name' => 'AttendanceReport',      'title' => '考勤报表', 'perm_key' => null],
                ['path' => 'shifts',       'name' => 'AttendanceShifts',      'title' => '班次配置', 'perm_key' => null],
                ['path' => 'groups',       'name' => 'AttendanceGroups',      'title' => '班组管理', 'perm_key' => null],
                ['path' => 'schedule',     'name' => 'AttendanceSchedule',    'title' => '排班计划', 'perm_key' => null],
                ['path' => 'my-schedule',  'name' => 'AttendanceMySchedule',  'title' => '我的排班', 'perm_key' => null],
            ]],
            ['path' => 'employee', 'title' => '员工管理', 'icon' => 'User', 'leaves' => [
                ['path' => 'list',         'name' => 'EmployeeList',         'title' => '员工列表', 'perm_key' => 'employee.view'],
                ['path' => 'onboardings',  'name' => 'EmployeeOnboardings',  'title' => '入职档案', 'perm_key' => 'employee.onboarding'],
                ['path' => 'resignations', 'name' => 'EmployeeResignations', 'title' => '离职管理', 'perm_key' => 'employee.resignation'],
                ['path' => 'skill',        'name' => 'EmployeeSkill',        'title' => '技能标签', 'perm_key' => 'employee.skill'],
            ]],
            ['path' => 'customer', 'title' => '客户管理', 'icon' => 'OfficeBuilding', 'leaves' => [
                ['path' => 'list',            'name' => 'CustomerList',           'title' => '客户列表', 'perm_key' => 'customer.view'],
                ['path' => 'health',          'name' => 'CustomerHealth',         'title' => '健康度',   'perm_key' => 'customer.health'],
                ['path' => 'pipeline',        'name' => 'CustomerPipeline',       'title' => '销售漏斗', 'perm_key' => 'customer.pipeline'],
                ['path' => 'follow-calendar', 'name' => 'CustomerFollowCalendar', 'title' => '跟进日历', 'perm_key' => 'customer.follow'],
                ['path' => 'map',             'name' => 'CustomerMap',            'title' => '客户地图', 'perm_key' => 'customer.map'],
            ]],
            ['path' => 'sales', 'title' => '销售管理', 'icon' => 'Money', 'leaves' => [
                ['path' => 'opps',            'name' => 'SalesOpps',          'title' => '商机池',     'perm_key' => 'sales.opps'],
                ['path' => 'opps/board',      'name' => 'SalesOppsBoard',     'title' => '商机看板',   'perm_key' => 'sales.opps.board'],
                ['path' => 'opps/:id/quote',  'name' => 'SalesQuotes',        'title' => '报价单',     'perm_key' => 'sales.quote'],
                ['path' => 'referrers',       'name' => 'SalesReferrers',     'title' => '推荐人',     'perm_key' => 'sales.referrer'],
                ['path' => 'settlements',     'name' => 'SalesSettlements',   'title' => '居间费结算', 'perm_key' => 'sales.settlement'],
                ['path' => 'external-quote',  'name' => 'SalesExternalQuote', 'title' => '报价看板',   'perm_key' => 'sales.external_quote'],
            ]],
            ['path' => 'project', 'title' => '项目管理', 'icon' => 'Files', 'leaves' => [
                ['path' => 'pool',           'name' => 'ProjectPool',              'title' => '项目池',     'perm_key' => 'project.pool'],
                ['path' => 'list',           'name' => 'ProjectList',              'title' => '项目列表',   'perm_key' => 'project.view'],
                ['path' => 'board',          'name' => 'ProjectBoard',             'title' => '项目看板',   'perm_key' => 'project.board'],
                ['path' => 'calendar',       'name' => 'ProjectCalendar',          'title' => '付款日历',   'perm_key' => 'project.calendar'],
                ['path' => 'create',         'name' => 'ProjectCreate',            'title' => '创建项目',   'perm_key' => 'project.create'],
                ['path' => 'gantt/:id',      'name' => 'ProjectGantt',             'title' => '施工图',     'perm_key' => 'project.gantt'],
                ['path' => 'warranty/list',  'name' => 'WarrantyList',             'title' => '质保期列表', 'perm_key' => 'warranty.view'],
                ['path' => 'warranty/expiring', 'name' => 'WarrantyExpiring',      'title' => '即将到期',   'perm_key' => 'warranty.expiring'],
                ['path' => 'warranty/service-order', 'name' => 'WarrantyServiceOrderList', 'title' => '服务工单', 'perm_key' => 'warranty.service'],
                ['path' => 'warranty/deposit', 'name' => 'WarrantyDepositList',    'title' => '质保金',     'perm_key' => 'warranty.deposit'],
            ]],
            ['path' => 'purchase-collab', 'title' => '采购协同', 'icon' => 'Connection', 'leaves' => [
                ['path' => 'requirement',         'name' => 'CollabRequirement',        'title' => '采购需求', 'perm_key' => 'purchase.requirement'],
                ['path' => 'order',               'name' => 'CollabOrder',              'title' => '采购计划', 'perm_key' => 'purchase.order'],
                ['path' => 'receive',             'name' => 'CollabReceive',            'title' => '采购详情', 'perm_key' => 'purchase.detail'],
                ['path' => 'supplier',            'name' => 'CollabSupplier',           'title' => '供应商库', 'perm_key' => 'purchase.supplier'],
                ['path' => 'tender',              'name' => 'CollabTender',             'title' => '招标中心', 'perm_key' => 'purchase.tender'],
                ['path' => 'construction-tender', 'name' => 'CollabConstructionTender', 'title' => '施工招标', 'perm_key' => 'purchase.construction_tender'],
                ['path' => 'portal-config',       'name' => 'CollabPortalConfig',       'title' => '门户管理', 'perm_key' => 'purchase.portal'],
            ]],
            ['path' => 'construction', 'title' => '施工管理', 'icon' => 'Tools', 'leaves' => [
                ['path' => 'team',                 'name' => 'ConstructionTeam',            'title' => '施工团队', 'perm_key' => 'construction.team'],
                ['path' => 'commencement',         'name' => 'ConstructionCommencement',    'title' => '开工单',   'perm_key' => 'construction.commencement'],
                ['path' => 'log',                  'name' => 'ConstructionLog',             'title' => '施工日志', 'perm_key' => 'construction.log'],
                ['path' => 'log/daily',            'name' => 'ConstructionLogDaily',        'title' => '每日上报', 'perm_key' => 'construction.log.daily'],
                ['path' => 'rectification',        'name' => 'ConstructionRectification',   'title' => '整改工单', 'perm_key' => 'construction.rectification'],
                ['path' => 'work-process',         'name' => 'ConstructionWorkProcess',     'title' => '工序字典', 'perm_key' => 'construction.process'],
                ['path' => 'external-work',        'name' => 'ConstructionExternalWork',    'title' => '施工发包', 'perm_key' => 'construction.external'],
                ['path' => 'process/templates',    'name' => 'ConstructionProcessTemplates','title' => '工序模板', 'perm_key' => 'construction.process.template'],
                ['path' => 'process/instances',    'name' => 'ConstructionProcessInstances','title' => '工序实例', 'perm_key' => 'construction.process.instance'],
                ['path' => 'process/inspections',  'name' => 'ConstructionProcessInspections','title' => '验收记录', 'perm_key' => 'construction.process.inspection'],
            ]],
            ['path' => 'maintenance', 'title' => '维修中心', 'icon' => 'SetUp', 'leaves' => [
                ['path' => 'work-orders',   'name' => 'MaintenanceWorkOrders',   'title' => '维修工单', 'perm_key' => 'maintenance.workorder'],
                ['path' => 'repairs',       'name' => 'MaintenanceRepairs',      'title' => '返修管理', 'perm_key' => 'maintenance.repair'],
                ['path' => 'stats',         'name' => 'MaintenanceStats',        'title' => '维修统计', 'perm_key' => 'maintenance.stats'],
                ['path' => 'kanban',        'name' => 'MaintenanceKanban',       'title' => '维修看板', 'perm_key' => 'maintenance.kanban'],
                ['path' => 'portal-repair', 'name' => 'MaintenancePortalRepair', 'title' => '返修单',   'perm_key' => 'maintenance.portal_repair'],
            ]],
            ['path' => 'inspection', 'title' => '巡检计划', 'icon' => 'CircleCheck', 'leaves' => [
                ['path' => 'overview',    'name' => 'InspectionOverview', 'title' => '巡检总览', 'perm_key' => 'inspection.overview'],
                ['path' => 'plans',       'name' => 'InspectionPlans',    'title' => '巡检计划', 'perm_key' => 'inspection.plan'],
                ['path' => 'tasks',       'name' => 'InspectionTasks',    'title' => '执行任务', 'perm_key' => 'inspection.task'],
                ['path' => 'tasks/mine',  'name' => 'InspectionMyTasks',  'title' => '我的巡检', 'perm_key' => 'inspection.mine'],
                ['path' => 'issues',      'name' => 'InspectionIssues',   'title' => '异常清单', 'perm_key' => 'inspection.issue'],
            ]],
            ['path' => 'expense', 'title' => '报销管理', 'icon' => 'Money', 'leaves' => [
                ['path' => 'list',  'name' => 'ExpenseList',  'title' => '报销列表', 'perm_key' => 'expense.view'],
                ['path' => 'apply', 'name' => 'ExpenseApply', 'title' => '申请报销', 'perm_key' => 'expense.apply'],
            ]],
            ['path' => 'vehicle', 'title' => '车辆管理', 'icon' => 'Van', 'leaves' => [
                ['path' => 'fleet',       'name' => 'VehicleFleet',       'title' => '车辆档案', 'perm_key' => 'vehicle.view'],
                ['path' => 'apply',       'name' => 'VehicleApply',       'title' => '用车申请', 'perm_key' => 'vehicle.apply'],
                ['path' => 'dispatch',    'name' => 'VehicleDispatch',    'title' => '调度管理', 'perm_key' => 'vehicle.dispatch'],
                ['path' => 'insurance',   'name' => 'VehicleInsurance',   'title' => '保险记录', 'perm_key' => 'vehicle.insurance'],
                ['path' => 'maintenance', 'name' => 'VehicleMaintenance', 'title' => '保养记录', 'perm_key' => 'vehicle.maintenance'],
                ['path' => 'fuel-card',   'name' => 'VehicleFuelCard',    'title' => '油卡管理', 'perm_key' => 'vehicle.fuel'],
            ]],
            ['path' => 'inventory', 'title' => '库存管理', 'icon' => 'Box', 'leaves' => [
                ['path' => '',                 'name' => 'InventoryStock',           'title' => '库存总览', 'perm_key' => 'inventory.view'],
                ['path' => 'inout',            'name' => 'InventoryInOut',           'title' => '出入库',   'perm_key' => 'inventory.inout'],
                ['path' => 'inbound-order',    'name' => 'InventoryInboundOrder',    'title' => '入库单',   'perm_key' => 'inventory.inbound'],
                ['path' => 'outbound-order',   'name' => 'InventoryOutboundOrder',   'title' => '出库单',   'perm_key' => 'inventory.outbound'],
                ['path' => 'material-request', 'name' => 'InventoryMaterialRequest', 'title' => '领料单',   'perm_key' => 'inventory.request'],
                ['path' => 'material-return',  'name' => 'InventoryMaterialReturn',  'title' => '退料单',   'perm_key' => 'inventory.return'],
            ]],
            ['path' => 'finance', 'title' => '财务管理', 'icon' => 'Wallet', 'leaves' => [
                ['path' => 'overview',        'name' => 'FinanceOverview',        'title' => '财务概览',   'perm_key' => 'finance.overview'],
                ['path' => 'receipt',         'name' => 'FinanceReceipt',         'title' => '收款单',     'perm_key' => 'finance.receipt'],
                ['path' => 'payment',         'name' => 'FinancePayment',         'title' => '付款单',     'perm_key' => 'finance.payment'],
                ['path' => 'receivable',      'name' => 'FinanceReceivable',      'title' => '应收账款',   'perm_key' => 'finance.receivable'],
                ['path' => 'payable',         'name' => 'FinancePayable',         'title' => '应付账款',   'perm_key' => 'finance.payable'],
                ['path' => 'supplier-ledger', 'name' => 'FinanceSupplierLedger',  'title' => '供应商总账', 'perm_key' => 'finance.supplier_ledger'],
                ['path' => 'customer-ledger', 'name' => 'FinanceCustomerLedger',  'title' => '客户总账',   'perm_key' => 'finance.customer_ledger'],
                ['path' => 'repair-cost',     'name' => 'FinanceRepairCost',      'title' => '成本报表',   'perm_key' => 'finance.repair_cost'],
            ]],
            ['path' => 'approval', 'title' => '审批中心', 'icon' => 'CircleCheck', 'leaves' => [
                ['path' => 'finance',   'name' => 'ApprovalFinance',   'title' => '财务审批', 'perm_key' => 'approval.finance'],
                ['path' => 'operation', 'name' => 'ApprovalOperation', 'title' => '运营审批', 'perm_key' => 'approval.operation'],
                ['path' => 'project',   'name' => 'ApprovalProject',   'title' => '项目审批', 'perm_key' => 'approval.project'],
            ]],
            ['path' => 'disk', 'title' => '公司网盘', 'icon' => 'FolderOpened', 'leaves' => [
                ['path' => 'disk', 'name' => 'Disk', 'title' => '公司网盘', 'perm_key' => 'disk.view'],
            ]],
            ['path' => 'knowledge', 'title' => '知识库', 'icon' => 'Reading', 'leaves' => [
                ['path' => 'list', 'name' => 'KnowledgeList', 'title' => '知识列表', 'perm_key' => 'knowledge.view'],
            ]],
            ['path' => 'screen', 'title' => '数据大屏', 'icon' => 'DataAnalysis', 'leaves' => [
                ['path' => 'screen', 'name' => 'Screen', 'title' => '数据大屏', 'perm_key' => null],
            ]],
            ['path' => 'message', 'title' => '消息中心', 'icon' => 'Bell', 'leaves' => [
                ['path' => 'list', 'name' => 'MessageList', 'title' => '消息列表', 'perm_key' => 'message.view'],
            ]],
            ['path' => 'settings', 'title' => '系统设置', 'icon' => 'Setting', 'leaves' => [
                ['path' => 'profile',        'name' => 'SettingsProfile',        'title' => '个人信息', 'perm_key' => null],
                ['path' => 'password',       'name' => 'SettingsPassword',       'title' => '修改密码', 'perm_key' => null],
                ['path' => 'my-permissions', 'name' => 'SettingsMyPermissions',  'title' => '我的权限', 'perm_key' => null],
                ['path' => 'organization',   'name' => 'SettingsOrg',            'title' => '组织结构', 'perm_key' => 'settings.org'],
                ['path' => 'role/matrix',    'name' => 'SettingsRoleMatrix',     'title' => '权限矩阵', 'perm_key' => 'settings.role_matrix'],
                ['path' => 'field-mask',     'name' => 'SettingsFieldMask',      'title' => '字段脱敏', 'perm_key' => 'settings.field_mask'],
                ['path' => 'permission-log', 'name' => 'SettingsPermissionLog',  'title' => '权限日志', 'perm_key' => 'settings.permission_log'],
                ['path' => 'approval',       'name' => 'SettingsApproval',       'title' => '审批中心', 'perm_key' => 'settings.approval'],
                ['path' => 'log',            'name' => 'SettingsLog',            'title' => '系统日志', 'perm_key' => 'settings.log'],
                ['path' => 'backup',         'name' => 'SettingsBackup',         'title' => '数据管理', 'perm_key' => 'settings.backup'],
            ]],
            // [SYSTEM ONLY] system 账号专属
            ['path' => 'admin', 'title' => '系统管理', 'icon' => 'Setting', 'leaves' => [
                ['path' => 'welcome', 'name' => 'AdminWelcome', 'title' => '系统首页',   'perm_key' => null],
                ['path' => 'wizard',  'name' => 'AdminWizard',  'title' => '初始化向导', 'perm_key' => null],
            ]],
        ];
    }

    /**
     * V0.5.2 角色权限矩阵
     * GET /api/roles/matrix
     * 返回: { roles:[name,...], permissions:[{module,name,label},...], matrix:{role_name:[perm_name,...]}, inheritance:Graph }
     */
    public function matrix(): JsonResponse
    {
        $roles = Role::where('guard_name', 'web')->orderBy('id')->get(['id', 'name', 'description', 'color']);

        // 全部权限按 module 分组
        $perms = Permission::orderBy('module')->orderBy('id')
            ->get(['id', 'name', 'module', 'description', 'display_name']);

        // 角色 -> 权限集合
        $roleMatrix = [];
        foreach ($roles as $r) {
            $p = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $r->id)
                ->pluck('permissions.name')
                ->all();
            sort($p);
            $roleMatrix[$r->name] = $p;
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'roles' => $roles->map(fn($r) => [
                    'id'          => $r->id,
                    'name'        => $r->name,
                    'description' => $r->description,
                    'color'       => $r->color ?? '#0C447C',
                ])->all(),
                'permissions' => $perms->map(fn($p) => [
                    'id'           => $p->id,
                    'name'         => $p->name,
                    'module'       => $p->module,
                    'label'        => $p->description ?? $p->name,
                    'display_name' => $p->display_name,
                ])->all(),
                'matrix'      => $roleMatrix,
                'inheritance' => \App\Support\PermissionInheritance::getGraph(),
            ],
        ]);
    }

    /**
     * V0.5.2 角色继承图
     * GET /api/permissions/inheritance
     */
    public function inheritanceGraph(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => \App\Support\PermissionInheritance::getGraph(),
        ]);
    }

    /**
     * V0.5.0 L1 前端用 — 当前登录用户的所有权限 name 列表
     * GET /api/permissions/my
     */
    public function myPermissions(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['code' => 401, 'message' => '未认证'], 401);
        }
        // admin 直接返所有权限 (前端不显示 hidden menu)
        $userRoles = [];
        try {
            $userRoles = $user->roles->pluck('name')->all();
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // ignore
        }
        if (in_array('admin', $userRoles, true)) {
            $list = Permission::orderBy('module')->orderBy('id')->get(['name', 'module', 'display_name', 'description'])
                ->map(fn($p) => [
                    'name' => $p->name,
                    'module' => $p->module,
                    'label' => $p->display_name ?? $p->description ?? $p->name,
                ]);
            return response()->json(['code' => 0, 'data' => $list, 'roles' => $userRoles]);
        }

        $list = $user->getAllPermissions()->map(fn($p) => [
            'name' => $p->name,
            'module' => $p->module ?? '',
            'label' => $p->display_name ?? $p->description ?? $p->name,
        ])->values();

        return response()->json(['code' => 0, 'data' => $list, 'roles' => $userRoles]);
    }

    /**
     * 所有权限（平铺列表）
     */
    public function permissionIndex(): JsonResponse
    {
        $list = Permission::orderBy('id')->get(['id', 'name', 'module', 'description'])->map(function ($p) {
            return [
                'id'          => $p->id,
                'name'        => $p->name,
                'label'       => $p->description ?? $p->name,
                'module'      => $p->module,
            ];
        });
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /**
     * 业务权限字典（与数据库 permissions.name 一一对应）
     * name 必须 = permissions.name（英文点号），label 给前端展示用
     */
    private function buildPermissionTree(): array
    {
        $modules = [
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
            ],
            '考勤管理' => [
                ['name' => 'attendance.view',    'label' => '考勤总览'],
                ['name' => 'attendance.record',  'label' => '打卡记录查看'],
                ['name' => 'attendance.leave',   'label' => '请假审批'],
                ['name' => 'attendance.overtime','label' => '加班审批'],
                ['name' => 'attendance.report',  'label' => '考勤报表'],
            ],
            '项目管理' => [
                ['name' => 'project.view',   'label' => '项目列表查看'],
                ['name' => 'project.create', 'label' => '项目创建编辑'],
                ['name' => 'project.assign', 'label' => '任务分配管理'],
                ['name' => 'project.report', 'label' => '项目报表'],
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
            ],
            '库存管理' => [
                ['name' => 'inventory.view',     'label' => '库存总览'],
                ['name' => 'inventory.transfer', 'label' => '出入库记录'],
                ['name' => 'inventory.alert',    'label' => '库存预警设置'],
            ],
            '审批流程' => [
                ['name' => 'approval.template', 'label' => '流程模板管理'],
                ['name' => 'approval.mine',     'label' => '我的审批'],
                ['name' => 'approval.config',   'label' => '审批配置'],
            ],
        ];
        $tree = [];
        foreach ($modules as $mod => $perms) {
            $children = [];
            foreach ($perms as $p) {
                $children[] = [
                    'id'    => $p['name'],
                    'name'  => $p['name'],
                    'label' => $p['label'],
                ];
            }
            $tree[] = [
                'id'       => $mod,
                'name'     => $mod,
                'label'    => $mod,
                'children' => $children,
            ];
        }
        return $tree;
    }

    // =============================================================
    // V0.5.1 用户-角色管理 (admin 限定)
    // =============================================================

    /**
     * 改用户角色
     * PUT /api/users/{user}/roles
     * body: { roles: ["admin","finance"] }  — 替换用户所有角色
     */
    public function usersSyncRoles(Request $request, \App\Models\User $user): JsonResponse
    {
        $roleNames = (array) $request->input('roles', []);
        // 校验所有 role name 都存在
        $valid = \Spatie\Permission\Models\Role::whereIn('name', $roleNames)->pluck('name')->all();
        if (count($valid) !== count($roleNames)) {
            $invalid = array_diff($roleNames, $valid);
            return response()->json([
                'code' => 422,
                'message' => '无效角色: ' . implode(',', $invalid),
            ], 422);
        }
        // 记录原角色 (audit 用)
        $oldRoles = $user->roles->pluck('name')->sort()->values()->all();
        $newRoles = collect($valid)->sort()->values()->all();
        // V0.5.2 修: User::getDefaultGuardName 已返回 'web', 直接 syncRoles 即可
        $user->syncRoles($valid);
        $freshRoles = $user->fresh()->roles->pluck('name')->all();

        // V0.5.2: 写 audit log (action=role_changed)
        if ($oldRoles !== $newRoles) {
            \App\Support\Audit::write('role_changed', sprintf(
                '用户「%s」(%d) 角色变更: %s → %s',
                $user->username, $user->id,
                implode(',', $oldRoles) ?: '(无)',
                implode(',', $newRoles) ?: '(无)'
            ), [
                'target_user_id' => $user->id,
                'target_username' => $user->username,
                'old_roles' => $oldRoles,
                'new_roles' => $newRoles,
            ]);
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'id'    => $user->id,
                'roles' => $freshRoles,
            ],
            'message' => '已更新',
        ]);
    }

    /**
     * 一键赋角色 — 给多个用户批量赋同一个角色
     * POST /api/users/bulk-assign-role
     * body: { user_ids: [1,2,3], role: "manager" }
     */
    public function usersBulkAssignRole(Request $request): JsonResponse
    {
        $userIds = (array) $request->input('user_ids', []);
        $roleName = (string) $request->input('role', '');
        if (!$roleName || !Role::where('name', $roleName)->exists()) {
            return response()->json(['code' => 422, 'message' => '角色不存在'], 422);
        }
        $count = 0;
        foreach ($userIds as $uid) {
            $u = \App\Models\User::find($uid);
            if ($u) {
                $u->assignRole($roleName);
                $count++;
            }
        }
        return response()->json([
            'code' => 0,
            'data' => ['affected' => $count],
            'message' => "已为 {$count} 个用户分配角色「{$roleName}」",
        ]);
    }

    // =============================================================
    // V0.5.3 临时角色授权
    // =============================================================

    /**
     * 查一个用户的所有角色记录（含过期/永久/grant 信息）
     * GET /api/users/{user}/roles
     */
    public function usersListRoles(Request $request, \App\Models\User $user): JsonResponse
    {
        $rows = $user->allRoleAssignments()
            ->get()
            ->map(function ($r) {
                $expires = $r->expires_at ? \Carbon\Carbon::parse($r->expires_at) : null;
                $isExpired = $expires && $expires->isPast();
                $isPermanent = $expires === null;
                return [
                    'name'        => $r->name,
                    'description' => $r->description,
                    'color'       => $r->color ?? '#0C447C',
                    'expires_at'  => $r->expires_at ? \Carbon\Carbon::parse($r->expires_at)->toDateTimeString() : null,
                    'granted_by'  => $r->granted_by,
                    'reason'      => $r->reason,
                    'status'      => $isExpired ? 'expired' : ($isPermanent ? 'permanent' : 'temporary'),
                    'days_left'   => $expires && !$isExpired ? (int) now()->diffInDays($expires, false) : null,
                ];
            });

        // 注入角色名（前端可能用 role id 转 name）
        return response()->json([
            'code' => 0,
            'data' => [
                'user_id'      => $user->id,
                'username'     => $user->username,
                'assignments'  => $rows->values()->all(),
                'active_count' => $rows->whereIn('status', ['permanent', 'temporary'])->count(),
            ],
        ]);
    }

    /**
     * 给一个用户授临时角色（可批量）
     * POST /api/users/{user}/roles/temporary
     * body:
     *   { assignments: [
     *       { "role": "finance", "expires_at": "2026-07-01 18:00", "reason": "项目借调" },
     *       { "role": "manager", "expires_at": "2026-12-31", "reason": "代理" }
     *     ] }
     * 语义: **替换**所有当前临时角色（永久角色保留）
     */
    public function usersGrantTemporary(Request $request, \App\Models\User $user): JsonResponse
    {
        $data = $request->validate([
            'assignments'                  => 'required|array|min:1',
            'assignments.*.role'            => 'required|string|max:100',
            'assignments.*.expires_at'      => 'required|date|after:now',
            'assignments.*.reason'          => 'nullable|string|max:500',
        ]);

        $grantedBy = $request->user()?->id;
        $oldAssignments = $user->allRoleAssignments()
            ->whereNotNull('model_has_roles.expires_at')
            ->get(['roles.name as name', 'model_has_roles.expires_at', 'model_has_roles.reason'])
            ->map(fn ($r) => (array) $r)
            ->all();

        $entries = [];
        foreach ($data['assignments'] as $a) {
            // 校验 role 存在
            $exists = Role::where('name', $a['role'])->where('guard_name', 'web')->exists();
            if (!$exists) {
                return response()->json([
                    'code' => 422,
                    'message' => "角色不存在: {$a['role']}",
                ], 422);
            }
            $entries[] = [
                'name'       => $a['role'],
                'expires_at' => \Carbon\Carbon::parse($a['expires_at']),
                'reason'     => $a['reason'] ?? null,
            ];
        }

        $added = \App\Support\TemporaryRole::syncTemporary($user, $entries, $grantedBy);

        // audit
        \App\Support\Audit::write('temporary_role_granted', sprintf(
            '用户「%s」(%d) 临时角色变更: %s → %s',
            $user->username, $user->id,
            $oldAssignments ? implode(',', array_column($oldAssignments, 'name')) : '(无)',
            implode(',', array_column($entries, 'name'))
        ), [
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'old_assignments' => $oldAssignments,
            'new_assignments' => array_map(fn ($e) => [
                'role' => $e['name'],
                'expires_at' => $e['expires_at']->toDateTimeString(),
                'reason' => $e['reason'],
            ], $entries),
            'granted_by' => $grantedBy,
        ]);

        return response()->json([
            'code'    => 0,
            'data'    => [
                'user_id'    => $user->id,
                'added'      => $added,
                'assignments' => $user->fresh()->allRoleAssignments()
                    ->get()
                    ->map(fn ($r) => [
                        'name'       => $r->name,
                        'expires_at' => $r->expires_at,
                        'reason'     => $r->reason,
                    ])
                    ->values()
                    ->all(),
            ],
            'message' => '已分配临时角色',
        ]);
    }

    /**
     * 撤销一个用户的一个角色（永久/临时都删）
     * DELETE /api/users/{user}/roles/{role}
     * 注: {role} 是 role name 字符串（前端按 name 识别），不是数字 id
     */
    public function usersRevokeRole(Request $request, \App\Models\User $user, string $role): JsonResponse
    {
        $exists = Role::where('name', $role)->where('guard_name', 'web')->exists();
        if (!$exists) {
            return response()->json(['code' => 404, 'message' => "角色不存在: {$role}"], 404);
        }

        $revokedBy = $request->user()?->id;
        $reason = (string) $request->input('reason', '');
        $ok = \App\Support\TemporaryRole::revoke($user, $role, $reason ?: null, $revokedBy);
        if (!$ok) {
            return response()->json(['code' => 404, 'message' => "用户未持有角色「{$role}」"], 404);
        }

        \App\Support\Audit::write('role_revoked', sprintf(
            '撤销用户「%s」(%d) 的角色「%s」%s',
            $user->username, $user->id, $role,
            $reason ? "（理由: {$reason}）" : ''
        ), [
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'role' => $role,
            'reason' => $reason,
            'revoked_by' => $revokedBy,
        ]);

        return response()->json([
            'code'    => 0,
            'message' => "已撤销角色「{$role}」",
        ]);
    }

    /**
     * 查用户当前有效角色 + 有效权限
     * GET /api/users/{user}/roles/active
     */
    public function usersActiveRoles(Request $request, \App\Models\User $user): JsonResponse
    {
        $roles = $user->activeRoles()
            ->get(['roles.id', 'roles.name', 'roles.description', 'roles.color', 'model_has_roles.expires_at', 'model_has_roles.reason'])
            ->map(fn ($r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'display_name'=> $this->roleDisplayName($r),
                'description' => $r->description,
                'color'       => $r->color ?? '#0C447C',
                'expires_at'  => $r->expires_at ? \Carbon\Carbon::parse($r->expires_at)->toDateTimeString() : null,
                'reason'      => $r->reason,
            ]);

        $permissions = $user->activePermissionNames();

        return response()->json([
            'code' => 0,
            'data' => [
                'user_id'     => $user->id,
                'username'    => $user->username,
                'roles'       => $roles,
                'permissions' => $permissions,
            ],
        ]);
    }

    /**
     * 管理员看 7 天内即将过期的角色
     * GET /api/roles/expiring?within_days=7
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $days = (int) $request->input('within_days', 7);
        $rows = \App\Support\TemporaryRole::expiringSoon($days);
        return response()->json([
            'code' => 0,
            'data' => [
                'within_days' => $days,
                'count'       => count($rows),
                'rows'        => $rows,
            ],
        ]);
    }
}
