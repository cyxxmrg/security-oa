<?php

use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\FieldMaskController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DashboardWidgetController;
use App\Http\Controllers\Api\SystemMonitorController;
use App\Http\Controllers\Api\SystemDictController;
use App\Http\Controllers\Api\SetupWizardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ApprovalTemplateController;
use App\Http\Controllers\Api\ApprovalCenterController;
use App\Http\Controllers\Api\FinanceApprovalController;
use App\Http\Controllers\Api\OperationApprovalController;
use App\Http\Controllers\Api\ProjectApprovalController;
use App\Http\Controllers\Api\FollowUpCalendarController;
use App\Http\Controllers\Api\WarrantyController;
use App\Http\Controllers\Api\WarrantyServiceOrderController;
use App\Http\Controllers\Api\WarrantyDepositController;
use App\Http\Controllers\Api\Construction\BudgetController;
use App\Http\Controllers\Api\Construction\TeamController;
use App\Http\Controllers\Api\Construction\CommencementOrderController;
use App\Http\Controllers\Api\Construction\ConstructionLogController;
use App\Http\Controllers\Api\Construction\RectificationController;
use App\Http\Controllers\Api\Construction\WorkProcessController;
use App\Http\Controllers\Api\Construction\ExternalConstructionController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

// ========== 工作台 (V1.2.4t: system 也能看) ==========
Route::prefix('dashboard')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('workbench', [DashboardController::class, 'workbench']);
    Route::get('stats', [DashboardController::class, 'stats']);
    Route::get('recent-projects', [DashboardController::class, 'recentProjects']);
    Route::get('recent-service-orders', [DashboardController::class, 'recentServiceOrders']);
    Route::get('project-progress', [DashboardController::class, 'projectProgress']);
    Route::get('todo', [DashboardController::class, 'todo']);
    Route::get('service-stats', [DashboardController::class, 'serviceStats']);
    Route::get('revenue-trend', [DashboardController::class, 'revenueTrend']);
    Route::get('screen', [DashboardController::class, 'screen']);
    Route::get('overview', [DashboardController::class, 'overview']);
    Route::get('warranty-stats', [DashboardController::class, 'warrantyStats']);
    Route::get('maintenance-stats', [DashboardController::class, 'maintenanceStats']);
});

// ========== 售后服务 ==========
Route::prefix('service')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('stats', [ServiceController::class, 'stats']);
    Route::get('maintenance-contracts', [ServiceController::class, 'maintenanceContracts']);
    Route::get('orders', [ServiceController::class, 'index']);
    Route::post('orders', [ServiceController::class, 'store']);
    Route::get('orders/stats', [ServiceController::class, 'stats']);
    Route::get('orders/{serviceOrder}', [ServiceController::class, 'show']);
    Route::post('orders/{serviceOrder}/assign', [ServiceController::class, 'assign']);
    Route::post('orders/{serviceOrder}/start', [ServiceController::class, 'startRepair']);
    Route::post('orders/{serviceOrder}/complete', [ServiceController::class, 'completeRepair']);
    Route::post('orders/{serviceOrder}/confirm', [ServiceController::class, 'confirmByCustomer']);
});

// ========== 系统管理 (V1.1: 仅 system 可写) ==========
// ========== V0.5.7 块5 - Dashboard 多维度 widget ==========
Route::prefix('dashboard/widget')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('method-distribution', [DashboardWidgetController::class, 'methodDistribution']);
    Route::get('cycle-percentile', [DashboardWidgetController::class, 'cyclePercentile']);
    Route::get('fault-top', [DashboardWidgetController::class, 'faultTop']);
    Route::get('technician-rank', [DashboardWidgetController::class, 'technicianRank']);
    Route::get('all', [DashboardWidgetController::class, 'all']);
});

// ========== V0.5.7 块A - 系统初始化向导 ==========
// V1.2.7 P0 fix: system 自身被 EnsureBusinessUser 拦, 所有 ensure_system 路由都加 withoutMiddleware
// V1.2.9 BUG FIX: /system/employees 加 auth:sanctum, 同时 withoutMiddleware('ensure_business')
// 之前 system 调这个会被 ensure_business 拦 403
Route::get('system/employees', [EmployeeController::class, 'index'])
    ->middleware(['auth:sanctum'])->withoutMiddleware('ensure_business');
