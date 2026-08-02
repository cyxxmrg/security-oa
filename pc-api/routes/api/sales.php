<?php

use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\SalesProductController;
use Illuminate\Support\Facades\Route;

// ========== 销售前链路 ==========
Route::prefix('sales')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    // V1.2.12: 线索 Lead 模块已删除 (商机直接走 opps/*)

    // 商机池
    Route::prefix('opps')->group(function () {
        Route::get('/', [SalesController::class, 'oppsIndex']);
        Route::get('kanban', [SalesController::class, 'oppsKanban']);
        Route::get('stage-options', [SalesController::class, 'oppsStageOptions']);
        Route::get('funnel', [SalesController::class, 'oppsFunnel']);
        Route::get('lost-reasons', [SalesController::class, 'oppsLostReasons']);
        Route::post('/', [SalesController::class, 'oppsStore'])->middleware('permission:sales.create|sales.edit');
        Route::patch('{opp}/stage', [SalesController::class, 'oppsUpdateStage'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::post('{opp}/mark-won', [SalesController::class, 'oppsMarkWon'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::post('{opp}/mark-lost', [SalesController::class, 'oppsMarkLost'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::get('{opp}/quotations', [SalesController::class, 'oppsQuotationsIndex'])->middleware('owns:opp');
        Route::post('{opp}/quotations', [SalesController::class, 'oppsQuotationsStore'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::post('{opp}/win', [SalesController::class, 'oppsWin'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::post('{opp}/lose', [SalesController::class, 'oppsLose'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::post('{opp}/hold', [SalesController::class, 'oppsHold'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::post('{opp}/move-to-project-pool', [SalesController::class, 'oppsMoveToProjectPool'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::post('{opp}/assign', [SalesController::class, 'oppsAssign'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::post('{opp}/revive', [SalesController::class, 'oppsRevive'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::post('{opp}/convert-to-project', [SalesController::class, 'oppsConvertToProject'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::get('{opp}', [SalesController::class, 'oppsShow'])->middleware('owns:opp');
        Route::put('{opp}', [SalesController::class, 'oppsUpdate'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        Route::delete('{opp}', [SalesController::class, 'oppsDestroy'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);

        // 阶段流转记录 (V1.2.12)
        Route::prefix('{opp}/stage-records')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\OpportunityStageRecordController::class, 'index'])->middleware('owns:opp');
            Route::post('/', [\App\Http\Controllers\Api\OpportunityStageRecordController::class, 'store'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
            Route::get('{record}', [\App\Http\Controllers\Api\OpportunityStageRecordController::class, 'show'])->middleware('owns:opp');
            Route::put('{record}', [\App\Http\Controllers\Api\OpportunityStageRecordController::class, 'update'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
            Route::delete('{record}', [\App\Http\Controllers\Api\OpportunityStageRecordController::class, 'destroy'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        });

        // 阶段附件 (V1.2.12c)
        Route::prefix('{opp}/stage-files')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\OpportunityStageFileController::class, 'index'])->middleware('owns:opp');
            Route::post('/', [\App\Http\Controllers\Api\OpportunityStageFileController::class, 'store'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
            Route::get('{file}/download', [\App\Http\Controllers\Api\OpportunityStageFileController::class, 'download']);
            Route::delete('{file}', [\App\Http\Controllers\Api\OpportunityStageFileController::class, 'destroy'])->middleware(['owns:opp', 'permission:sales.create|sales.edit']);
        });
    });

    // 报价单
    Route::prefix('quotes')->group(function () {
        Route::get('/', [SalesController::class, 'quotesIndex']);
        Route::get('status-options', [SalesController::class, 'quotesStatusOptions']);
        Route::post('/', [SalesController::class, 'quotesStore'])->middleware('permission:sales.create|sales.edit');
        Route::put('{quote}/status', [SalesController::class, 'quotesUpdateStatus'])->middleware(['owns:quote', 'permission:sales.create|sales.edit']);
        Route::post('{quote}/items', [SalesController::class, 'quotesStoreItems'])->middleware(['owns:quote', 'permission:sales.create|sales.edit']);
        Route::post('{quote}/new-version', [SalesController::class, 'quotesNewVersion'])->middleware(['owns:quote', 'permission:sales.create|sales.edit']);
        Route::post('{quote}/accept', [SalesController::class, 'quotationsAccept'])->middleware(['owns:quote', 'permission:sales.create|sales.edit']);
        Route::post('{quote}/reject', [SalesController::class, 'quotationsReject'])->middleware(['owns:quote', 'permission:sales.create|sales.edit']);
        Route::post('{quote}/revise', [SalesController::class, 'quotationsRevise'])->middleware(['owns:quote', 'permission:sales.create|sales.edit']);
        Route::get('{quote}', [SalesController::class, 'quotesShow'])->middleware('owns:quote');
        Route::put('{quote}', [SalesController::class, 'quotesUpdate'])->middleware(['owns:quote', 'permission:sales.create|sales.edit']);
        Route::delete('{quote}', [SalesController::class, 'quotesDestroy'])->middleware(['owns:quote', 'permission:sales.create|sales.edit']);
    });

    // 报价单 quotations 别名
    Route::prefix('quotations')->group(function () {
        Route::get('/', [SalesController::class, 'quotesIndex']);
        Route::get('{quotation}', [SalesController::class, 'quotationsShow'])->middleware('owns:quotation');
        Route::put('{quotation}', [SalesController::class, 'quotationsUpdate'])->middleware(['owns:quotation', 'permission:sales.create|sales.edit']);
        Route::delete('{quotation}', [SalesController::class, 'quotationsDestroy'])->middleware(['owns:quotation', 'permission:sales.create|sales.edit']);
        Route::post('{quotation}/accept', [SalesController::class, 'quotationsAccept'])->middleware(['owns:quotation', 'permission:sales.create|sales.edit']);
        Route::post('{quotation}/reject', [SalesController::class, 'quotationsReject'])->middleware(['owns:quotation', 'permission:sales.create|sales.edit']);
        Route::post('{quotation}/revise', [SalesController::class, 'quotationsRevise'])->middleware(['owns:quotation', 'permission:sales.create|sales.edit']);
    });

    // 推荐人
    Route::prefix('referrers')->group(function () {
        Route::get('/', [SalesController::class, 'referrersIndex']);
        Route::post('/', [SalesController::class, 'referrersStore'])->middleware('permission:sales.create|sales.edit');
        Route::get('{referrer}', [SalesController::class, 'referrersShow'])->middleware('owns:referrer');
        Route::put('{referrer}', [SalesController::class, 'referrersUpdate'])->middleware(['owns:referrer', 'permission:sales.create|sales.edit']);
        Route::delete('{referrer}', [SalesController::class, 'referrersDestroy'])->middleware(['owns:referrer', 'permission:sales.create|sales.edit']);
    });

    // 项目池
    Route::prefix('pool')->group(function () {
        Route::get('/', [SalesController::class, 'poolIndex']);
        Route::post('{pool}/convert-to-project', [SalesController::class, 'poolConvertToProject'])->middleware(['owns:pool', 'permission:sales.create|sales.edit']);
        Route::get('{pool}', [SalesController::class, 'poolShow'])->middleware('owns:pool');
        Route::put('{pool}', [SalesController::class, 'poolUpdate'])->middleware(['owns:pool', 'permission:sales.create|sales.edit']);
    });

    // 跟进记录 + 附件
    Route::prefix('follow-ups')->group(function () {
        Route::get('/', [SalesController::class, 'followUpsIndex']);
        Route::post('/', [SalesController::class, 'followUpsStore'])->middleware('permission:sales.create|sales.edit');
        Route::get('attachments/{att}/download', [SalesController::class, 'followUpsDownloadAttachment'])->middleware('owns:att');
        Route::delete('attachments/{att}', [SalesController::class, 'followUpsDeleteAttachment'])->middleware(['owns:att', 'permission:sales.create|sales.edit']);
        Route::post('{followUp}/attachments', [SalesController::class, 'followUpsUploadAttachment'])->middleware(['owns:followUp', 'permission:sales.create|sales.edit']);
        Route::get('{followUp}', [SalesController::class, 'followUpsShow'])->middleware('owns:followUp');
        Route::put('{followUp}', [SalesController::class, 'followUpsUpdate'])->middleware(['owns:followUp', 'permission:sales.create|sales.edit']);
        Route::delete('{followUp}', [SalesController::class, 'followUpsDestroy'])->middleware(['owns:followUp', 'permission:sales.create|sales.edit']);
    });

    // 推荐人居间费结算
    Route::prefix('referral-settlements')->group(function () {
        Route::get('/', [SalesController::class, 'referralSettlementsIndex']);
        Route::get('stats', [SalesController::class, 'referralSettlementsStats']);
        Route::post('{settlement}/approve', [SalesController::class, 'referralSettlementsApprove'])->middleware('permission:sales.create|sales.edit');
        Route::post('{settlement}/pay', [SalesController::class, 'referralSettlementsPay'])->middleware('permission:sales.create|sales.edit');
        Route::get('{settlement}', [SalesController::class, 'referralSettlementsShow']);
    });

    // 产品库
    Route::prefix('products')->group(function () {
        Route::get('categories', [SalesProductController::class, 'categories']);
        Route::get('/', [SalesProductController::class, 'index']);
        Route::post('/', [SalesProductController::class, 'store'])->middleware('permission:sales.create|sales.edit');
        Route::get('{product}', [SalesProductController::class, 'show']);
        Route::put('{product}', [SalesProductController::class, 'update'])->middleware('permission:sales.create|sales.edit');
        Route::delete('{product}', [SalesProductController::class, 'destroy'])->middleware('permission:sales.create|sales.edit');
    });
});
