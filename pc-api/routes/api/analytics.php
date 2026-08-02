<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AnalyticsPdfController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 6 大报表 API + PDF 导出 (V1.2.7 E2)
|--------------------------------------------------------------------------
|
| 全部从物化视图读 (凌晨 02:30 刷新)
| 5min Redis 缓存
| 权限: analytics.view (spatie permission)
*/

Route::middleware(['auth:sanctum'])->prefix('analytics')->group(function () {
    // 数据接口
    Route::get('revenue',         [AnalyticsController::class, 'revenue']);
    Route::get('sales-funnel',    [AnalyticsController::class, 'salesFunnel']);
    Route::get('project-health',  [AnalyticsController::class, 'projectHealth']);
    Route::get('customer-rfm',    [AnalyticsController::class, 'customerRfm']);
    Route::get('inventory-aging', [AnalyticsController::class, 'inventoryAging']);
    Route::get('finance-pnl',     [AnalyticsController::class, 'financePnl']);

    // 物化视图状态 (dashboard 顶部 "数据更新于 XX")
    Route::get('refresh-status',  [AnalyticsController::class, 'refreshStatus']);

    // PDF 导出 (无 auth 因为浏览器 iframe 下载, 但需要 token query)
    Route::get('export/pdf',      [AnalyticsPdfController::class, 'export']);
});