Route::prefix('setup')->middleware(['auth:sanctum'])->group(function () {
    Route::get('summary', [SetupWizardController::class, 'summary'])->middleware('ensure_system')->withoutMiddleware('ensure_business');
    Route::post('step1', [SetupWizardController::class, 'step1'])->middleware('ensure_system')->withoutMiddleware('ensure_business');
    Route::post('step3', [SetupWizardController::class, 'step3'])->middleware('ensure_system')->withoutMiddleware('ensure_business');
    Route::post('step4', [SetupWizardController::class, 'step4'])->middleware('ensure_system')->withoutMiddleware('ensure_business');
    Route::post('complete', [SetupWizardController::class, 'complete'])->middleware('ensure_system')->withoutMiddleware('ensure_business');
    Route::get('sample-csv', [SetupWizardController::class, 'sampleCsv'])->middleware('ensure_system')->withoutMiddleware('ensure_business');
});

// ========== V0.5.7 块B - 数据字典中心 ==========
Route::prefix('dict')->middleware(['auth:sanctum', 'ensure_system'])->group(function () {
    Route::get('kinds', [SystemDictController::class, 'kinds'])->withoutMiddleware('ensure_business');
    Route::get('grouped', [SystemDictController::class, 'grouped'])->withoutMiddleware('ensure_business');
    Route::get('/', [SystemDictController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::post('/', [SystemDictController::class, 'store'])->withoutMiddleware('ensure_business');
    Route::post('reorder', [SystemDictController::class, 'reorder'])->withoutMiddleware('ensure_business');
    Route::post('seed-defaults', [SystemDictController::class, 'seedDefaults'])->withoutMiddleware('ensure_business');
    Route::patch('{id}', [SystemDictController::class, 'update'])->whereNumber('id')->withoutMiddleware('ensure_business');
    Route::delete('{id}', [SystemDictController::class, 'destroy'])->whereNumber('id')->withoutMiddleware('ensure_business');
});

// ========== V0.5.7 块C - 系统监控 ==========
Route::prefix('admin/monitor')->middleware(['auth:sanctum', 'ensure_system'])->group(function () {
    Route::get('metrics', [SystemMonitorController::class, 'metrics'])->withoutMiddleware('ensure_business');
    Route::get('disk', [SystemMonitorController::class, 'disk'])->withoutMiddleware('ensure_business');
    Route::get('db', [SystemMonitorController::class, 'db'])->withoutMiddleware('ensure_business');
    Route::get('services', [SystemMonitorController::class, 'services'])->withoutMiddleware('ensure_business');
    Route::get('errors', [SystemMonitorController::class, 'errors'])->withoutMiddleware('ensure_business');
    Route::get('backups', [SystemMonitorController::class, 'backups'])->withoutMiddleware('ensure_business');
});

// ========== 角色权限管理 (RBAC) ==========
// V1.2.7 P0 fix: 路由用 {role} 接受 name (admin/manager/finance/user),
// 不用 whereNumber 限制 (前端 Matrix.vue 用 name 调, 不是 id)
// V1.2.7 P0 fix: 所有 roles 路由都加 withoutMiddleware('ensure_business')
// 业务管理员 zhaodc 没 system.* permission, 但有 system.role (admin role)
// V1.2.8n: 改成每个子路由单独加 withoutMiddleware, group 链式调用在 ensure_business 嵌套 group 下不生效
// P0-3 安全修复: 整个 roles group 加 permission:system.role 中间件, store / saveMenuPermissions / matrix 等
// 写接口不再裸奔; 继续保留 withoutMiddleware('ensure_business') 不破坏 admin 兼容
Route::prefix('roles')->middleware(['auth:sanctum', 'permission:system.role'])->group(function () {
    Route::get('/',         [RoleController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::post('/',        [RoleController::class, 'store'])->withoutMiddleware('ensure_business');
    Route::get('matrix',    [RoleController::class, 'matrix'])->withoutMiddleware('ensure_business');
    Route::get('menu-matrix', [RoleController::class, 'menuMatrix'])->withoutMiddleware('ensure_business');
    Route::post('{role}/menu-permissions', [RoleController::class, 'saveMenuPermissions'])->withoutMiddleware('ensure_business');
    Route::get('expiring',  [RoleController::class, 'expiringSoon'])->withoutMiddleware('ensure_business');
    Route::get('{role}',    [RoleController::class, 'show'])->withoutMiddleware('ensure_business');
});

// ========== 权限字典 ==========
Route::prefix('permissions')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [RoleController::class, 'permissionIndex'])->withoutMiddleware('ensure_business');
    Route::get('tree', [RoleController::class, 'permissionTree'])->withoutMiddleware('ensure_business');
    Route::get('my', [RoleController::class, 'myPermissions'])->withoutMiddleware('ensure_business');
});

Route::prefix('users')->middleware(['auth:sanctum'])->group(function () {
    Route::get('{user}/roles/active', [RoleController::class, 'usersActiveRoles'])
        ->whereNumber('user')
        ->withoutMiddleware('ensure_business');
    Route::get('{user}/roles', [RoleController::class, 'usersListRoles'])
        ->whereNumber('user')
        ->withoutMiddleware('ensure_business');
});

// ========== 角色权限矩阵 + 继承图 ==========
Route::middleware(['auth:sanctum', 'permission:system.role'])->group(function () {
    Route::get('roles/matrix', [RoleController::class, 'matrix'])->withoutMiddleware('ensure_business');
    Route::get('permissions/inheritance', [RoleController::class, 'inheritanceGraph'])->withoutMiddleware('ensure_business');
    Route::put('users/{user}/roles', [RoleController::class, 'usersSyncRoles'])->whereNumber('user')->withoutMiddleware('ensure_business');
    Route::post('users/bulk-assign-role', [RoleController::class, 'usersBulkAssignRole'])->withoutMiddleware('ensure_business');
    Route::post('users/{user}/roles/temporary', [RoleController::class, 'usersGrantTemporary'])->whereNumber('user')->withoutMiddleware('ensure_business');
    Route::delete('users/{user}/roles/{role}', [RoleController::class, 'usersRevokeRole'])->whereNumber('user')->withoutMiddleware('ensure_business');
});

// ========== 字段脱敏规则管理 ==========
Route::prefix('field-masks')->middleware(['auth:sanctum', 'permission:system.role'])->group(function () {
    Route::get('/', [FieldMaskController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::get('endpoints', [FieldMaskController::class, 'endpoints'])->withoutMiddleware('ensure_business');
    Route::post('/', [FieldMaskController::class, 'store'])->withoutMiddleware('ensure_business');
    Route::put('{id}', [FieldMaskController::class, 'update'])->whereNumber('id')->withoutMiddleware('ensure_business');
    Route::delete('{id}', [FieldMaskController::class, 'destroy'])->whereNumber('id')->withoutMiddleware('ensure_business');
    Route::post('flush-cache', [FieldMaskController::class, 'flushCache'])->withoutMiddleware('ensure_business');
    Route::post('preview', [FieldMaskController::class, 'preview'])->withoutMiddleware('ensure_business');
});

// ========== 审计日志 ==========
Route::prefix('audit-logs')->middleware(['auth:sanctum', 'permission:system.log'])->group(function () {
    Route::get('/', [AuditController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::get('{id}', [AuditController::class, 'show'])->withoutMiddleware('ensure_business');
});

// 数据权限审计
Route::prefix('audit/data-scope')->middleware(['auth:sanctum', 'permission:system.log'])->group(function () {
    Route::get('denied', [AuditController::class, 'dataScopeDenied'])->withoutMiddleware('ensure_business');
    Route::get('summary', [AuditController::class, 'dataScopeSummary'])->withoutMiddleware('ensure_business');
    Route::get('stats', [AuditController::class, 'dataScopeStats'])->withoutMiddleware('ensure_business');
});

// 权限变更流水
Route::prefix('audit')->middleware(['auth:sanctum', 'permission:system.role'])->group(function () {
    Route::get('role-changes', [AuditController::class, 'roleChanges'])->withoutMiddleware('ensure_business');
    Route::get('role-changes/summary', [AuditController::class, 'roleChangesSummary'])->withoutMiddleware('ensure_business');
});

// ========== 系统设置 ==========
Route::middleware(['auth:sanctum', 'permission:system.config'])->group(function () {
    Route::get('settings', [SystemSettingsController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::put('settings', [SystemSettingsController::class, 'update'])->withoutMiddleware('ensure_business');
    Route::get('settings/port', [SystemSettingsController::class, 'getPortConfig'])->withoutMiddleware('ensure_business');
    Route::put('settings/port', [SystemSettingsController::class, 'updatePortConfig'])->withoutMiddleware('ensure_business');
});

// V1.2.9f: system also reads idle-config; bypass ensure_business.
Route::get('settings/idle-config', [SystemSettingsController::class, 'getIdleConfig'])->withoutMiddleware('ensure_business');

// ========== 审批流程模板 ==========
Route::prefix('approval-templates')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [ApprovalTemplateController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::post('/', [ApprovalTemplateController::class, 'store'])->withoutMiddleware('ensure_business');
    Route::get('{approvalTemplate}', [ApprovalTemplateController::class, 'show'])->withoutMiddleware('ensure_business');
    Route::put('{approvalTemplate}', [ApprovalTemplateController::class, 'update'])->withoutMiddleware('ensure_business');
    Route::delete('{approvalTemplate}', [ApprovalTemplateController::class, 'destroy'])->withoutMiddleware('ensure_business');
    Route::post('{approvalTemplate}/toggle', [ApprovalTemplateController::class, 'toggle'])->withoutMiddleware('ensure_business');
});

// ========== 审批中心 ==========
// V1.2.7k: system 越权审批 — 不走 ensure_business
Route::prefix('approvals')->middleware(['auth:sanctum'])->withoutMiddleware(['ensure_business'])->group(function () {
    Route::get('center', [ApprovalCenterController::class, 'index']);
    Route::get('center/stats', [ApprovalCenterController::class, 'stats']);
    Route::get('/', [ApprovalCenterController::class, 'index']);
    // 财务审批
    Route::prefix('finance')->group(function () {
        Route::get('/', [FinanceApprovalController::class, 'index']);
        Route::post('/', [FinanceApprovalController::class, 'store']);
        Route::post('{approval}/approve', [FinanceApprovalController::class, 'approve']);
        Route::post('{approval}/reject', [FinanceApprovalController::class, 'reject']);
        Route::post('{approval}/forward', [FinanceApprovalController::class, 'forward']);
        Route::get('{approval}', [FinanceApprovalController::class, 'show']);
    });
    // 运营审批
    Route::prefix('operation')->group(function () {
        Route::get('/', [OperationApprovalController::class, 'index']);
        Route::post('/', [OperationApprovalController::class, 'store']);
        Route::post('{approval}/approve', [OperationApprovalController::class, 'approve']);
        Route::post('{approval}/reject', [OperationApprovalController::class, 'reject']);
        Route::post('{approval}/forward', [OperationApprovalController::class, 'forward']);
        Route::get('{approval}', [OperationApprovalController::class, 'show']);
    });
    // 项目审批
    Route::prefix('project')->group(function () {
        Route::get('/', [ProjectApprovalController::class, 'index']);
        Route::post('/', [ProjectApprovalController::class, 'store']);
        Route::post('{approval}/approve', [ProjectApprovalController::class, 'approve']);
        Route::post('{approval}/reject', [ProjectApprovalController::class, 'reject']);
        Route::post('{approval}/forward', [ProjectApprovalController::class, 'forward']);
        Route::get('{approval}', [ProjectApprovalController::class, 'show']);
    });
});

// ========== 客户跟进日历 ==========
Route::prefix('follow-ups')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('calendar', [FollowUpCalendarController::class, 'index']);
});

// ========== 质保期管理 ==========
Route::prefix('warranties')->middleware(['auth:sanctum', 'ensure_business', 'permission:warranty.view'])->group(function () {
    Route::get('expiring', [WarrantyController::class, 'expiring']);
    Route::get('/', [WarrantyController::class, 'index']);
    Route::post('/', [WarrantyController::class, 'store']);
    Route::post('/{id}/renew', [WarrantyController::class, 'renew'])->where('id', '[0-9]+');
    Route::post('/{id}/terminate', [WarrantyController::class, 'terminate'])->where('id', '[0-9]+');
    Route::get('/{id}', [WarrantyController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [WarrantyController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [WarrantyController::class, 'destroy'])->where('id', '[0-9]+');
});

// 质保期服务工单
Route::prefix('warranty-service-orders')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('technician-stats', [WarrantyServiceOrderController::class, 'technicianStats']);
    Route::get('/', [WarrantyServiceOrderController::class, 'index']);
    Route::post('/', [WarrantyServiceOrderController::class, 'store']);
    Route::post('/{id}/assign', [WarrantyServiceOrderController::class, 'assign'])->where('id', '[0-9]+');
    Route::post('/{id}/start', [WarrantyServiceOrderController::class, 'start'])->where('id', '[0-9]+');
    Route::post('/{id}/complete', [WarrantyServiceOrderController::class, 'complete'])->where('id', '[0-9]+');
    Route::post('/{id}/cancel', [WarrantyServiceOrderController::class, 'cancel'])->where('id', '[0-9]+');
    Route::get('/{id}', [WarrantyServiceOrderController::class, 'show'])->where('id', '[0-9]+');
});

// 质保期保证金
Route::prefix('warranty-deposits')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [WarrantyDepositController::class, 'index']);
    Route::post('/', [WarrantyDepositController::class, 'store']);
    Route::post('/{id}/partial-release', [WarrantyDepositController::class, 'partialRelease'])->where('id', '[0-9]+');
    Route::post('/{id}/full-release', [WarrantyDepositController::class, 'fullRelease'])->where('id', '[0-9]+');
    Route::post('/{id}/forfeit', [WarrantyDepositController::class, 'forfeit'])->where('id', '[0-9]+');
    Route::get('/{id}', [WarrantyDepositController::class, 'show'])->where('id', '[0-9]+');
});

// ========== 施工预算 ==========
Route::prefix('construction/budgets')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [BudgetController::class, 'index']);
    Route::get('/summary/{projectId}', [BudgetController::class, 'summary']);
    Route::post('/', [BudgetController::class, 'store']);
    Route::get('/{id}', [BudgetController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [BudgetController::class, 'update'])->where('id', '[0-9]+');
    Route::post('/{id}/approve', [BudgetController::class, 'approve'])->where('id', '[0-9]+');
    Route::post('/{id}/revise', [BudgetController::class, 'revise'])->where('id', '[0-9]+');
    Route::delete('/{id}', [BudgetController::class, 'destroy'])->where('id', '[0-9]+');
});

