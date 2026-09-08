<?php

use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\PortalRepairController;
use Illuminate\Support\Facades\Route;

// ========== 客户端查询入口 (公开, 无需登录, 双因子验证) ==========
Route::prefix('portal/repair')->group(function () {
    // 限流 10/min 防暴力枚举
    Route::get('/', [PortalRepairController::class, 'query'])
        ->middleware('throttle:10,1');
});

// ========== 供应商门户 (V0.6.0 外部免登录) ==========
Route::prefix('portal')->group(function () {
    // P1-7 修复: 一次性 access_token 签发端点
    Route::post('access', [PortalController::class, 'access'])->middleware('throttle:10,1');
    Route::get('invitations', [PortalController::class, 'invitations'])->middleware('throttle:10,1');
    Route::get('supplier/info', [PortalController::class, 'supplierInfo'])->middleware('throttle:10,1');
    Route::get('t/{token}', [PortalController::class, 'tenderByToken']);
    Route::get('t/{token}/attachments/{attachment}', [PortalController::class, 'downloadPublicAttachment'])
        ->whereNumber('attachment')->middleware('throttle:30,1');
    Route::get('t/{token}/my-bid', [PortalController::class, 'myBid'])->middleware('throttle:20,1');
    Route::post('t/{token}/bids', [PortalController::class, 'submitBid'])->middleware('throttle:10,1');
    Route::post('t/{token}/bids/attachments', [PortalController::class, 'uploadBidAttachment'])->middleware('throttle:10,1');
});
