<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * V1.2.9l 菜单矩阵 — 同步 pc-web/src/router/index.ts 的 perm_key 定义
 * 跟 pc-api/app/Http/Controllers/Api/RoleController.php::buildMenuTree() 严格对齐
 *
 * 只 INSERT, 已有 name 跳过 (idempotent, 可重跑)
 * 老的 38 个 module-level perms (employee/customer/project...) 保留做兜底
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            // ===== 考勤管理 =====
            ['name' => 'attendance.overview', 'module' => 'attendance', 'display_name' => '考勤总览', 'guard_name' => 'web'],
            ['name' => 'attendance.record',   'module' => 'attendance', 'display_name' => '打卡记录', 'guard_name' => 'web'],
            ['name' => 'attendance.leave',    'module' => 'attendance', 'display_name' => '请假管理', 'guard_name' => 'web'],
            ['name' => 'attendance.overtime', 'module' => 'attendance', 'display_name' => '加班管理', 'guard_name' => 'web'],
            ['name' => 'attendance.report',   'module' => 'attendance', 'display_name' => '考勤报表', 'guard_name' => 'web'],
            ['name' => 'attendance.shifts',   'module' => 'attendance', 'display_name' => '班次配置', 'guard_name' => 'web'],
            ['name' => 'attendance.groups',   'module' => 'attendance', 'display_name' => '班组管理', 'guard_name' => 'web'],
            ['name' => 'attendance.schedule', 'module' => 'attendance', 'display_name' => '排班计划', 'guard_name' => 'web'],
            ['name' => 'attendance.mine',     'module' => 'attendance', 'display_name' => '我的排班', 'guard_name' => 'web'],

            // ===== 员工管理 =====
            ['name' => 'employee.view',        'module' => 'employee', 'display_name' => '员工列表',  'guard_name' => 'web'],
            ['name' => 'employee.onboarding',  'module' => 'employee', 'display_name' => '入职档案',  'guard_name' => 'web'],
            ['name' => 'employee.resignation', 'module' => 'employee', 'display_name' => '离职管理',  'guard_name' => 'web'],
            ['name' => 'employee.skill',       'module' => 'employee', 'display_name' => '技能标签',  'guard_name' => 'web'],

            // ===== 客户管理 =====
            ['name' => 'customer.view',     'module' => 'customer', 'display_name' => '客户列表', 'guard_name' => 'web'],
            ['name' => 'customer.health',   'module' => 'customer', 'display_name' => '健康度',   'guard_name' => 'web'],
            ['name' => 'customer.pipeline', 'module' => 'customer', 'display_name' => '销售漏斗', 'guard_name' => 'web'],
            ['name' => 'customer.follow',   'module' => 'customer', 'display_name' => '跟进日历', 'guard_name' => 'web'],
            ['name' => 'customer.map',      'module' => 'customer', 'display_name' => '客户地图', 'guard_name' => 'web'],

            // ===== 销售管理 =====
            ['name' => 'sales.leads',            'module' => 'sales', 'display_name' => '线索池',     'guard_name' => 'web'],
            ['name' => 'sales.leads.board',      'module' => 'sales', 'display_name' => '线索看板',   'guard_name' => 'web'],
            ['name' => 'sales.opps',             'module' => 'sales', 'display_name' => '商机池',     'guard_name' => 'web'],
            ['name' => 'sales.opps.board',       'module' => 'sales', 'display_name' => '商机看板',   'guard_name' => 'web'],
            ['name' => 'sales.quote',            'module' => 'sales', 'display_name' => '报价单',     'guard_name' => 'web'],
            ['name' => 'sales.referrer',         'module' => 'sales', 'display_name' => '推荐人',     'guard_name' => 'web'],
            ['name' => 'sales.settlement',       'module' => 'sales', 'display_name' => '居间费结算', 'guard_name' => 'web'],
            ['name' => 'sales.external_quote',   'module' => 'sales', 'display_name' => '报价看板',   'guard_name' => 'web'],

            // ===== 项目管理 =====
            ['name' => 'project.pool',      'module' => 'project', 'display_name' => '项目池',     'guard_name' => 'web'],
            ['name' => 'project.view',      'module' => 'project', 'display_name' => '项目列表',   'guard_name' => 'web'],
            ['name' => 'project.board',     'module' => 'project', 'display_name' => '项目看板',   'guard_name' => 'web'],
            ['name' => 'project.calendar',  'module' => 'project', 'display_name' => '付款日历',   'guard_name' => 'web'],
            ['name' => 'project.create',    'module' => 'project', 'display_name' => '创建项目',   'guard_name' => 'web'],
            ['name' => 'project.gantt',     'module' => 'project', 'display_name' => '施工图',     'guard_name' => 'web'],
            ['name' => 'warranty.view',     'module' => 'warranty', 'display_name' => '质保期列表', 'guard_name' => 'web'],
            ['name' => 'warranty.expiring', 'module' => 'warranty', 'display_name' => '即将到期',   'guard_name' => 'web'],
            ['name' => 'warranty.service',  'module' => 'warranty', 'display_name' => '服务工单',   'guard_name' => 'web'],
            ['name' => 'warranty.deposit',  'module' => 'warranty', 'display_name' => '质保金',     'guard_name' => 'web'],

            // ===== 采购协同 =====
            ['name' => 'purchase.requirement',         'module' => 'purchase', 'display_name' => '采购需求', 'guard_name' => 'web'],
            ['name' => 'purchase.order',               'module' => 'purchase', 'display_name' => '采购计划', 'guard_name' => 'web'],
            ['name' => 'purchase.detail',              'module' => 'purchase', 'display_name' => '采购详情', 'guard_name' => 'web'],
            ['name' => 'purchase.supplier',            'module' => 'purchase', 'display_name' => '供应商库', 'guard_name' => 'web'],
            ['name' => 'purchase.tender',              'module' => 'purchase', 'display_name' => '招标中心', 'guard_name' => 'web'],
            ['name' => 'purchase.construction_tender', 'module' => 'purchase', 'display_name' => '施工招标', 'guard_name' => 'web'],
            ['name' => 'purchase.portal',              'module' => 'purchase', 'display_name' => '门户管理', 'guard_name' => 'web'],

            // ===== 施工管理 =====
            ['name' => 'construction.team',                  'module' => 'construction', 'display_name' => '施工团队', 'guard_name' => 'web'],
            ['name' => 'construction.commencement',          'module' => 'construction', 'display_name' => '开工单',   'guard_name' => 'web'],
            ['name' => 'construction.log',                   'module' => 'construction', 'display_name' => '施工日志', 'guard_name' => 'web'],
            ['name' => 'construction.log.daily',             'module' => 'construction', 'display_name' => '每日上报', 'guard_name' => 'web'],
            ['name' => 'construction.rectification',         'module' => 'construction', 'display_name' => '整改工单', 'guard_name' => 'web'],
            ['name' => 'construction.process',               'module' => 'construction', 'display_name' => '工序库',   'guard_name' => 'web'],
            ['name' => 'construction.external',              'module' => 'construction', 'display_name' => '施工发包', 'guard_name' => 'web'],
            ['name' => 'construction.process.template',      'module' => 'construction', 'display_name' => '工序模板', 'guard_name' => 'web'],
            ['name' => 'construction.process.instance',      'module' => 'construction', 'display_name' => '工序实例', 'guard_name' => 'web'],
            ['name' => 'construction.process.inspection',    'module' => 'construction', 'display_name' => '验收记录', 'guard_name' => 'web'],

            // ===== 维修中心 =====
            ['name' => 'maintenance.workorder',      'module' => 'maintenance', 'display_name' => '维修工单', 'guard_name' => 'web'],
            ['name' => 'maintenance.repair',         'module' => 'maintenance', 'display_name' => '返修管理', 'guard_name' => 'web'],
            ['name' => 'maintenance.stats',          'module' => 'maintenance', 'display_name' => '维修统计', 'guard_name' => 'web'],
            ['name' => 'maintenance.kanban',         'module' => 'maintenance', 'display_name' => '维修看板', 'guard_name' => 'web'],
            ['name' => 'maintenance.portal_repair',  'module' => 'maintenance', 'display_name' => '返修单',   'guard_name' => 'web'],

            // ===== 巡检计划 =====
            ['name' => 'inspection.overview', 'module' => 'inspection', 'display_name' => '巡检总览', 'guard_name' => 'web'],
            ['name' => 'inspection.plan',     'module' => 'inspection', 'display_name' => '巡检计划', 'guard_name' => 'web'],
            ['name' => 'inspection.task',     'module' => 'inspection', 'display_name' => '执行任务', 'guard_name' => 'web'],
            ['name' => 'inspection.mine',     'module' => 'inspection', 'display_name' => '我的巡检', 'guard_name' => 'web'],
            ['name' => 'inspection.issue',    'module' => 'inspection', 'display_name' => '异常清单', 'guard_name' => 'web'],

            // ===== 报销 =====
            ['name' => 'expense.view',  'module' => 'expense', 'display_name' => '报销列表', 'guard_name' => 'web'],
            ['name' => 'expense.apply', 'module' => 'expense', 'display_name' => '申请报销', 'guard_name' => 'web'],

            // ===== 车辆 =====
            ['name' => 'vehicle.view',        'module' => 'vehicle', 'display_name' => '车辆档案', 'guard_name' => 'web'],
            ['name' => 'vehicle.apply',       'module' => 'vehicle', 'display_name' => '用车申请', 'guard_name' => 'web'],
            ['name' => 'vehicle.dispatch',    'module' => 'vehicle', 'display_name' => '调度管理', 'guard_name' => 'web'],
            ['name' => 'vehicle.insurance',   'module' => 'vehicle', 'display_name' => '保险记录', 'guard_name' => 'web'],
            ['name' => 'vehicle.maintenance', 'module' => 'vehicle', 'display_name' => '保养记录', 'guard_name' => 'web'],
            ['name' => 'vehicle.fuel',        'module' => 'vehicle', 'display_name' => '油卡管理', 'guard_name' => 'web'],

            // ===== 库存 =====
            ['name' => 'inventory.view',     'module' => 'inventory', 'display_name' => '库存总览', 'guard_name' => 'web'],
            ['name' => 'inventory.inout',    'module' => 'inventory', 'display_name' => '出入库',   'guard_name' => 'web'],
            ['name' => 'inventory.inbound',  'module' => 'inventory', 'display_name' => '入库单',   'guard_name' => 'web'],
            ['name' => 'inventory.outbound', 'module' => 'inventory', 'display_name' => '出库单',   'guard_name' => 'web'],
            ['name' => 'inventory.request',  'module' => 'inventory', 'display_name' => '领料单',   'guard_name' => 'web'],
            ['name' => 'inventory.return',   'module' => 'inventory', 'display_name' => '退料单',   'guard_name' => 'web'],

            // ===== 财务 =====
            ['name' => 'finance.overview',        'module' => 'finance', 'display_name' => '财务概览',   'guard_name' => 'web'],
            ['name' => 'finance.receipt',         'module' => 'finance', 'display_name' => '收款单',     'guard_name' => 'web'],
            ['name' => 'finance.payment',         'module' => 'finance', 'display_name' => '付款单',     'guard_name' => 'web'],
            ['name' => 'finance.receivable',      'module' => 'finance', 'display_name' => '应收账款',   'guard_name' => 'web'],
            ['name' => 'finance.payable',         'module' => 'finance', 'display_name' => '应付账款',   'guard_name' => 'web'],
            ['name' => 'finance.supplier_ledger', 'module' => 'finance', 'display_name' => '供应商总账', 'guard_name' => 'web'],
            ['name' => 'finance.customer_ledger', 'module' => 'finance', 'display_name' => '客户总账',   'guard_name' => 'web'],
            ['name' => 'finance.repair_cost',     'module' => 'finance', 'display_name' => '成本报表',   'guard_name' => 'web'],

            // ===== 审批 =====
            ['name' => 'approval.finance',   'module' => 'approval', 'display_name' => '财务审批', 'guard_name' => 'web'],
            ['name' => 'approval.operation', 'module' => 'approval', 'display_name' => '运营审批', 'guard_name' => 'web'],
            ['name' => 'approval.project',   'module' => 'approval', 'display_name' => '项目审批', 'guard_name' => 'web'],

            // ===== 网盘 =====
            ['name' => 'disk.view', 'module' => 'disk', 'display_name' => '公司网盘', 'guard_name' => 'web'],

            // ===== 知识库 =====
            ['name' => 'knowledge.view', 'module' => 'knowledge', 'display_name' => '知识列表', 'guard_name' => 'web'],

            // ===== 消息 =====
            ['name' => 'message.view', 'module' => 'message', 'display_name' => '消息列表', 'guard_name' => 'web'],

            // ===== 系统设置 (业务管理员可见) =====
            ['name' => 'settings.org',             'module' => 'settings', 'display_name' => '组织结构', 'guard_name' => 'web'],
            ['name' => 'settings.role_matrix',     'module' => 'settings', 'display_name' => '权限矩阵', 'guard_name' => 'web'],
            ['name' => 'settings.user',            'module' => 'settings', 'display_name' => '用户管理', 'guard_name' => 'web'],
            ['name' => 'settings.field_mask',      'module' => 'settings', 'display_name' => '字段脱敏', 'guard_name' => 'web'],
            ['name' => 'settings.permission_log',  'module' => 'settings', 'display_name' => '权限日志', 'guard_name' => 'web'],
            ['name' => 'settings.approval',        'module' => 'settings', 'display_name' => '审批中心', 'guard_name' => 'web'],
            ['name' => 'settings.log',             'module' => 'settings', 'display_name' => '系统日志', 'guard_name' => 'web'],
            ['name' => 'settings.backup',          'module' => 'settings', 'display_name' => '数据管理', 'guard_name' => 'web'],
        ];

        foreach ($rows as $r) {
            // 已有就跳过 (idempotent)
            $exists = DB::table('permissions')->where('name', $r['name'])->where('guard_name', $r['guard_name'])->exists();
            if ($exists) continue;
            DB::table('permissions')->insert(array_merge($r, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        // 软删: 真的回滚就清掉这批
        $names = [
            'attendance.overview','attendance.record','attendance.leave','attendance.overtime',
            'attendance.report','attendance.shifts','attendance.groups','attendance.schedule','attendance.mine',
            'employee.view','employee.onboarding','employee.resignation','employee.skill',
            'customer.view','customer.health','customer.pipeline','customer.follow','customer.map',
            'sales.leads','sales.leads.board','sales.opps','sales.opps.board','sales.quote',
            'sales.referrer','sales.settlement','sales.external_quote',
            'project.pool','project.view','project.board','project.calendar','project.create','project.gantt',
            'warranty.view','warranty.expiring','warranty.service','warranty.deposit',
            'purchase.requirement','purchase.order','purchase.detail','purchase.supplier',
            'purchase.tender','purchase.construction_tender','purchase.portal',
            'construction.team','construction.commencement','construction.log','construction.log.daily',
            'construction.rectification','construction.process','construction.external',
            'construction.process.template','construction.process.instance','construction.process.inspection',
            'maintenance.workorder','maintenance.repair','maintenance.stats','maintenance.kanban','maintenance.portal_repair',
            'inspection.overview','inspection.plan','inspection.task','inspection.mine','inspection.issue',
            'expense.view','expense.apply',
            'vehicle.view','vehicle.apply','vehicle.dispatch','vehicle.insurance','vehicle.maintenance','vehicle.fuel',
            'inventory.view','inventory.inout','inventory.inbound','inventory.outbound','inventory.request','inventory.return',
            'finance.overview','finance.receipt','finance.payment','finance.receivable','finance.payable',
            'finance.supplier_ledger','finance.customer_ledger','finance.repair_cost',
            'approval.finance','approval.operation','approval.project',
            'disk.view','knowledge.view','message.view',
            'settings.org','settings.role_matrix','settings.user','settings.field_mask',
            'settings.permission_log','settings.approval','settings.log','settings.backup',
        ];
        DB::table('permissions')->whereIn('name', $names)->delete();
    }
};