// ========== 施工团队 ==========
Route::prefix('construction/teams')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [TeamController::class, 'index']);
    Route::post('/', [TeamController::class, 'store']);
    Route::get('/{id}', [TeamController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [TeamController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [TeamController::class, 'destroy'])->where('id', '[0-9]+');
    Route::post('/{id}/members', [TeamController::class, 'addMembers'])->where('id', '[0-9]+');
    Route::delete('/{id}/members/{memberId}', [TeamController::class, 'removeMember'])->where('id', '[0-9]+')->where('memberId', '[0-9]+');
});

// ========== 开工单 ==========
Route::prefix('construction/commencement-orders')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [CommencementOrderController::class, 'index']);
    Route::post('/', [CommencementOrderController::class, 'store']);
    Route::post('/{id}/approve', [CommencementOrderController::class, 'approve'])->where('id', '[0-9]+');
    Route::post('/{id}/start', [CommencementOrderController::class, 'startWork'])->where('id', '[0-9]+');
    Route::post('/{id}/complete', [CommencementOrderController::class, 'complete'])->where('id', '[0-9]+');
    Route::get('/{id}', [CommencementOrderController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [CommencementOrderController::class, 'update'])->where('id', '[0-9]+');
});

// ========== 施工日志 ==========
Route::prefix('construction/logs')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [ConstructionLogController::class, 'index']);
    Route::post('/', [ConstructionLogController::class, 'store']);
    Route::get('/overdue', [ConstructionLogController::class, 'overdue']);
    Route::post('/{id}/submit', [ConstructionLogController::class, 'submit'])->where('id', '[0-9]+');
    Route::post('/{id}/progress', [ConstructionLogController::class, 'updateProgress'])->where('id', '[0-9]+');
    Route::get('/{id}', [ConstructionLogController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [ConstructionLogController::class, 'update'])->where('id', '[0-9]+');
});

