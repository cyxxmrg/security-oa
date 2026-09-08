<?php

use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\FuelCardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryCategoryController;
use App\Http\Controllers\Api\ToolController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\DiskController;
use App\Http\Controllers\Api\KnowledgeController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SystemLogController;
use Illuminate\Support\Facades\Route;

// ========== 鎶ラ攢绠＄悊 ==========
Route::prefix('expenses')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expense.view');
    Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expense.create|expense.edit');
    Route::get('stats', [ExpenseController::class, 'stats'])->middleware('permission:expense.view');
    Route::get('stats-group', [ExpenseController::class, 'statsGroup'])->middleware('permission:expense.view');
    Route::get('projects', [ExpenseController::class, 'projects'])->middleware('permission:expense.create|expense.edit');
    Route::get('my', [ExpenseController::class, 'myClaims'])->middleware('permission:expense.view');
    Route::get('{claim}', [ExpenseController::class, 'show'])->middleware('permission:expense.view');
    Route::put('{claim}', [ExpenseController::class, 'update'])->middleware('permission:expense.create|expense.edit');
    Route::delete('{claim}', [ExpenseController::class, 'destroy'])->middleware('permission:expense.create|expense.edit');
    Route::post('{claim}/approve', [ExpenseController::class, 'approve'])->middleware('permission:expense.approve');
    Route::post('{claim}/cancel', [ExpenseController::class, 'cancel'])->middleware('permission:expense.create|expense.edit');
    Route::post('{claim}/pay', [ExpenseController::class, 'pay'])->middleware('permission:expense.approve');
});

// ========== 杞﹁締绠＄悊 ==========
// V1.4.4 瀹夊叏淇: 鍘熻璺敱(鍒楄〃/缁熻/璇︽儏/淇濋櫓/淇濆吇/鐢ㄨ溅鐢宠)瀹屽叏鏃?permission 涓棿浠?
// 浠讳綍鐧诲綍鐢ㄦ埛閮借兘鏌ョ湅杞︾墝/淇濋櫓/淇濆吇/璐圭敤鏁版嵁; 鍐欒矾鐢辩敤鐨?vehicle.create|vehicle.edit
// 鍦ㄦ潈闄愬瓧鍏镐腑鏍规湰鏈敞鍐?鈫?闈?admin 瑙掕壊涓€寰?403銆?
// 鐜扮粺涓€瀵归綈"鑿滃崟绾ф潈闄愮偣"(vehicle.view/apply/dispatch/insurance/maintenance),
// 杩欎簺鐐瑰湪瑙掕壊鏉冮檺鐭╅樀 UI 涓彲鍕鹃€? 绠＄悊鍛樺彲鑷璋冩暣鍙鑼冨洿銆?
Route::prefix('vehicles')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [VehicleController::class, 'index'])->middleware('permission:vehicle.*');
    Route::post('/', [VehicleController::class, 'store'])->middleware('permission:vehicle.create');
    Route::get('stats', [VehicleController::class, 'stats'])->middleware('permission:vehicle.*');
    Route::get('usage', [VehicleController::class, 'usageRequests'])->middleware('permission:vehicle.apply|vehicle.dispatch');
    Route::post('usage', [VehicleController::class, 'storeUsageRequest'])->middleware('permission:vehicle.apply');
    Route::post('usage/{usageRequest}/dispatch', [VehicleController::class, 'dispatchVehicle'])->middleware('permission:vehicle.dispatch');
    Route::put('usage/{usageRequest}', [VehicleController::class, 'updateUsageRequest'])->middleware('permission:vehicle.apply|vehicle.dispatch');
    Route::get('applies', [VehicleController::class, 'usageRequests'])->middleware('permission:vehicle.apply|vehicle.dispatch');
    Route::get('apply', [VehicleController::class, 'usageRequests'])->middleware('permission:vehicle.apply|vehicle.dispatch');
    Route::post('apply', [VehicleController::class, 'storeUsageRequest'])->middleware('permission:vehicle.apply');
    Route::get('insurances', [VehicleController::class, 'insurances'])->middleware('permission:vehicle.insurance');
    Route::post('insurances', [VehicleController::class, 'storeInsurance'])->middleware('permission:vehicle.insurance');
    Route::put('insurances/{insurance}', [VehicleController::class, 'updateInsurance'])->middleware('permission:vehicle.insurance');
    Route::delete('insurances/{insurance}', [VehicleController::class, 'destroyInsurance'])->middleware('permission:vehicle.insurance');
    Route::get('maintenances', [VehicleController::class, 'maintenances'])->middleware('permission:vehicle.maintenance');
    Route::post('maintenances', [VehicleController::class, 'storeMaintenance'])->middleware('permission:vehicle.maintenance');
    Route::put('maintenances/{maintenance}', [VehicleController::class, 'updateMaintenance'])->middleware('permission:vehicle.maintenance');
    Route::delete('maintenances/{maintenance}', [VehicleController::class, 'destroyMaintenance'])->middleware('permission:vehicle.maintenance');
    Route::get('{vehicle}', [VehicleController::class, 'show'])->middleware('permission:vehicle.*');
    Route::put('{vehicle}', [VehicleController::class, 'update'])->middleware('permission:vehicle.edit');
    Route::delete('{vehicle}', [VehicleController::class, 'destroy'])->middleware('permission:vehicle.edit');
});

