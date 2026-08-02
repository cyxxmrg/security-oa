<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerPipelineController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\LedgerController;
use Illuminate\Support\Facades\Route;

// ========== 客户管理 ==========
Route::prefix('customers')->middleware(['auth:sanctum', 'ensure_business', 'permission:customer.view'])->group(function () {
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store'])->middleware('permission:customer.create');
    Route::get('stats', [CustomerController::class, 'stats']);
    Route::get('industries', [CustomerController::class, 'industries']);
    Route::get('health', [CustomerController::class, 'health']);
    Route::post('import', [CustomerController::class, 'import']);
    // 销售漏斗看板
    Route::get('pipeline', [CustomerPipelineController::class, 'index']);
    Route::get('pipeline/weekly-trend', [CustomerPipelineController::class, 'weeklyTrend']);
    Route::get('map', [CustomerController::class, 'mapData']);
    Route::get('{customer}/profile', [CustomerController::class, 'profile']);
    Route::get('{customer}/follow-ups', [CustomerController::class, 'followUps']);
    Route::post('{customer}/follow-ups', [CustomerController::class, 'storeFollowUp']);
    Route::get('{customer}/devices', [CustomerController::class, 'devices']);
    // 联系人管理
    Route::get('{customer}/contacts', [CustomerController::class, 'listContacts']);
    Route::post('{customer}/contacts', [CustomerController::class, 'storeContact']);
    Route::put('{customer}/contacts/{contact}', [CustomerController::class, 'updateContact'])->whereNumber('contact');
    Route::delete('{customer}/contacts/{contact}', [CustomerController::class, 'destroyContact'])->whereNumber('contact');
    // 开票信息
    Route::get('{customer}/invoice-infos', [CustomerController::class, 'listInvoiceInfos']);
    Route::post('{customer}/invoice-infos', [CustomerController::class, 'storeInvoiceInfo']);
    Route::put('{customer}/invoice-infos/{info}', [CustomerController::class, 'updateInvoiceInfo'])->whereNumber('info');
    Route::delete('{customer}/invoice-infos/{info}', [CustomerController::class, 'destroyInvoiceInfo'])->whereNumber('info');
    // 放最后
    Route::get('{customer}', [CustomerController::class, 'show']);
    Route::put('{customer}', [CustomerController::class, 'update']);
    Route::put('{customer}/stage', [CustomerPipelineController::class, 'updateStage']);
    Route::delete('{customer}', [CustomerController::class, 'destroy']);
});

// ========== 供应商管理 ==========
Route::prefix('suppliers')->middleware(['auth:sanctum', 'ensure_business', 'permission:supplier.view'])->group(function () {
    Route::get('/', [SupplierController::class, 'index']);
    Route::post('/', [SupplierController::class, 'store'])->middleware('permission:supplier.create');
    Route::get('{id}', [SupplierController::class, 'show'])->whereNumber('id');
    Route::put('{id}', [SupplierController::class, 'update'])->whereNumber('id');
    Route::delete('{id}', [SupplierController::class, 'destroy'])->whereNumber('id');
    Route::post('{id}/change-status', [SupplierController::class, 'changeStatus'])->whereNumber('id');
    Route::post('{id}/sync-contacts', [SupplierController::class, 'syncContacts'])->whereNumber('id');
    Route::get('{id}/evaluations', [SupplierController::class, 'evaluations'])->whereNumber('id');
});

// ========== 总账（只读） ==========
Route::prefix('ledger')->middleware(['auth:sanctum', 'ensure_business', 'permission:finance.view'])->group(function () {
    Route::get('suppliers', [LedgerController::class, 'suppliers']);
    Route::get('suppliers/{id}', [LedgerController::class, 'supplierLedger'])->whereNumber('id');
    Route::get('suppliers/{id}/payables', [LedgerController::class, 'supplierPayables'])->whereNumber('id');
    Route::get('supplier-payments/{id}', [LedgerController::class, 'showSupplierPayment'])->whereNumber('id');
    Route::get('customers', [LedgerController::class, 'customers']);
    Route::get('customers/{id}', [LedgerController::class, 'customerLedger'])->whereNumber('id');
    Route::get('customers/{id}/receivables', [LedgerController::class, 'customerReceivables'])->whereNumber('id');
    Route::get('customer-receipts', [LedgerController::class, 'customerReceipts']);
    Route::get('customer-receipts/{id}', [LedgerController::class, 'showCustomerReceipt'])->whereNumber('id');
    Route::get('summary', [LedgerController::class, 'summary']);
    Route::get('aging', [LedgerController::class, 'aging']);
});

// ========== 总账（写操作） ==========
Route::prefix('ledger')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::post('supplier-payments', [LedgerController::class, 'createSupplierPayment'])->middleware('permission:finance.pay');
    Route::post('customer-receipts', [LedgerController::class, 'createCustomerReceipt'])->middleware('permission:finance.receive');
});