// ========== 整改工单 ==========
Route::prefix('construction/rectifications')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [RectificationController::class, 'index']);
    Route::post('/', [RectificationController::class, 'store']);
    Route::post('/{id}/complete', [RectificationController::class, 'complete'])->where('id', '[0-9]+');
    Route::get('/{id}', [RectificationController::class, 'show'])->where('id', '[0-9]+');
});

// ========== 工序字典 ==========
Route::prefix('construction/work-processes')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [WorkProcessController::class, 'index']);
    Route::post('/', [WorkProcessController::class, 'store']);
    Route::put('/{id}', [WorkProcessController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [WorkProcessController::class, 'destroy'])->where('id', '[0-9]+');
});

// ========== 施工发包 ==========
Route::prefix('construction/external-works')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [ExternalConstructionController::class, 'index']);
    Route::post('/', [ExternalConstructionController::class, 'store']);
    Route::get('/{id}/bids', [ExternalConstructionController::class, 'listBids'])->where('id', '[0-9]+');
    Route::post('/{id}/close', [ExternalConstructionController::class, 'close'])->where('id', '[0-9]+');
    Route::post('/{id}/bids', [ExternalConstructionController::class, 'submitBid'])->where('id', '[0-9]+');
    Route::post('/{id}/award', [ExternalConstructionController::class, 'award'])->where('id', '[0-9]+');
    Route::get('/{id}', [ExternalConstructionController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [ExternalConstructionController::class, 'update'])->where('id', '[0-9]+');
});