// ========== 娌瑰崱绠＄悊 ==========
// V1.4.4 瀹夊叏淇: 涓庤溅杈嗗悓灞炰竴涓ā鍧? 鍘熸湰瀹屽叏鏃?permission 涓棿浠?
// (娌瑰崱浣欓/鍏呭€兼祦姘村睘璐㈠姟鏁忔劅鏁版嵁) 鈫?缁熶竴鎸傝彍鍗曠骇鏉冮檺鐐?vehicle.fuel
// V1.4.3/V1.4.4 浜屾鏀剁揣 (REVIEW_security-audit.md P1-3): 鍘熸暣缁勮鍐欏悓鏉?
// 鑳界湅浣欓/鍏呭€兼祦姘磋€呭嵆鍙敼娌瑰崱銆佸垹鍏呭€兼祦姘?鈫?鎷嗗垎涓?
//   璇?鍒楄〃/缁熻/鍏呭€兼祦姘? = vehicle.fuel
//   鍐?鏂板/缂栬緫/鍒犻櫎/鍏呭€?鍒犳祦姘? = vehicle.fuel.edit (鏂版潈闄愮偣, 闇€鍦ㄦ潈闄愮煩闃靛彟琛屾巿浜?
Route::prefix('fuel-cards')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('stats', [FuelCardController::class, 'stats'])->middleware('permission:vehicle.fuel');
    Route::get('/', [FuelCardController::class, 'index'])->middleware('permission:vehicle.fuel');
    Route::get('recharges', [FuelCardController::class, 'recharges'])->middleware('permission:vehicle.fuel');
    Route::post('/', [FuelCardController::class, 'store'])->middleware('permission:vehicle.fuel.edit');
    Route::post('recharges', [FuelCardController::class, 'storeRecharge'])->middleware('permission:vehicle.fuel.edit');
    Route::delete('recharges/{recharge}', [FuelCardController::class, 'destroyRecharge'])->middleware('permission:vehicle.fuel.edit');
    Route::put('{card}', [FuelCardController::class, 'update'])->middleware('permission:vehicle.fuel.edit');
    Route::delete('{card}', [FuelCardController::class, 'destroy'])->middleware('permission:vehicle.fuel.edit');
});

