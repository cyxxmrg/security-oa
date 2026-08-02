<?php

use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\FuelCardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryCategoryController;
use App\Http\Controllers\Api\ToolController;
use App\Http\Controllers\Api\DiskController;
use App\Http\Controllers\Api\KnowledgeController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SystemLogController;
use Illuminate\Support\Facades\Route;

// ========== 报销管理 ==========
Route::prefix('expenses')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [ExpenseController::class, 'index']);
    Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expense.create|expense.edit');
    Route::get('stats', [ExpenseController::class, 'stats']);
    Route::get('stats-group', [ExpenseController::class, 'statsGroup']);
    Route::get('projects', [ExpenseController::class, 'projects']);
    Route::get('my', [ExpenseController::class, 'myClaims']);
    Route::get('{claim}', [ExpenseController::class, 'show']);
    Route::put('{claim}', [ExpenseController::class, 'update'])->middleware('permission:expense.create|expense.edit');
    Route::delete('{claim}', [ExpenseController::class, 'destroy'])->middleware('permission:expense.create|expense.edit');
    Route::post('{claim}/approve', [ExpenseController::class, 'approve'])->middleware('permission:expense.approve');
    Route::post('{claim}/cancel', [ExpenseController::class, 'cancel'])->middleware('permission:expense.create|expense.edit');
    Route::post('{claim}/pay', [ExpenseController::class, 'pay'])->middleware('permission:expense.approve');
});

