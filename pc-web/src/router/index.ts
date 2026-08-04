import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getToken, removeToken } from '@/utils/auth'
import { useUserStore } from '@/stores/user'
import { startIdleMonitor, stopIdleMonitor } from '@/composables/useIdleTimer'

NProgress.configure({ showSpinner: false })

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/index.vue'),
    meta: { title: '登录', requiresAuth: false }
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    redirect: '/dashboard',
    children: [
      // 工作台：面向所有员工的个人工作入口
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/index.vue'),
        meta: { title: '工作台', icon: 'Odometer' }
      },
      // 旧总览看板地址兼容：合并到老板看板
      {
        path: 'project-overview',
        name: 'ProjectOverview',
        redirect: '/analytics',
        meta: { title: '老板看板', hidden: true }
      },
      // 老板看板：合并原总览看板和 BI 报表
      {
        path: 'analytics',
        name: 'Analytics',
        component: () => import('@/views/analytics/Index.vue'),
        meta: { title: '老板看板', icon: 'DataBoard' }
      },
      {
        path: 'analytics/revenue',
        name: 'AnalyticsRevenue',
        component: () => import('@/views/analytics/Revenue.vue'),
        meta: { title: '营收分析', parent: 'Analytics', hidden: true }
      },
      {
        path: 'analytics/funnel',
        name: 'AnalyticsFunnel',
        component: () => import('@/views/analytics/SalesFunnel.vue'),
        meta: { title: '销售漏斗', parent: 'Analytics', hidden: true }
      },
      {
        path: 'analytics/projects',
        name: 'AnalyticsProjects',
        component: () => import('@/views/analytics/ProjectHealth.vue'),
        meta: { title: '项目健康度', parent: 'Analytics', hidden: true }
      },
      {
        path: 'analytics/rfm',
        name: 'AnalyticsRfm',
        component: () => import('@/views/analytics/CustomerRfm.vue'),
        meta: { title: '客户 RFM', parent: 'Analytics', hidden: true }
      },
      {
        path: 'analytics/inventory',
        name: 'AnalyticsInventory',
        component: () => import('@/views/analytics/InventoryAging.vue'),
        meta: { title: '库存周转', parent: 'Analytics', hidden: true }
      },
      {
        path: 'analytics/pnl',
        name: 'AnalyticsPnl',
        component: () => import('@/views/analytics/FinancePnl.vue'),
        meta: { title: '财务利润表', parent: 'Analytics', hidden: true }
      },
      // ---- 考勤管理 ----
      {
        path: 'attendance',
        name: 'Attendance',
        redirect: '/attendance/overview',
        alias: '/attendance',
        meta: { title: '考勤管理', icon: 'Calendar' },
        children: [
          { path: 'overview', name: 'AttendanceOverview', component: () => import('@/views/attendance/index.vue'), meta: { title: '考勤总览' } },
          { path: 'record', name: 'AttendanceRecord', component: () => import('@/views/attendance/Record.vue'), meta: { title: '打卡记录' } },
          { path: 'leave', name: 'AttendanceLeave', component: () => import('@/views/attendance/Leave.vue'), meta: { title: '请假管理' } },
          { path: 'overtime', name: 'AttendanceOvertime', component: () => import('@/views/attendance/Overtime.vue'), meta: { title: '加班管理' } },
          { path: 'report', name: 'AttendanceReport', component: () => import('@/views/attendance/Report.vue'), meta: { title: '考勤报表' } },
          { path: 'shifts', name: 'AttendanceShifts', component: () => import('@/views/attendance/Shifts.vue'), meta: { title: '班次配置' } },
          { path: 'groups', name: 'AttendanceGroups', component: () => import('@/views/attendance/Groups.vue'), meta: { title: '班组管理' } },
          { path: 'schedule', name: 'AttendanceSchedule', component: () => import('@/views/attendance/Schedule.vue'), meta: { title: '排班计划' } },
          { path: 'my-schedule', name: 'AttendanceMySchedule', component: () => import('@/views/attendance/MySchedule.vue'), meta: { title: '我的排班' } }
        ]
      },
      // ---- 员工管理 ----
      {
        path: 'employee',
        name: 'Employee',
        redirect: '/employee/list',
        alias: '/employee',
        meta: { title: '员工管理', icon: 'User' },
        children: [
          { path: 'list', name: 'EmployeeList', component: () => import('@/views/employee/Organization.vue'), meta: { title: '员工列表' } },
          { path: 'onboardings', name: 'EmployeeOnboardings', component: () => import('@/views/employee/Onboardings.vue'), meta: { title: '入职档案' } },
          { path: 'resignations', name: 'EmployeeResignations', component: () => import('@/views/employee/Resignations.vue'), meta: { title: '离职管理' } },
          { path: 'skill', name: 'EmployeeSkill', component: () => import('@/views/employee/Skill.vue'), meta: { title: '技能标签' } }
        ]
      },
      // ---- 客户管理 ----
      {
        path: 'customer',
        name: 'Customer',
        redirect: '/customer/list',
        alias: '/customer',
        meta: { title: '客户管理', icon: 'OfficeBuilding' },
        children: [
          { path: 'list', name: 'CustomerList', component: () => import('@/views/customer/index.vue'), meta: { title: '客户列表', permission: 'customer.view' } },
          { path: 'follow-calendar', name: 'CustomerFollowCalendar', component: () => import('@/views/customer/FollowCalendar.vue'), meta: { title: '跟进日历' } },
          { path: 'map', name: 'CustomerMap', component: () => import('@/views/customer/CustomerMap.vue'), meta: { title: '客户地图' } },
          { path: ':id', name: 'CustomerDetail', component: () => import('@/views/customer/Detail.vue'), meta: { title: '客户详情', hidden: true }, props: true }
        ]
      },
      // ---- 销售管理 (P0 界面) ----
      {
        path: 'sales',
        name: 'Sales',
        redirect: '/sales/opps',
        alias: '/sales',
        meta: { title: '销售管理', icon: 'Money' },
        children: [
          { path: 'opps', name: 'SalesOpps', component: () => import('@/views/sales/Opps.vue'), meta: { title: '商机池' } },
          { path: 'opps/board', name: 'SalesOppsBoard', component: () => import('@/views/sales/OppsBoard.vue'), meta: { title: '商机看板' } },
          { path: 'opps/:id', name: 'SalesOppDetail', component: () => import('@/views/sales/OppDetail.vue'), meta: { title: '商机详情', hideInMenu: true } },
          { path: 'opps/:id/quote', name: 'SalesQuotes', component: () => import('@/views/sales/Quotes.vue'), meta: { title: '报价单', hideInMenu: true } },
          { path: 'referrers', name: 'SalesReferrers', component: () => import('@/views/sales/Referrers.vue'), meta: { title: '推荐人' } },
          { path: 'settlements', name: 'SalesSettlements', component: () => import('@/views/sales/Settlements.vue'), meta: { title: '居间费结算' } },
          // V1.2.9u 报价看板已删除 (对外报价 → 销售管理 → /sales/opps/:id/quote)
        ]
      },
      // ---- 招标中心 (V0.6.0) — 独立顶级菜单, V0.6.2 整合到「采购协同」后隐藏 ----
      {
        path: 'business/tender',
        name: 'BusinessTender',
        redirect: '/purchase-collab/tender',
        meta: { title: '招标中心', icon: 'Trophy', hidden: true },
        children: [
          { path: 'list', name: 'TenderList', component: () => import('@/views/business/tender/index.vue'), meta: { title: '招标项目' } },
          { path: 'detail/:id', name: 'TenderDetail', component: () => import('@/views/business/tender/Detail.vue'), meta: { title: '招标详情', hidden: true }, props: true }
        ]
      },
      // ---- 项目管理 — 合同/钱/质保期全流程 ----
      {
        path: 'project',
        name: 'Project',
        redirect: '/project/list',
        meta: { title: '项目管理', icon: 'Files' },
        children: [
          { path: '', redirect: '/project/list' },
          { path: 'pool', name: 'ProjectPool', component: () => import('@/views/project/Pool.vue'), meta: { title: '项目池' } },
          { path: 'list', name: 'ProjectList', component: () => import('@/views/project/index.vue'), meta: { title: '项目列表', permission: 'project.view' } },
          { path: 'board', name: 'ProjectBoard', component: () => import('@/views/project/Board.vue'), meta: { title: '项目看板' } },
          { path: 'calendar', name: 'ProjectCalendar', component: () => import('@/views/project/Calendar.vue'), meta: { title: '付款日历', hidden: true } },
          { path: 'create', name: 'ProjectCreate', component: () => import('@/views/project/Create.vue'), meta: { title: '创建项目', hidden: true } },
          { path: 'detail/:id', name: 'ProjectDetail', component: () => import('@/views/project/Detail.vue'), meta: { title: '项目详情', hidden: true }, props: true },
          { path: 'gantt/:id', name: 'ProjectGantt', component: () => import('@/views/project/Gantt.vue'), meta: { title: '施工图' }, props: true },
          // 总览看板已在顶级路由 (/project-overview)
          // V1.2.12p: 质保期 (菜单归到项目管理)
          { path: 'warranty/list', name: 'WarrantyList', component: () => import('@/views/warranty/Index.vue'), meta: { title: '质保期列表', permission: 'warranty.view' } },
          // 老 /warranty/create 已改为列表页弹窗，保留 redirect 防旧链接 404
          { path: 'warranty/create', redirect: '/project/warranty/list' },
          { path: 'warranty/detail/:id', name: 'WarrantyDetail', component: () => import('@/views/warranty/Detail.vue'), meta: { title: '质保期详情', hidden: true }, props: true },
          { path: 'warranty/expiring', name: 'WarrantyExpiring', component: () => import('@/views/warranty/Expiring.vue'), meta: { title: '即将到期' } },
          { path: 'warranty/deposit', name: 'WarrantyDepositList', component: () => import('@/views/warranty/Deposit.vue'), meta: { title: '质保金' } },
          { path: 'warranty/deposit/detail/:id', name: 'WarrantyDepositDetail', component: () => import('@/views/warranty/DepositDetail.vue'), meta: { title: '质保金详情', hidden: true }, props: true }
        ]
      },
      // 兼容老路径: /project/process/* → /construction/process/* (老项目路径)
      { path: 'project/process/templates', redirect: '/process/templates' },
      { path: 'project/process/instances', redirect: '/process/instances' },
      { path: 'project/process/inspections', redirect: '/process/inspections' },
      { path: 'project/process/instances/detail/:id', redirect: '/process/instances/detail/:id' },
      // V1.2.9u: 报价看板已删, 老路径重定向到销售管理首页
      { path: 'external-quote', redirect: '/sales' },
      // ---- 采购管理 (v0.3.10) - 放在「施工管理」后面 ----
      // ---- 采购协同 (V0.6.2 一级菜单, 整合采购单/供应商/招标/施工发包) ----
      {
        path: 'purchase-collab',
        name: 'PurchaseCollab',
        redirect: '/purchase-collab/requirement',
        meta: { title: '采购协同', icon: 'Connection' },
        children: [
          // 1. 采购单子模块
          { path: 'requirement', name: 'CollabRequirement', component: () => import('@/views/purchase/Requirement.vue'), meta: { title: '采购需求' } },
          { path: 'order', name: 'CollabOrder', component: () => import('@/views/purchase/Plan.vue'), meta: { title: '采购计划' } },
          { path: 'contract', name: 'CollabContract', component: () => import('@/views/purchase/Contract.vue'), meta: { title: '采购合同' } },
          // V0.6.2.2: "采购详情" 路由指向新 PurchaseDetail.vue (按订单号 PO 聚合, 4 Tab: 基础/合同/付款/发货)
          { path: 'receive', name: 'CollabReceive', component: () => import('@/views/purchase/PurchaseDetail.vue'), meta: { title: '采购详情' } },
          // 2. 供应商库
          { path: 'supplier', name: 'CollabSupplier', component: () => import('@/views/supplier/index.vue'), meta: { title: '供应商库' } },
          { path: 'supplier/:id', name: 'CollabSupplierDetail', component: () => import('@/views/supplier/Detail.vue'), meta: { title: '供应商详情', hidden: true }, props: true },
          // 注: 联系人已合并到供应商库 (V0.6.3)
          // 3. 招标中心 (原 /business/tender 挪过来, 别名兼容老路径)
          { path: 'tender', name: 'CollabTender', component: () => import('@/views/business/tender/index.vue'), meta: { title: '招标中心' } },
          { path: 'tender/detail/:id', name: 'CollabTenderDetail', component: () => import('@/views/business/tender/Detail.vue'), meta: { title: '招标详情', hidden: true }, props: true },
          // 4. 外部施工招标 (菜单已隐藏, 统一归招标中心)
          { path: 'construction-tender', name: 'CollabConstructionTender', component: () => import('@/views/construction/external-work/index.vue'), meta: { title: '施工招标', hidden: true } },
          { path: 'construction-tender/:id', name: 'CollabConstructionTenderDetail', component: () => import('@/views/construction/external-work/Detail.vue'), meta: { title: '发包详情', hidden: true }, props: true },
          // 5. 供应商门户 (后台配置 - 当前是 portal 首页的 iframe/外链, 这里展示"链接 + 访问记录")
          { path: 'portal-config', name: 'CollabPortalConfig', component: () => import('@/views/portal/tender/Index.vue'), meta: { title: '门户管理' } }
        ]
      },
      // ---- 维修中心 (质保期已归项目管理, 巡检归入本菜单) ----
      {
        path: 'after-sales',
        name: 'AfterSales',
        redirect: '/after-sales/work-orders',
        meta: { title: '维修中心', icon: 'SetUp' },
        children: [
          // 维修 — 实际路径仍在 /maintenance/*
          { path: 'work-orders',     redirect: '/maintenance/work-orders',     meta: { title: '维修工单' } },
          { path: 'repairs',         redirect: '/maintenance/repairs',         meta: { title: '返修管理' } },
          { path: 'stats',           redirect: '/maintenance/stats',           meta: { title: '维修统计' } },
          { path: 'kanban',          redirect: '/maintenance/kanban',          meta: { title: '维修看板' } },
          { path: 'contract',        redirect: '/maintenance/contract',        meta: { title: '维保合同' } },
          // 巡检 — 实际路径仍在 /inspection/*
          { path: 'overview',        redirect: '/inspection/overview',         meta: { title: '巡检总览' } },
          { path: 'plans',           redirect: '/inspection/plans',            meta: { title: '巡检计划' } },
          { path: 'tasks',           redirect: '/inspection/tasks',            meta: { title: '执行任务' } },
          { path: 'tasks/mine',      redirect: '/inspection/tasks/mine',       meta: { title: '我的巡检' } },
          { path: 'issues',          redirect: '/inspection/issues',           meta: { title: '异常清单' } },
        ]
      },
      // ---- 施工管理 (V0.4.3) — 施工团队/开工单/日志/发包 ----
      {
        path: 'construction',
        name: 'Construction',
        redirect: '/construction/team',
        alias: '/construction',
        meta: { title: '施工管理', icon: 'Tools' },
        children: [
          // 施工团队
          { path: 'team',         name: 'ConstructionTeam',         component: () => import('@/views/construction/team/index.vue'),          meta: { title: '施工团队' } },
          { path: 'team/:id',     name: 'ConstructionTeamDetail',  component: () => import('@/views/construction/team/Detail.vue'),        meta: { title: '团队详情', hidden: true }, props: true },
          // 开工单
          { path: 'commencement', name: 'ConstructionCommencement', component: () => import('@/views/construction/commencement/index.vue'), meta: { title: '开工单' } },
          { path: 'commencement/:id', name: 'ConstructionCommencementDetail', component: () => import('@/views/construction/commencement/Detail.vue'), meta: { title: '开工详情', hidden: true }, props: true },
          // 施工日志
          { path: 'log',          name: 'ConstructionLog',         component: () => import('@/views/construction/log/index.vue'),         meta: { title: '施工日志' } },
          { path: 'log/daily',    name: 'ConstructionLogDaily',     component: () => import('@/views/construction/log/DailyReport.vue'),   meta: { title: '每日上报' } },
          // 整改工单
          { path: 'rectification', name: 'ConstructionRectification', component: () => import('@/views/construction/rectification/index.vue'), meta: { title: '整改工单' } },
          { path: 'rectification/:id', name: 'ConstructionRectificationDetail', component: () => import('@/views/construction/rectification/Detail.vue'), meta: { title: '整改详情', hidden: true }, props: true },
          // 验收记录 (从工序管理移入)
          { path: 'inspections',  name: 'ConstructionInspections',  component: () => import('@/views/process/InspectionList.vue'),    meta: { title: '工序验收' } },
          // 工序字典 (已归到工序管理顶级路由, 保留 redirect 防旧链接 404)
          { path: 'work-process', redirect: '/process/work-process' },
          // 施工发包 (菜单已隐藏, 统一归招标中心)
          { path: 'external-work',     name: 'ConstructionExternalWork',        component: () => import('@/views/construction/external-work/index.vue'), meta: { title: '施工发包', hidden: true } },
          { path: 'external-work/:id', name: 'ConstructionExternalWorkDetail', component: () => import('@/views/construction/external-work/Detail.vue'),  meta: { title: '发包详情', hidden: true }, props: true },
          // 外部供应商投标（无 auth 要求,放 MainLayout 内但不影响登录态)
          { path: 'external-work/bid/:id', name: 'ConstructionBidForm', component: () => import('@/views/construction/external-work/BidForm.vue'), meta: { title: '投标申请', hidden: true }, props: true },
          // 工序管理旧路径兼容
          { path: 'process/templates', redirect: '/process/templates' },
          { path: 'process/instances', redirect: '/process/instances' },
          { path: 'process/inspections', redirect: '/process/inspections' },
          { path: 'process/instances/detail/:id', redirect: '/process/instances/detail/:id' },
        ]
      },
      // ---- 工序管理 (V1.2.12p 从施工管理独立为一级菜单) ----
      {
        path: 'process',
        name: 'ProcessManagement',
        redirect: '/process/work-process',
        meta: { title: '工序管理', icon: 'List' },
        children: [
          { path: 'work-process', name: 'ProcessWorkProcess', component: () => import('@/views/construction/work-process/index.vue'), meta: { title: '工序字典' } },
          { path: 'templates',    name: 'ProcessTemplates',    component: () => import('@/views/process/TemplateList.vue'),       meta: { title: '工序模板' } },
          { path: 'instances',    name: 'ProcessInstances',    component: () => import('@/views/process/InstanceList.vue'),       meta: { title: '工序实例' } },
          { path: 'inspections',  name: 'ProcessInspections',  component: () => import('@/views/process/InspectionList.vue'),    meta: { title: '工序验收', hidden: true } },
          // v1.2.12p 详情改为弹窗模式, 独立路由保留 redirect 防老链接 404
{ path: 'instances/detail/:id', redirect: '/process/instances' },
        ]
      },
      // ---- 维修中心 (V0.5.5 改名: 售后服务 → 维修中心) — 菜单已归到售后管理 ----
      {
        path: 'maintenance',
        name: 'Maintenance',
        redirect: '/maintenance/work-orders',
        alias: '/maintenance',
        meta: { title: '维修中心', icon: 'SetUp', hidden: true },
        children: [
          { path: 'work-orders', name: 'MaintenanceWorkOrders', component: () => import('@/views/maintenance/WorkOrderList.vue'), meta: { title: '维修工单' } },
          { path: 'work-orders/:id', name: 'MaintenanceWorkOrderDetail', component: () => import('@/views/maintenance/WorkOrderDetail.vue'), meta: { title: '工单详情', hidden: true }, props: true },
          { path: 'work-orders/create', name: 'MaintenanceWorkOrderCreate', redirect: '/maintenance/work-orders', meta: { title: '创建工单', hidden: true } },
          { path: 'repairs', name: 'MaintenanceRepairs', component: () => import('@/views/maintenance/RepairList.vue'), meta: { title: '返修管理' } },
          { path: 'repairs/:id', name: 'MaintenanceRepairDetail', component: () => import('@/views/maintenance/RepairDetail.vue'), meta: { title: '返修详情', hidden: true }, props: true },
          { path: 'repairs/create', name: 'MaintenanceRepairCreate', redirect: '/maintenance/repairs', meta: { title: '新建返修', hidden: true } },
          { path: 'stats', name: 'MaintenanceStats', component: () => import('@/views/maintenance/Stats.vue'), meta: { title: '维修统计' } },
          { path: 'kanban', name: 'MaintenanceKanban', component: () => import('@/views/maintenance/Kanban.vue'), meta: { title: '维修看板' } },
          { path: 'contract', name: 'MaintenanceContract', component: () => import('@/views/service/Contract.vue'), meta: { title: '维保合同' } },
          // V0.5.7 块3 — 返修进度查询 (内嵌给财务/管理员查看)
          { path: 'portal-repair', name: 'MaintenancePortalRepair', component: () => import('@/views/portal/Repair.vue'), meta: { title: '返修单' } }
        ]
      },
      // ---- 公开直链 /portal/repair (外部客户用, 不进菜单) ----
      {
        path: 'portal/repair',
        name: 'PortalRepair',
        component: () => import('@/views/portal/Repair.vue'),
        meta: { title: '返修查询', public: true, noAuth: true, hidden: true },
      },
      // ---- 公开直链 /portal/tender (供应商投标, 不进菜单) ----
      {
        path: 'portal/tender',
        name: 'PortalTender',
        component: () => import('@/views/portal/tender/Index.vue'),
        meta: { title: '招标中心 · 供应商门户', public: true, noAuth: true, hidden: true },
      },
      {
        path: 'portal/tender/:token',
        name: 'PortalTenderBid',
        component: () => import('@/views/portal/tender/BidForm.vue'),
        meta: { title: '在线投标', public: true, noAuth: true, hidden: true },
      },
      // ---- 旧 service 路径重定向到 maintenance (V0.5.5 兼容期) ----
      {
        path: 'service',
        redirect: '/maintenance/work-orders',
        meta: { hidden: true }
      },
      // ---- V0.7 巡检计划 ----
      {
        path: 'inspection',
        name: 'Inspection',
        redirect: '/inspection/plans',
        meta: { title: '巡检计划', icon: 'CircleCheck', hidden: true },
        children: [
          { path: 'overview', name: 'InspectionOverview', component: () => import('@/views/inspection/Overview.vue'), meta: { title: '巡检总览' } },
          { path: 'plans', name: 'InspectionPlans', component: () => import('@/views/inspection/PlanList.vue'), meta: { title: '巡检计划' } },
          { path: 'plans/create', name: 'InspectionPlanCreate', component: () => import('@/views/inspection/PlanForm.vue'), meta: { title: '新建巡检计划', hidden: true } },
          { path: 'plans/detail/:id', name: 'InspectionPlanDetail', component: () => import('@/views/inspection/PlanDetail.vue'), meta: { title: '巡检计划详情', hidden: true }, props: true },
          { path: 'tasks', name: 'InspectionTasks', component: () => import('@/views/inspection/TaskList.vue'), meta: { title: '执行任务' } },
          { path: 'tasks/mine', name: 'InspectionMyTasks', component: () => import('@/views/inspection/MyTasks.vue'), meta: { title: '我的巡检' } },
          { path: 'tasks/checkin/:id', name: 'InspectionCheckin', component: () => import('@/views/inspection/Checkin.vue'), meta: { title: '现场打卡', hidden: true }, props: true },
          { path: 'issues', name: 'InspectionIssues', component: () => import('@/views/inspection/IssueList.vue'), meta: { title: '异常清单' } }
        ]
      },
      // ---- 报销管理 ----
      {
        path: 'expense',
        name: 'Expense',
        redirect: '/expense/list',
        alias: '/expense',
        meta: { title: '报销管理', icon: 'Money' },
        children: [
          { path: 'list', name: 'ExpenseList', component: () => import('@/views/expense/index.vue'), meta: { title: '报销列表' } },
          { path: 'apply', name: 'ExpenseApply', component: () => import('@/views/expense/Apply.vue'), meta: { title: '申请报销', hidden: true } }
        ]
      },
      // ---- 车辆管理 ----
      {
        path: 'vehicle',
        name: 'Vehicle',
        redirect: '/vehicle/fleet',
        alias: '/vehicle',
        meta: { title: '车辆管理', icon: 'Van' },
        children: [
          { path: 'fleet', name: 'VehicleFleet', component: () => import('@/views/vehicle/index.vue'), meta: { title: '车辆档案' } },
          { path: 'apply', name: 'VehicleApply', component: () => import('@/views/vehicle/Apply.vue'), meta: { title: '用车申请', hidden: true } },
          { path: 'dispatch', name: 'VehicleDispatch', component: () => import('@/views/vehicle/Dispatch.vue'), meta: { title: '调度管理' } },
          { path: 'insurance', name: 'VehicleInsurance', component: () => import('@/views/vehicle/Insurance.vue'), meta: { title: '保险记录' } },
          { path: 'maintenance', name: 'VehicleMaintenance', component: () => import('@/views/vehicle/Maintenance.vue'), meta: { title: '保养记录' } },
          { path: 'fuel-card', name: 'VehicleFuelCard', component: () => import('@/views/vehicle/FuelCard.vue'), meta: { title: '油卡管理' } }
        ]
      },
      // ---- 库存管理 (P1) ----
      {
        path: 'inventory',
        name: 'Inventory',
        redirect: '',
        alias: '/inventory',
        meta: { title: '库存管理', icon: 'Box' },
        children: [
          { path: '', name: 'InventoryStock', component: () => import('@/views/inventory/index.vue'), meta: { title: '库存总览' } },
          { path: 'inout', name: 'InventoryInOut', component: () => import('@/views/inventory/InOut.vue'), meta: { title: '出入库' } },
          { path: 'inbound-order', name: 'InventoryInboundOrder', component: () => import('@/views/inventory/InboundOrder.vue'), meta: { title: '入库单' } },
          { path: 'outbound-order', name: 'InventoryOutboundOrder', component: () => import('@/views/inventory/OutboundOrder.vue'), meta: { title: '出库单' } },
          { path: 'material-request', name: 'InventoryMaterialRequest', component: () => import('@/views/inventory/MaterialRequest.vue'), meta: { title: '领料单' } },
          { path: 'material-return', name: 'InventoryMaterialReturn', component: () => import('@/views/inventory/MaterialReturn.vue'), meta: { title: '退料单' } },
          { path: 'tool-usage-order', name: 'InventoryToolUsageOrder', component: () => import('@/views/inventory/ToolUsageOrder.vue'), meta: { title: '工具使用单' } },
          { path: 'tool-usage-detail', name: 'InventoryToolUsageDetail', component: () => import('@/views/inventory/ToolUsageDetail.vue'), meta: { title: '工具使用单明细' } },
          { path: 'warehouse-manage', name: 'InventoryWarehouseManage', component: () => import('@/views/inventory/WarehouseManage.vue'), meta: { title: '仓库管理' } },
          { path: 'stock-transfer', name: 'InventoryStockTransfer', component: () => import('@/views/inventory/StockTransfer.vue'), meta: { title: '调拨单' } }
        ]
      },
      // ---- 财务管理 (P1) ----
      {
        path: 'finance',
        name: 'Finance',
        redirect: '/finance/overview',
        alias: '/finance',
        meta: { title: '财务管理', icon: 'Wallet' },
        children: [
          { path: 'overview', name: 'FinanceOverview', component: () => import('@/views/finance/index.vue'), meta: { title: '财务概览' } },
          { path: 'account', name: 'FinanceAccount', component: () => import('@/views/finance/AccountManagement.vue'), meta: { title: '资金账户管理' } },
          // V1.2.16: 内部转账明细二级菜单
          { path: 'internal-transfer', name: 'FinanceInternalTransfer', component: () => import('@/views/finance/InternalTransfer.vue'), meta: { title: '内部转账' } },
          { path: 'receipt', name: 'FinanceReceipt', component: () => import('@/views/finance/Receipt.vue'), meta: { title: '收款单' } },
          { path: 'payment', name: 'FinancePayment', component: () => import('@/views/finance/Payment.vue'), meta: { title: '付款单' } },
          { path: 'receivable', name: 'FinanceReceivable', component: () => import('@/views/finance/Receivable.vue'), meta: { title: '应收账款' } },
          { path: 'payable', name: 'FinancePayable', component: () => import('@/views/finance/Payable.vue'), meta: { title: '应付账款' } },
          { path: 'supplier-ledger', name: 'FinanceSupplierLedger', component: () => import('@/views/finance/supplier-ledger.vue'), meta: { title: '供应商总账' } },
          { path: 'customer-ledger', name: 'FinanceCustomerLedger', component: () => import('@/views/finance/customer-ledger.vue'), meta: { title: '客户总账' } },
          // V0.5.7 块4 — 维修成本报表
          { path: 'repair-cost', name: 'FinanceRepairCost', component: () => import('@/views/finance/RepairCostReport.vue'), meta: { title: '成本报表' } },
          { path: 'profit-report', name: 'FinanceProfitReport', component: () => import('@/views/finance/ProfitReport.vue'), meta: { title: '项目利润表' } },
          { path: 'invoice', name: 'FinanceInvoice', component: () => import('@/views/finance/Invoice.vue'), meta: { title: '发票管理' } },
          { path: 'fixed-assets', name: 'FinanceFixedAssets', component: () => import('@/views/finance/FixedAssets.vue'), meta: { title: '固定资产管理' } }
        ]
      },
      // ---- 供应商管理 (V0.4.2) — V0.6.2 整合到「采购协同」后隐藏 ----
      {
        path: 'supplier',
        name: 'Supplier',
        redirect: '/purchase-collab/supplier',
        alias: '/supplier',
        meta: { title: '供应商', icon: 'OfficeBuilding', hidden: true },
        children: [
          { path: 'list', name: 'SupplierList', component: () => import('@/views/supplier/index.vue'), meta: { title: '供应商列表' } },
          { path: ':id', name: 'SupplierDetail', component: () => import('@/views/supplier/Detail.vue'), meta: { title: '供应商详情', hidden: true }, props: true }
        ]
      },
      // ---- 对外报价 (V0.4.2) - 已彻底删除 (V1.2.9u), 不再保留 ----
      // ---- 工序管理 (V1.1) - 已移到「施工管理」下, 保留 /process/* 兼容路径 ----
      // ---- 审批中心 (v0.3.10) - 3 模块分类 ----
      {
        path: 'approval',
        name: 'Approval',
        redirect: '/approval/finance',
        alias: '/approval',
        meta: { title: '审批中心', icon: 'CircleCheck' },
        children: [
          { path: 'finance', name: 'ApprovalFinance', component: () => import('@/views/approval/finance/Index.vue'), meta: { title: '财务审批' } },
          { path: 'operation', name: 'ApprovalOperation', component: () => import('@/views/approval/operation/Index.vue'), meta: { title: '运营审批' } },
          { path: 'project', name: 'ApprovalProject', component: () => import('@/views/approval/project/Index.vue'), meta: { title: '项目审批' } }
        ]
      },
      // ---- 公司网盘 (P1) ----
      {
        path: 'disk',
        name: 'Disk',
        component: () => import('@/views/disk/index.vue'),
        meta: { title: '公司网盘', icon: 'FolderOpened' }
      },
      // ---- 知识库 (P1) ----
      {
        path: 'knowledge',
        name: 'Knowledge',
        redirect: '/knowledge/list',
        alias: '/knowledge',
        meta: { title: '知识库', icon: 'Reading' },
        children: [
          { path: 'list', name: 'KnowledgeList', component: () => import('@/views/knowledge/index.vue'), meta: { title: '知识列表' } }
        ]
      },
      // ---- 数据大屏 (P2) ----
      {
        path: 'screen',
        name: 'Screen',
        component: () => import('@/views/screen/index.vue'),
        meta: { title: '数据大屏', icon: 'DataAnalysis' }
      },
      // ---- 消息中心 ----
      {
        path: 'message',
        name: 'Message',
        redirect: '/message/list',
        alias: '/message',
        meta: { title: '消息中心', icon: 'Bell' },
        children: [
          { path: 'list', name: 'MessageList', component: () => import('@/views/message/index.vue'), meta: { title: '消息列表' } }
        ]
      },
      // ---- 系统设置 ----
      {
        path: 'settings',
        name: 'Settings',
        redirect: '/settings/profile',
        alias: '/settings',
        // V1.2.4k: 系统设置菜单对所有登录用户可见 (含 business 业务管理员)
        // 子菜单里通过 permission/special 标记控制细粒度访问
        meta: { title: '系统设置', icon: 'Setting' },
        children: [
          // V1.2.1: 个人中心/改密/我的权限 — 所有登录用户通用
          { path: 'profile', name: 'SettingsProfile', component: () => import('@/views/settings/Profile.vue'), meta: { title: '个人信息' } },
          { path: 'password', name: 'SettingsPassword', component: () => import('@/views/settings/Password.vue'), meta: { title: '修改密码' } },
          { path: 'my-permissions', name: 'SettingsMyPermissions', component: () => import('@/views/settings/MyPermissions.vue'), meta: { title: '我的权限' } },
          // V1.2.4k: 组织/角色/用户/字段脱敏/权限日志 — 业务管理员 (绑了 admin 角色) 也能看
          { path: 'organization', name: 'SettingsOrg', component: () => import('@/views/settings/Organization.vue'), meta: { title: '组织结构' } },
          { path: 'role/matrix', name: 'SettingsRoleMatrix', component: () => import('@/views/settings/role/Matrix.vue'), meta: { title: '权限矩阵' } },
          { path: 'field-mask', name: 'SettingsFieldMask', component: () => import('@/views/settings/FieldMask.vue'), meta: { title: '字段脱敏' } },
          { path: 'permission-log', name: 'SettingsPermissionLog', component: () => import('@/views/settings/PermissionLog.vue'), meta: { title: '权限日志' } },
          // 业务管理员也能进: 审批/日志/备份
          { path: 'approval', name: 'SettingsApproval', component: () => import('@/views/settings/approval/Index.vue'), meta: { title: '审批流程' } },
          { path: 'log', name: 'SettingsLog', component: () => import('@/views/settings/log/Index.vue'), meta: { title: '系统日志' } },
          { path: 'backup', name: 'SettingsBackup', component: () => import('@/views/settings/Backup.vue'), meta: { title: '数据管理' } },
          // V1.3.2: 期初数据 (业务管理员 admin 角色可见)
          { path: 'opening-balances', name: 'SettingsOpeningBalances', component: () => import('@/views/settings/OpeningBalances.vue'), meta: { title: '期初数据', permission: 'setting.view' } },
          // V1.2.4k: 初始化向导/字典/监控 — 只对 system 可见 (systemOnly)
          { path: 'wizard', name: 'SettingsWizard', component: () => import('@/views/settings/SetupWizard.vue'), meta: { title: '系统初始化', systemOnly: true } },
          { path: 'dict', name: 'SettingsDict', component: () => import('@/views/settings/SystemDict.vue'), meta: { title: '数据字典', systemOnly: true } },
          { path: 'monitor', name: 'SettingsMonitor', component: () => import('@/views/settings/SystemMonitor.vue'), meta: { title: '系统监控', systemOnly: true } }
        ]
      }
    ]
  },
  // V1.1: system 账号专属入口 /admin
  {
    path: '/admin',
    name: 'Admin',
    component: () => import('@/layouts/MainLayout.vue'),
    redirect: '/admin/welcome',
    meta: { title: '系统管理', icon: 'Setting', systemOnly: true },
    children: [
      {
        path: 'welcome',
        name: 'AdminWelcome',
        component: () => import('@/views/admin/Welcome.vue'),
        meta: { title: '系统首页' }
      },
      {
        // V1.2: 初始化向导 (建业务管理员 + 系统信息)
        path: 'wizard',
        name: 'AdminWizard',
        component: () => import('@/views/admin/Wizard.vue'),
        meta: { title: '初始化向导' }
      }
    ]
  },
  // V1.2: 强制改密页 (白名单, 不走 MainLayout)
  {
    path: '/change-password',
    name: 'ChangePassword',
    component: () => import('@/views/auth/ChangePassword.vue'),
    meta: { title: '修改密码', requiresAuth: false }
  },
  // 错误页（独立 layout,不走 MainLayout）
  {
    path: '/error/404',
    name: 'Error404',
    component: () => import('@/views/error/404.vue'),
    meta: { title: '页面不存在', requiresAuth: false }
  },
  {
    path: '/error/500',
    name: 'Error500',
    component: () => import('@/views/error/500.vue'),
    meta: { title: '服务异常', requiresAuth: false }
  },
  {
    path: '/error/network',
    name: 'ErrorNetwork',
    component: () => import('@/views/error/NetworkError.vue'),
    meta: { title: '网络断开', requiresAuth: false }
  },
  // 法律页（不需登录）
  {
    path: '/legal/agreement',
    name: 'LegalAgreement',
    component: () => import('@/views/legal/Agreement.vue'),
    meta: { title: '用户服务协议', requiresAuth: false }
  },
  {
    path: '/legal/privacy',
    name: 'LegalPrivacy',
    component: () => import('@/views/legal/Privacy.vue'),
    meta: { title: '隐私政策', requiresAuth: false }
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('@/views/error/403.vue'),
    meta: { title: '403 权限不足' }
  },
  // 404 兜底（必须在最后）
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/error/404.vue'),
    meta: { title: '404' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 白名单：不需要登录就能访问的页面
const WHITE_LIST = [
  '/login',
  '/change-password',  // V1.2: 强制改密页 (要求已登录, 但路由守卫不要拦)
  '/error/404',
  '/error/500',
  '/error/network',
  '/legal/agreement',
  '/legal/privacy',
  '/portal/repair',  // V0.5.7 块3 — 客户公开查询
  '/portal/tender',  // V0.6.0 — 供应商招标门户首页
  // /portal/tender/:token — 公开投标页, 路径级白名单(用 to.matched[0]?.path 通配)见 beforeEach
]

// 路由守卫
router.beforeEach(async (to, from, next) => {
  NProgress.start()
  // 用 systemConfigStore 拿动态系统名；如果未加载（登录页/初次进入），用兜底
  try {
    const { useSystemConfigStore } = await import('@/stores/systemConfig')
    const store = useSystemConfigStore()
    const sysName = store.sysConfig.systemName || 'OA 办公系统'
    document.title = to.meta.title ? `${to.meta.title} - ${sysName}` : sysName
  } catch {
    document.title = to.meta.title ? `${to.meta.title} - OA 办公系统` : 'OA 办公系统'
  }

  const token = getToken()
  // 白名单匹配: 精确路径 + 前缀通配(noAuth 路由用, 任意 token 后路径)
  const isWhiteList = WHITE_LIST.includes(to.path)
    || to.meta?.requiresAuth === false
    || to.meta?.noAuth === true
    || to.path.startsWith('/portal/tender/')  // V0.6.0 供应商投标详情页免登录

  // 白名单页面直接放行
  if (isWhiteList) {
    // 已登录用户访问 /login → 直接跳到首页（避免来回跳转）
    if (to.path === '/login' && token) {
      // V1.2: 兜底检查, 登录页也得能跳到 /change-password
      next('/')
      return
    }
    // V1.2: /change-password 也走白名单 (不能被路由守卫拦截)
    // 离开业务页面 → 停止闲置计时
    stopIdleMonitor()
    next()
    return
  }

  // ⚠️ 关键修复：兜底防御 — 修复 localStorage token/userInfo 不一致问题
  // 场景：单独 removeToken() 后,userInfo 残留,守卫会误判为已登录
  if (!token) {
    // 清理任何残留的 userInfo,保证状态一致
    try {
      const { clearAuth } = await import('@/utils/auth')
      clearAuth()
    } catch { /* 忽略 */ }
    next({ path: '/login', query: { redirect: to.fullPath } })
    return
  }

  // 有 token — 必须**先验证 token 在后端真的有效**,再放行
  // 避免: token 过期 → 守卫通过 → 进入 dashboard → 多个组件并发请求触发一堆 401 弹框
  const userStore = useUserStore()
  try {
    if (!userStore.userInfo) {
      await userStore.getUserInfoAction()
    }
  } catch {
    // token 失效 → 清空一切 + 跳 login
    stopIdleMonitor()
    try { userStore.logout() } catch { /* 忽略 */ }
    try {
      const { clearAuth } = await import('@/utils/auth')
      clearAuth()
    } catch { /* 忽略 */ }
    next({ path: '/login', query: { redirect: to.fullPath } })
    return
  }

  // 业务页面验证通过 → 启动/重置闲置计时(30分钟)
  startIdleMonitor()

  // ====== V1.2: 强制改密 (放在最前, 比 system 隔离更优先) ======
  const mustChangePwd = (userStore.userInfo as Record<string, unknown>)?.must_change_password ?? false
  if (mustChangePwd && to.path !== '/change-password') {
    // V1.2.4: 兜底 — 如果是 system 账号, 实时拉 /settings/super-admin 确认 must_change 状态
    // 防止前端 store 的 must_change_password 缓存导致"已改完但还卡在改密页"
    const u = userStore.userInfo as Record<string, unknown>
    if (u?.username === 'system' || u?.is_system === true) {
      try {
        const sa = await fetch('/api/settings/super-admin', {
          headers: { Authorization: `Bearer ${userStore.token || ''}` }
        }).then(r => r.json()).catch(() => null)
        if (sa?.code === 0 && sa?.data?.has_password === true && !sa?.data?.must_set_password) {
          // 后端说 system 已经有密码了 → 同步前端 store, 跳走
          if (userStore.userInfo) {
            userStore.userInfo.must_change_password = false
          }
          ElMessage.info('检测到您已设置过超级管理员密码, 直接进入系统')
          next(u?.user_type === 'system' ? '/admin/welcome' : '/dashboard')
          return
        }
      } catch {}
    }
    ElMessage.warning('首次登录, 请先修改默认密码')
    next('/change-password')
    return
  }

  // ====== V1.1 admin 隔离: 按 user_type 分流 ======
  // system 用户只能进系统管理页, 不能进业务页
  // business 用户 (manager/user/finance) 只能进业务页, 不能进系统管理页
  const userType = (userStore.userInfo as Record<string, unknown>)?.user_type ?? 'business'
  const path = to.path

  // V1.2.3: 所有用户通用路径 (个人中心/改密/我的权限/通知) — 不算 system 专属, 任何登录用户可访问
  const isCommonUserPath = path === '/settings/profile'
    || path === '/settings/password'
    || path === '/settings/my-permissions'
    || path === '/notifications'
    || path.startsWith('/settings/profile')
    || path.startsWith('/settings/password')
    || path.startsWith('/settings/my-permissions')
    || path.startsWith('/notifications')

  const isSystemPath = !isCommonUserPath && (
       path === '/admin'
    || path.startsWith('/admin/')
    || path === '/settings'
    || path.startsWith('/settings/')
    || path === '/system-logs'
    || path === '/license'
    || path === '/init-wizard'
    || path === '/settings/wizard'  // V1.1: 复用现有 SetupWizard
    || path === '/change-password'  // V1.2: 强制改密也是 system 专属
  )

  // V1.1: 初始化向导对所有用户可见 (system 引导业务用户, business 走流程)
  // 业务页面排除白名单
  const isBusinessPath = !isSystemPath
    && path !== '/login'
    && path !== '/'
    && path !== '/dashboard'
    && path !== '/admin/welcome'
    && !isCommonUserPath

  // system 用户访问业务页 → 跳管理后台
  if (userType === 'system' && (isBusinessPath || path === '/dashboard' || path === '/' || path === '/login')) {
    // V1.2: 没初始化时跳 wizard
    if ((userStore.userInfo as Record<string, unknown>)?.system_initialized === false) {
      ElMessage.info('请先完成初始化向导')
      next('/admin/wizard')
      return
    }
    ElMessage.warning('系统账号不参与业务流程')
    next('/admin')
    return
  }

  // business 用户访问系统页 → 跳工作台
  // V1.2.4k: business 业务管理员可以访问系统设置菜单 (除 wizard/dict/monitor 外)
  // V1.2.4x: 进一步开放 — system-logs 也对业务管理员开放 (有 system.log 权限即可)
  // 但 wizard/dict/monitor/license/admin/* 仍只对 system 开放
  const isSystemOnlyPath = path === '/admin'
    || path.startsWith('/admin/')
    || path === '/license'
    || path === '/settings/wizard'
    || path === '/settings/dict'
    || path === '/settings/monitor'
    || path === '/change-password'  // system 首次设密 (business 走 /settings/password 改密)

  // V1.1: 业务用户访问 system-only 页 → 跳工作台
  if (userType !== 'system' && isSystemOnlyPath) {
    ElMessage.error('该功能仅系统账号可用')
    next('/dashboard')
    return
  }

  // V1.1.1: 首次访问业务页时, 业务用户未初始化 (没有 boss 等业务管理员), 跳初始化向导
  // 注: 这里通过 systemSettings 标记判断
  try {
    const { useSystemConfigStore } = await import('@/stores/systemConfig')
    const sysStore = useSystemConfigStore()
    const initialized = sysStore.sysConfig?.systemInitialized !== false
    if (userType === 'business' && !initialized && isBusinessPath) {
      next('/init-wizard')
      return
    }
  } catch { /* 系统配置没加载也放行, 让组件层报错 */ }

  next()
})

router.afterEach(() => {
  NProgress.done()
})

// Handle chunk loading failures (e.g. network issues after deployment)
router.onError((error, to) => {
  if (error.message.includes('Failed to fetch dynamically imported module') || error.message.includes('Importing a module script failed')) {
    ElMessage.error('页面加载失败，正在刷新...')
    window.location.href = to.fullPath
  }
})

export default router