// ========== 搴撳瓨绠＄悊 ==========
Route::prefix('inventory')->middleware(['auth:sanctum', 'ensure_business', 'permission:inventory.view'])->group(function () {
    Route::get('/', [InventoryController::class, 'index']);
    Route::post('/', [InventoryController::class, 'store'])->middleware('permission:inventory.create');
    Route::get('stock-records', [InventoryController::class, 'stockRecords']);
    Route::get('stock-records/{recordNo}', [InventoryController::class, 'stockRecordDetail']);
    // V1.3.6: 搴撳瓨娴佹按璁板綍 (鍘熷鏄庣粏, 涓嶈仛鍚? 渚涘嚭鍏ュ簱椤甸€愭潯灞曠ず)
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
    // 浠撳簱绠＄悊 (V1.2.14p)
    Route::post('warehouses', [InventoryController::class, 'warehouseStore'])->middleware('permission:inventory.create');
    Route::put('warehouses/{id}', [InventoryController::class, 'warehouseUpdate'])->middleware('permission:inventory.create');
    Route::delete('warehouses/{id}', [InventoryController::class, 'warehouseDestroy'])->middleware('permission:inventory.create');
    // 浠撳簱璋冩嫧 (V1.2.14p)
    Route::post('stock-transfer', [InventoryController::class, 'stockTransfer'])->middleware('permission:inventory.transfer');
    // 宸ュ叿浣跨敤鍗?(V1.3.4 绠€鍖栫増) 鈥?娉ㄦ剰: 蹇呴』鍦?{inventoryItem} 娉涜矾鐢变箣鍓嶆敞鍐?
    Route::get('tool-records', [ToolController::class, 'records']);
    Route::get('tools', [ToolController::class, 'tools']);
    Route::post('tools/convert', [ToolController::class, 'convert'])->middleware('permission:inventory.transfer');
    Route::post('tool-checkout', [ToolController::class, 'checkout'])->middleware('permission:inventory.transfer');
    Route::post('tool-return', [ToolController::class, 'returnItem'])->middleware('permission:inventory.transfer');
    Route::get('{inventoryItem}', [InventoryController::class, 'show']);
    Route::put('{inventoryItem}', [InventoryController::class, 'update'])->middleware('permission:inventory.create');
    Route::delete('{inventoryItem}', [InventoryController::class, 'destroy'])->middleware('permission:inventory.create');
});

// ========== 搴撳瓨鍒嗙被 ==========
Route::prefix('inventory-categories')->middleware(['auth:sanctum', 'ensure_business', 'permission:inventory.view'])->group(function () {
    Route::get('/', [InventoryCategoryController::class, 'index']);
    Route::get('tree', [InventoryCategoryController::class, 'tree']);
    Route::post('/', [InventoryCategoryController::class, 'store'])->middleware('permission:inventory.create');
    Route::post('{category}/move', [InventoryCategoryController::class, 'moveCategory'])->middleware('permission:inventory.create');
    Route::put('{category}', [InventoryCategoryController::class, 'update'])->middleware('permission:inventory.create');
    Route::delete('{category}', [InventoryCategoryController::class, 'destroy'])->middleware('permission:inventory.create');
});