// ========== 车辆管理 ==========
Route::prefix('vehicles')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [VehicleController::class, 'index']);
    Route::post('/', [VehicleController::class, 'store'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::get('stats', [VehicleController::class, 'stats']);
    Route::get('usage', [VehicleController::class, 'usageRequests']);
    Route::post('usage', [VehicleController::class, 'storeUsageRequest'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::post('usage/{usageRequest}/dispatch', [VehicleController::class, 'dispatchVehicle'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::put('usage/{usageRequest}', [VehicleController::class, 'updateUsageRequest'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::get('applies', [VehicleController::class, 'usageRequests']);
    Route::get('apply', [VehicleController::class, 'usageRequests']);
    Route::post('apply', [VehicleController::class, 'storeUsageRequest'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::get('insurances', [VehicleController::class, 'insurances']);
    Route::post('insurances', [VehicleController::class, 'storeInsurance'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::put('insurances/{insurance}', [VehicleController::class, 'updateInsurance'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::delete('insurances/{insurance}', [VehicleController::class, 'destroyInsurance'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::get('maintenances', [VehicleController::class, 'maintenances']);
    Route::post('maintenances', [VehicleController::class, 'storeMaintenance'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::put('maintenances/{maintenance}', [VehicleController::class, 'updateMaintenance'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::delete('maintenances/{maintenance}', [VehicleController::class, 'destroyMaintenance'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::get('{vehicle}', [VehicleController::class, 'show']);
    Route::put('{vehicle}', [VehicleController::class, 'update'])->middleware('permission:vehicle.create|vehicle.edit');
    Route::delete('{vehicle}', [VehicleController::class, 'destroy'])->middleware('permission:vehicle.create|vehicle.edit');
});

// ========== 油卡管理 ==========
Route::prefix('fuel-cards')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('stats', [FuelCardController::class, 'stats']);
    Route::get('/', [FuelCardController::class, 'index']);
    Route::post('/', [FuelCardController::class, 'store']);
    Route::get('recharges', [FuelCardController::class, 'recharges']);
    Route::post('recharges', [FuelCardController::class, 'storeRecharge']);
    Route::delete('recharges/{recharge}', [FuelCardController::class, 'destroyRecharge']);
    Route::put('{card}', [FuelCardController::class, 'update']);
    Route::delete('{card}', [FuelCardController::class, 'destroy']);
});

// ========== 库存管理 ==========
Route::prefix('inventory')->middleware(['auth:sanctum', 'ensure_business', 'permission:inventory.view'])->group(function () {
    Route::get('/', [InventoryController::class, 'index']);
    Route::post('/', [InventoryController::class, 'store'])->middleware('permission:inventory.create');
    Route::get('stock-records', [InventoryController::class, 'stockRecords']);
    Route::get('stock-records/{recordNo}', [InventoryController::class, 'stockRecordDetail']);
    // V1.3.6: 库存流水记录 (原始明细, 不聚合, 供出入库页逐条展示)
    Route::get('stock-flow', [InventoryController::class, 'stockFlow']);
    Route::get('warehouses', [InventoryController::class, 'warehouses']);
    Route::get('low-stock', [InventoryController::class, 'lowStock']);
    Route::get('stats', [InventoryController::class, 'stats']);
    Route::post('stock-in', [InventoryController::class, 'stockIn'])->middleware('permission:inventory.transfer');
    Route::post('stock-out', [InventoryController::class, 'stockOut'])->middleware('permission:inventory.transfer');
    Route::post('batch-delete', [InventoryController::class, 'batchDelete'])->middleware('permission:inventory.create');
    Route::post('batch-update', [InventoryController::class, 'batchUpdate'])->middleware('permission:inventory.create');
    Route::post('batch-export', [InventoryController::class, 'batchExport']);
    Route::get('tree-with-counts', [InventoryController::class, 'treeWithCounts']);
    Route::get('items-by-category', [InventoryController::class, 'itemsByCategory']);
    Route::post('items/batch-import', [InventoryController::class, 'batchImport'])->middleware('permission:inventory.create');
    Route::get('items/export-template', [InventoryController::class, 'exportTemplate']);
    Route::get('warnings', [InventoryController::class, 'warnings']);
    // 仓库管理 (V1.2.14p)
    Route::post('warehouses', [InventoryController::class, 'warehouseStore'])->middleware('permission:inventory.create');
    Route::put('warehouses/{id}', [InventoryController::class, 'warehouseUpdate'])->middleware('permission:inventory.create');
    Route::delete('warehouses/{id}', [InventoryController::class, 'warehouseDestroy'])->middleware('permission:inventory.create');
    // 仓库调拨 (V1.2.14p)
    Route::post('stock-transfer', [InventoryController::class, 'stockTransfer'])->middleware('permission:inventory.transfer');
    // 工具使用单 (V1.3.4 简化版) — 注意: 必须在 {inventoryItem} 泛路由之前注册
    Route::get('tool-records', [ToolController::class, 'records']);
    Route::get('tools', [ToolController::class, 'tools']);
    Route::post('tools/convert', [ToolController::class, 'convert'])->middleware('permission:inventory.transfer');
    Route::post('tool-checkout', [ToolController::class, 'checkout'])->middleware('permission:inventory.transfer');
    Route::post('tool-return', [ToolController::class, 'returnItem'])->middleware('permission:inventory.transfer');
    Route::get('{inventoryItem}', [InventoryController::class, 'show']);
    Route::put('{inventoryItem}', [InventoryController::class, 'update'])->middleware('permission:inventory.create');
    Route::delete('{inventoryItem}', [InventoryController::class, 'destroy'])->middleware('permission:inventory.create');
});

// ========== 库存分类 ==========
Route::prefix('inventory-categories')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [InventoryCategoryController::class, 'index']);
    Route::get('tree', [InventoryCategoryController::class, 'tree']);
    Route::post('/', [InventoryCategoryController::class, 'store'])->middleware('permission:inventory.create');
    Route::post('{category}/move', [InventoryCategoryController::class, 'moveCategory'])->middleware('permission:inventory.create');
    Route::put('{category}', [InventoryCategoryController::class, 'update'])->middleware('permission:inventory.create');
    Route::delete('{category}', [InventoryCategoryController::class, 'destroy'])->middleware('permission:inventory.create');
});

// ========== 财务管理 ==========
Route::prefix('finance')->middleware(['auth:sanctum', 'ensure_business', 'permission:finance.view', 'field_mask'])->group(function () {
    Route::get('overview', [FinanceController::class, 'overview']);
    Route::get('summary', [FinanceController::class, 'summary']);
    Route::get('project-profit', [FinanceController::class, 'projectProfit']); // V1.2.16
    Route::get('payments', [FinanceController::class, 'payments']);
    // V1.2.12o: 独立付款单 (前端 Payment.vue 调)
    Route::post('payments', [FinanceController::class, 'storePayment'])->middleware('permission:finance.pay');
    // 应收
    Route::get('receivables', [FinanceController::class, 'receivables']);
    Route::post('receivables', [FinanceController::class, 'storeReceivable'])->middleware('permission:finance.receive');
    Route::get('receivables/{receivable}/payments', [FinanceController::class, 'receivablePayments']);
    Route::post('receivables/{receivable}/payments', [FinanceController::class, 'storeReceivablePayment'])->middleware('permission:finance.receive');
    Route::post('receivables/{receivable}/close', [FinanceController::class, 'closeReceivable'])->middleware('permission:finance.approve');
    Route::put('receivables/{receivable}', [FinanceController::class, 'updateReceivable'])->middleware('permission:finance.receive');
    Route::delete('receivables/{receivable}', [FinanceController::class, 'destroyReceivable'])->middleware('permission:finance.receive');
    // 应付
    Route::get('payables', [FinanceController::class, 'payables']);
    Route::post('payables', [FinanceController::class, 'storePayable'])->middleware('permission:finance.pay');
    Route::get('payables/{payable}/payments', [FinanceController::class, 'payablePayments']);
    Route::post('payables/{payable}/payments', [FinanceController::class, 'storePayablePayment'])->middleware('permission:finance.pay');
    Route::put('payables/{payable}', [FinanceController::class, 'updatePayable'])->middleware('permission:finance.pay');
    Route::delete('payables/{payable}', [FinanceController::class, 'destroyPayable'])->middleware('permission:finance.pay');
    // 资金账户
    Route::get('accounts', [FinanceController::class, 'accounts']);
    Route::post('accounts', [FinanceController::class, 'storeAccount'])->middleware('permission:finance.pay');
    Route::post('accounts/transfer', [FinanceController::class, 'transferAccount'])->middleware('permission:finance.pay');
    // V1.2.16: 内部转账明细
    Route::get('internal-transfers', [FinanceController::class, 'internalTransfers']);
    Route::get('internal-transfers/{groupId}', [FinanceController::class, 'internalTransferDetail']);
    Route::get('accounts/{account}/transactions', [FinanceController::class, 'accountTransactions']);
    Route::put('accounts/{account}', [FinanceController::class, 'updateAccount'])->middleware('permission:finance.pay');
    Route::delete('accounts/{account}', [FinanceController::class, 'destroyAccount'])->middleware('permission:finance.pay');
    // 发票
    Route::get('invoices', [FinanceController::class, 'invoices']);
    Route::post('invoices', [FinanceController::class, 'storeInvoice'])->middleware('permission:finance.pay');
    Route::get('invoices/{invoice}', [FinanceController::class, 'showInvoice']);
    Route::put('invoices/{invoice}', [FinanceController::class, 'updateInvoice'])->middleware('permission:finance.pay');
    Route::delete('invoices/{invoice}', [FinanceController::class, 'destroyInvoice'])->middleware('permission:finance.pay');
    // 报表
    Route::get('summary/aging', [FinanceController::class, 'agingSummary']);
    Route::get('summary/cashflow', [FinanceController::class, 'cashflowSummary']);
    // 收款单
    Route::get('receipts', [FinanceController::class, 'receipts']);
    Route::post('receipts', [FinanceController::class, 'storeReceipt'])->middleware('permission:finance.receive');
    Route::get('receipts/{receipt}', [FinanceController::class, 'showReceipt']);
    Route::get('transfers', [FinanceController::class, 'transfers']);
});

// ========== 公司网盘 ==========
Route::prefix('disk')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    // 初始化 & 设置
    Route::post('init', [DiskController::class, 'initDisk'])->middleware('permission:system.settings|admin');
    Route::get('settings', [DiskController::class, 'getSettings']);
    Route::put('settings', [DiskController::class, 'saveSettings'])->middleware('permission:system.settings|admin');
    Route::get('disk-list', [DiskController::class, 'diskList'])->middleware('permission:system.settings|admin');
    // 文件操作
    Route::get('tree', [DiskController::class, 'tree']);
    Route::get('stats', [DiskController::class, 'stats']);
    Route::get('folders', [DiskController::class, 'folders']);
    Route::post('folders', [DiskController::class, 'createFolder'])->middleware('permission:disk.create|disk.edit');
    Route::put('folders/{folder}', [DiskController::class, 'renameFolder'])->middleware('permission:disk.create|disk.edit');
    Route::delete('folders/{folder}', [DiskController::class, 'destroyFolder'])->middleware('permission:disk.create|disk.edit');
    Route::get('files', [DiskController::class, 'files']);
    Route::post('upload', [DiskController::class, 'upload'])->middleware('permission:disk.create|disk.edit');
    Route::get('files/{file}/download', [DiskController::class, 'download']);
    Route::put('files/{file}', [DiskController::class, 'renameFile'])->middleware('permission:disk.create|disk.edit');
    Route::delete('files/{file}', [DiskController::class, 'destroyFile'])->middleware('permission:disk.create|disk.edit');
    // 项目自动创建文件夹触发器（项目创建时项目模块调用）
    Route::post('ensure-project-folder/{project}', [DiskController::class, 'ensureProjectFolder'])->middleware('permission:project.create|project.edit');
});

// ========== 知识库 ==========
Route::prefix('knowledge')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::post('categories', [KnowledgeController::class, 'storeCategory'])->middleware('permission:knowledge.create|knowledge.edit');
    Route::get('categories', [KnowledgeController::class, 'categories']);
    Route::put('categories/{category}', [KnowledgeController::class, 'updateCategory'])->middleware('permission:knowledge.create|knowledge.edit');
    Route::delete('categories/{category}', [KnowledgeController::class, 'destroyCategory'])->middleware('permission:knowledge.create|knowledge.edit');
    Route::get('articles', [KnowledgeController::class, 'articles']);
    Route::post('articles', [KnowledgeController::class, 'store'])->middleware('permission:knowledge.create|knowledge.edit');
    Route::get('articles/{article}', [KnowledgeController::class, 'show']);
    Route::put('articles/{article}', [KnowledgeController::class, 'update'])->middleware('permission:knowledge.create|knowledge.edit');
    Route::delete('articles/{article}', [KnowledgeController::class, 'destroy'])->middleware('permission:knowledge.create|knowledge.edit');
    Route::post('upload', [KnowledgeController::class, 'uploadAttachment'])->middleware('permission:knowledge.create|knowledge.edit');
    Route::get('attachment/download', [KnowledgeController::class, 'downloadAttachment']);
});

// ========== 数据备份 (通用) ==========
// P0-4 安全修复: 加 permission:system.backup, 防止业务用户下载/触发备份/清空备份
// V1.2.8n: 每个子路由单独 withoutMiddleware('ensure_business'), group 链式无效
Route::prefix('backups')->middleware(['auth:sanctum', 'permission:system.backup'])->group(function () {
    Route::get('/', [BackupController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::post('/', [BackupController::class, 'store'])->withoutMiddleware('ensure_business');
    Route::get('schedule', [BackupController::class, 'schedule'])->withoutMiddleware('ensure_business');
    Route::put('schedule', [BackupController::class, 'updateSchedule'])->withoutMiddleware('ensure_business');
    Route::get('{filename}/download', [BackupController::class, 'download'])->withoutMiddleware('ensure_business');
    Route::delete('{filename}', [BackupController::class, 'destroy'])->withoutMiddleware('ensure_business');
});
// P0-4 安全修复: run-due 之前完全脱 auth, 任何人 / 任何 IP 可调 -> 完全裸奔
// 现在至少需要 auth:sanctum + permission:system.backup, 匿名不能再 trigger 备份
// 外部 cron 触发改用 token 走 GET /backups/run-due?token=... (已有 token 校验逻辑保留作 fallback)
Route::post('backups/run-due', [BackupController::class, 'runDue'])
    ->middleware(['auth:sanctum', 'permission:system.backup'])
    ->withoutMiddleware('ensure_business');

// ========== 消息中心 (通用) ==========
// V1.2.9f: 必须 ->withoutMiddleware('ensure_business'), system 也要能看自己未读数 (admin 顶部铃铛)
Route::prefix('notifications')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::get('unread-count', [NotificationController::class, 'unreadCount'])->withoutMiddleware('ensure_business');
    Route::post('mark-read', [NotificationController::class, 'markAsRead'])->withoutMiddleware('ensure_business');
    Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead'])->withoutMiddleware('ensure_business');
});

// ========== 系统日志 ==========
Route::middleware(['auth:sanctum', 'permission:system.log'])->group(function () {
    Route::get('system-logs', [SystemLogController::class, 'index']);
});