// ========== System only 危险操作 ==========
// V1.2.7 P0 fix: 必须 ->withoutMiddleware('ensure_business')
// system 自身被 EnsureBusinessUser 拦, 进不去 wipe-data
Route::middleware(['auth:sanctum', 'ensure_system'])->group(function () {
    Route::post('admin/wipe-data',         [SystemSettingsController::class, 'wipeData'])->withoutMiddleware('ensure_business');
    Route::post('system/mark-initialized',  [SystemSettingsController::class, 'markInitialized'])->withoutMiddleware('ensure_business');
    Route::get('system/init-wizard-data',   [SystemSettingsController::class, 'initWizardData'])->withoutMiddleware('ensure_business');
    Route::post('system/wipe-all',          [SystemSettingsController::class, 'wipeAll'])->withoutMiddleware('ensure_business');
    Route::post('system/mark-as-system',    [SystemSettingsController::class, 'markAsSystem'])->withoutMiddleware('ensure_business');
    Route::post('system/business-admin',    [SystemSettingsController::class, 'businessAdmin'])->withoutMiddleware('ensure_business');
});

// ========== 通用 system 改密 (登录后即可调) ==========
// V1.2.7 P0 fix: system 账号自身改密/查看自身信息, 必须 ->withoutMiddleware('ensure_business')
// 不然 system 登录就被卡死, 进不去 setup wizard (因为 system_user is_system=true 但 user_type=system
// 同样被 EnsureBusinessUser 拒)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('settings/super-admin', [SystemSettingsController::class, 'getSuperAdmin'])
        ->withoutMiddleware('ensure_business');
    Route::post('system/super-admin/set-password', [SystemSettingsController::class, 'setSuperAdminPassword'])
        ->withoutMiddleware('ensure_business');
    Route::post('system/reset-user-password', [SystemSettingsController::class, 'resetUserPassword'])
        ->withoutMiddleware('ensure_business');
    // V1.2.9 BUG FIX: system 拉部门/角色 专用端点 (Welcome.vue 业务管理员弹窗)
    Route::get('system/departments', [SystemSettingsController::class, 'systemDepartments'])
        ->withoutMiddleware('ensure_business');
    Route::get('system/roles', [SystemSettingsController::class, 'systemRoles'])
        ->withoutMiddleware('ensure_business');
});

// V1.3.2: 期初数据 (admin 角色可见, system 可解锁)
use App\Http\Controllers\Api\OpeningBalanceController;
Route::prefix('opening')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('receivables', [OpeningBalanceController::class, 'receivables']);
    Route::post('receivables', [OpeningBalanceController::class, 'storeReceivable']);
    Route::delete('receivables/{receivable}', [OpeningBalanceController::class, 'destroyReceivable']);
    Route::get('payables', [OpeningBalanceController::class, 'payables']);
    Route::post('payables', [OpeningBalanceController::class, 'storePayable']);
    Route::delete('payables/{payable}', [OpeningBalanceController::class, 'destroyPayable']);
});

// 期初锁定状态 (挂在 settings 下, admin 可访问)
Route::prefix('settings/opening-balances')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('status', [OpeningBalanceController::class, 'status']);
    Route::post('lock', [OpeningBalanceController::class, 'lock']);
    Route::post('unlock', [OpeningBalanceController::class, 'unlock']);
});