// ========== 璐㈠姟绠＄悊 ==========
Route::prefix('finance')->middleware(['auth:sanctum', 'ensure_business', 'permission:finance.view', 'field_mask'])->group(function () {
    // ===== 鍥哄畾璧勪骇绠＄悊 (V1.4.0) 鈥?娉ㄦ剰: assets/{asset} 鏀惧湪闈欐€佽矾鐢变箣鍚?=====
    Route::get('assets/categories/tree', [AssetController::class, 'categoryTree']);
    Route::post('assets/categories', [AssetController::class, 'storeCategory'])->middleware('permission:finance.asset');
    Route::put('assets/categories/{id}', [AssetController::class, 'updateCategory'])->middleware('permission:finance.asset');
    Route::delete('assets/categories/{id}', [AssetController::class, 'destroyCategory'])->middleware('permission:finance.asset');
    Route::get('assets/depreciations', [AssetController::class, 'depreciations']);
    Route::post('assets/depreciate', [AssetController::class, 'depreciate'])->middleware('permission:finance.asset');
    Route::get('assets/maintenances', [AssetController::class, 'maintenances']);
    Route::post('assets/maintenances', [AssetController::class, 'storeMaintenance'])->middleware('permission:finance.asset');
    Route::get('assets/inventories', [AssetController::class, 'inventories']);
    Route::post('assets/inventories', [AssetController::class, 'storeInventory'])->middleware('permission:finance.asset');
    Route::post('assets/inventories/{inventory}/complete', [AssetController::class, 'completeInventory'])->middleware('permission:finance.asset');
    Route::get('assets/disposals', [AssetController::class, 'disposals']);
    Route::post('assets/disposals', [AssetController::class, 'storeDisposal'])->middleware('permission:finance.asset');
    Route::get('assets/transfers', [AssetController::class, 'transfers']);
    Route::post('assets/transfers', [AssetController::class, 'storeTransfer'])->middleware('permission:finance.asset');
    Route::get('assets', [AssetController::class, 'index']);
    Route::post('assets', [AssetController::class, 'store'])->middleware('permission:finance.asset');
    Route::get('assets/{asset}', [AssetController::class, 'show']);
    Route::put('assets/{asset}', [AssetController::class, 'update'])->middleware('permission:finance.asset');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->middleware('permission:finance.asset');

    Route::get('overview', [FinanceController::class, 'overview']);
    Route::get('summary', [FinanceController::class, 'summary']);
    Route::get('project-profit', [FinanceController::class, 'projectProfit']); // V1.2.16
    Route::get('payments', [FinanceController::class, 'payments']);
    // V1.2.12o: 鐙珛浠樻鍗?(鍓嶇 Payment.vue 璋?
    Route::post('payments', [FinanceController::class, 'storePayment'])->middleware('permission:finance.pay');
    // 搴旀敹
    Route::get('receivables', [FinanceController::class, 'receivables']);
    Route::post('receivables', [FinanceController::class, 'storeReceivable'])->middleware('permission:finance.receive');
    Route::get('receivables/{receivable}/payments', [FinanceController::class, 'receivablePayments']);
    Route::post('receivables/{receivable}/payments', [FinanceController::class, 'storeReceivablePayment'])->middleware('permission:finance.receive');
    Route::post('receivables/{receivable}/close', [FinanceController::class, 'closeReceivable'])->middleware('permission:finance.approve');
    Route::put('receivables/{receivable}', [FinanceController::class, 'updateReceivable'])->middleware('permission:finance.receive');
    Route::delete('receivables/{receivable}', [FinanceController::class, 'destroyReceivable'])->middleware('permission:finance.receive');
    // 搴斾粯
    Route::get('payables', [FinanceController::class, 'payables']);
    Route::post('payables', [FinanceController::class, 'storePayable'])->middleware('permission:finance.pay');
    Route::get('payables/{payable}/payments', [FinanceController::class, 'payablePayments']);
    Route::post('payables/{payable}/payments', [FinanceController::class, 'storePayablePayment'])->middleware('permission:finance.pay');
    Route::put('payables/{payable}', [FinanceController::class, 'updatePayable'])->middleware('permission:finance.pay');
    Route::delete('payables/{payable}', [FinanceController::class, 'destroyPayable'])->middleware('permission:finance.pay');
    // 璧勯噾璐︽埛
    Route::get('accounts', [FinanceController::class, 'accounts']);
    Route::post('accounts', [FinanceController::class, 'storeAccount'])->middleware('permission:finance.pay');
    Route::post('accounts/transfer', [FinanceController::class, 'transferAccount'])->middleware('permission:finance.pay');
    // V1.2.16: 鍐呴儴杞处鏄庣粏
    Route::get('internal-transfers', [FinanceController::class, 'internalTransfers']);
    Route::get('internal-transfers/{groupId}', [FinanceController::class, 'internalTransferDetail']);
    Route::get('accounts/{account}/transactions', [FinanceController::class, 'accountTransactions']);
    Route::put('accounts/{account}', [FinanceController::class, 'updateAccount'])->middleware('permission:finance.pay');
    Route::delete('accounts/{account}', [FinanceController::class, 'destroyAccount'])->middleware('permission:finance.pay');
    // 鍙戠エ
    Route::get('invoices', [FinanceController::class, 'invoices']);
    Route::post('invoices', [FinanceController::class, 'storeInvoice'])->middleware('permission:finance.pay');
    Route::get('invoices/{invoice}', [FinanceController::class, 'showInvoice']);
    Route::put('invoices/{invoice}', [FinanceController::class, 'updateInvoice'])->middleware('permission:finance.pay');
    Route::delete('invoices/{invoice}', [FinanceController::class, 'destroyInvoice'])->middleware('permission:finance.pay');
    // 鎶ヨ〃
    Route::get('summary/aging', [FinanceController::class, 'agingSummary']);
    Route::get('summary/cashflow', [FinanceController::class, 'cashflowSummary']);
    // 鏀舵鍗?
    Route::get('receipts', [FinanceController::class, 'receipts']);
    Route::post('receipts', [FinanceController::class, 'storeReceipt'])->middleware('permission:finance.receive');
    Route::get('receipts/{receipt}', [FinanceController::class, 'showReceipt']);
    Route::get('transfers', [FinanceController::class, 'transfers']);
});

// ========== 鍏徃缃戠洏 ==========
Route::prefix('disk')->middleware(['auth:sanctum', 'ensure_business', 'permission:disk.view'])->group(function () {
    // 鍒濆鍖?& 璁剧疆
    Route::post('init', [DiskController::class, 'initDisk'])->middleware('permission:system.settings|admin');
    Route::get('settings', [DiskController::class, 'getSettings']);
    Route::put('settings', [DiskController::class, 'saveSettings'])->middleware('permission:system.settings|admin');
    Route::get('disk-list', [DiskController::class, 'diskList'])->middleware('permission:system.settings|admin');
    // 鏂囦欢鎿嶄綔
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
    // 椤圭洰鑷姩鍒涘缓鏂囦欢澶硅Е鍙戝櫒锛堥」鐩垱寤烘椂椤圭洰妯″潡璋冪敤锛?
    Route::post('ensure-project-folder/{project}', [DiskController::class, 'ensureProjectFolder'])->middleware('permission:project.create|project.edit');
});

// ========== 鐭ヨ瘑搴?==========
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
    Route::get('articles/{article}/attachment', [KnowledgeController::class, 'downloadAttachment'])
        ->middleware('permission:knowledge.view')
        ->name('knowledge.articles.attachment');
});

// ========== 鏁版嵁澶囦唤 (閫氱敤) ==========
// P0-4 瀹夊叏淇: 鍔?permission:system.backup, 闃叉涓氬姟鐢ㄦ埛涓嬭浇/瑙﹀彂澶囦唤/娓呯┖澶囦唤
// V1.2.8n: 姣忎釜瀛愯矾鐢卞崟鐙?withoutMiddleware('ensure_business'), group 閾惧紡鏃犳晥
Route::prefix('backups')->middleware(['auth:sanctum', 'ensure_system', 'permission:system.backup'])->group(function () {
    Route::get('/', [BackupController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::post('/', [BackupController::class, 'store'])->withoutMiddleware('ensure_business');
    Route::get('schedule', [BackupController::class, 'schedule'])->withoutMiddleware('ensure_business');
    Route::put('schedule', [BackupController::class, 'updateSchedule'])->withoutMiddleware('ensure_business');
    Route::get('{filename}/download', [BackupController::class, 'download'])->withoutMiddleware('ensure_business');
    Route::delete('{filename}', [BackupController::class, 'destroy'])->withoutMiddleware('ensure_business');
});
// ========== 娑堟伅涓績 (閫氱敤) ==========
// V1.2.9f: 蹇呴』 ->withoutMiddleware('ensure_business'), system 涔熻鑳界湅鑷繁鏈鏁?(admin 椤堕儴閾冮摏)
Route::prefix('notifications')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::get('unread-count', [NotificationController::class, 'unreadCount'])->withoutMiddleware('ensure_business');
    Route::post('mark-read', [NotificationController::class, 'markAsRead'])->withoutMiddleware('ensure_business');
    Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead'])->withoutMiddleware('ensure_business');
});

// ========== 绯荤粺鏃ュ織 ==========
Route::middleware(['auth:sanctum', 'permission:system.log'])->group(function () {
    Route::get('system-logs', [SystemLogController::class, 'index']);
});
