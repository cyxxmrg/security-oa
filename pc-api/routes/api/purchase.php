<?php

use App\Http\Controllers\Api\PurchaseFlowController;
use App\Http\Controllers\Api\PurchaseRequirementController;
use App\Http\Controllers\Api\PurchasePlanController;
use App\Http\Controllers\Api\PurchaseContractController;
use App\Http\Controllers\Api\PurchasePaymentRequestController;
use App\Http\Controllers\Api\PurchasePaymentController;
use App\Http\Controllers\Api\PurchaseShipmentController;
use App\Http\Controllers\Api\PurchaseLogisticsController;
use App\Http\Controllers\Api\PurchaseApprovalController;
use App\Http\Controllers\Api\TenderController;
use App\Http\Controllers\Api\ExternalQuoteController;
use Illuminate\Support\Facades\Route;

// ========== 招标中心 ==========
Route::prefix('tenders')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [TenderController::class, 'index'])->middleware('permission:tender.view|tender.approve|deposit.manage');
    Route::post('/', [TenderController::class, 'store'])->middleware('permission:tender.create');
    Route::get('pending-review', [TenderController::class, 'pendingReview'])->middleware('permission:tender.approve');
    Route::post('{id}/publish', [TenderController::class, 'publish'])->whereNumber('id')->middleware('permission:tender.submit|tender.approve');
    Route::post('{id}/close', [TenderController::class, 'close'])->whereNumber('id')->middleware('permission:tender.cancel|tender.create');
    Route::post('{id}/cancel', [TenderController::class, 'cancel'])->whereNumber('id')->middleware('permission:tender.cancel');
    Route::post('{id}/evaluate', [TenderController::class, 'evaluate'])->whereNumber('id')->middleware('permission:tender.award|tender.approve');
    Route::post('{id}/award', [TenderController::class, 'award'])->whereNumber('id')->middleware('permission:tender.award');
    Route::post('{id}/submit-review', [TenderController::class, 'submitReview'])->whereNumber('id')->middleware('permission:tender.submit');
    Route::post('{id}/approve', [TenderController::class, 'approve'])->whereNumber('id')->middleware('permission:tender.approve');
    Route::post('{id}/reject', [TenderController::class, 'reject'])->whereNumber('id')->middleware('permission:tender.approve');
    Route::post('{id}/withdraw', [TenderController::class, 'withdraw'])->whereNumber('id')->middleware('permission:tender.withdraw');
    Route::post('{id}/cancel-v2', [TenderController::class, 'cancelV2'])->whereNumber('id')->middleware('permission:tender.cancel');
    Route::put('{id}/deposit-rule', [TenderController::class, 'setDepositRule'])->whereNumber('id')->middleware('permission:deposit.manage');
    Route::get('{id}/deposits', [TenderController::class, 'listDeposits'])->whereNumber('id')->middleware('permission:tender.view|tender.approve|deposit.manage');
    Route::post('{id}/deposits', [TenderController::class, 'createDeposit'])->whereNumber('id')->middleware('permission:deposit.manage');
    Route::post('{id}/deposits/{depositId}/mark-paid', [TenderController::class, 'markDepositPaid'])->whereNumber('id')->whereNumber('depositId')->middleware('permission:deposit.manage');
    Route::post('{id}/deposits/{depositId}/refund', [TenderController::class, 'refundDeposit'])->whereNumber('id')->whereNumber('depositId')->middleware('permission:deposit.manage');
    Route::post('{id}/deposits/{depositId}/forfeit', [TenderController::class, 'forfeitDeposit'])->whereNumber('id')->whereNumber('depositId')->middleware('permission:deposit.manage');
    Route::get('{id}/downstream', [TenderController::class, 'downstream'])->whereNumber('id')->middleware('permission:tender.view|tender.approve');
    Route::get('{id}/bids', [TenderController::class, 'bids'])->whereNumber('id')->middleware('permission:tender.view|tender.award');
    Route::post('{id}/bids', [TenderController::class, 'storeBid'])->whereNumber('id')->middleware('permission:tender.create');
    Route::get('{id}/attachments', [TenderController::class, 'listAttachments'])->whereNumber('id')->middleware('permission:tender.view|tender.award');
    Route::post('{id}/attachments', [TenderController::class, 'uploadAttachment'])->whereNumber('id')->middleware('permission:tender.create');
    Route::get('{id}/attachments/{attId}/download', [TenderController::class, 'downloadAttachment'])
        ->whereNumber('id')->whereNumber('attId')->name('tenders.attachments.download')->middleware('permission:tender.view|tender.award');
    Route::delete('{id}/attachments/{attId}', [TenderController::class, 'deleteAttachment'])->whereNumber('id')->middleware('permission:tender.create');
    Route::get('{id}', [TenderController::class, 'show'])->whereNumber('id')->middleware('permission:tender.view|tender.approve|deposit.manage');
    Route::put('{id}', [TenderController::class, 'update'])->whereNumber('id')->middleware('permission:tender.create');
    Route::delete('{id}', [TenderController::class, 'destroy'])->whereNumber('id')->middleware('permission:tender.create');
});

// ========== 采购协同 8 步流转 ==========
Route::prefix('purchase-flow')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::post('requirements', [PurchaseFlowController::class, 'createRequirement'])->middleware('permission:purchase.requirement');
    Route::get('by-source/{type}/{id}', [PurchaseFlowController::class, 'bySource'])->middleware('permission:purchase.requirement');
    Route::post('requirements/{id}/submit', [PurchaseFlowController::class, 'submitRequirement'])->whereNumber('id')->middleware('permission:purchase.requirement');
    Route::post('requirements/{id}/approve', [PurchaseFlowController::class, 'approveRequirement'])->whereNumber('id')->middleware('permission:approval.mine');
    Route::post('{entityType}/{id}/cancel', [PurchaseFlowController::class, 'cancel'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::get('{entityType}/{id}/trace', [PurchaseFlowController::class, 'trace'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::post('plans', [PurchaseFlowController::class, 'createPlan'])->middleware('permission:purchase.order');
    Route::post('plans/{id}/submit', [PurchaseFlowController::class, 'submitPlan'])->whereNumber('id')->middleware('permission:purchase.order');
    Route::post('plans/{id}/approve', [PurchaseFlowController::class, 'approvePlan'])->whereNumber('id')->middleware('permission:approval.mine');
    Route::post('orders', [PurchaseFlowController::class, 'createOrder'])->middleware('permission:purchase.order');
    Route::post('orders/{id}/submit', [PurchaseFlowController::class, 'submitOrder'])->whereNumber('id')->middleware('permission:purchase.order');
    Route::post('orders/{id}/approve', [PurchaseFlowController::class, 'approveOrder'])->whereNumber('id')->middleware('permission:approval.mine');
    Route::post('contracts', [PurchaseFlowController::class, 'createContract'])->middleware('permission:purchase.detail');
    Route::post('contracts/{id}/sign', [PurchaseFlowController::class, 'signContract'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::get('contracts/{id}/files', [PurchaseFlowController::class, 'listContractFiles'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::post('contracts/{id}/files', [PurchaseFlowController::class, 'uploadContractFile'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::get('contracts/{id}/files/{fid}/download', [PurchaseFlowController::class, 'downloadContractFile'])->whereNumber('id')->whereNumber('fid')->name('purchase-flow.contract-files.download')->middleware('permission:purchase.detail');
    Route::delete('contracts/{id}/files/{fid}', [PurchaseFlowController::class, 'deleteContractFile'])->whereNumber('id')->whereNumber('fid')->middleware('permission:purchase.detail');
    Route::get('contracts/{id}/items', [PurchaseFlowController::class, 'listContractItems'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::post('contracts/{id}/items', [PurchaseFlowController::class, 'addContractItem'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::post('contracts/{id}/items/sync', [PurchaseFlowController::class, 'syncContractItems'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::put('contracts/{id}/items/{iid}', [PurchaseFlowController::class, 'updateContractItem'])->whereNumber('id')->whereNumber('iid')->middleware('permission:purchase.detail');
    Route::delete('contracts/{id}/items/{iid}', [PurchaseFlowController::class, 'deleteContractItem'])->whereNumber('id')->whereNumber('iid')->middleware('permission:purchase.detail');
    Route::post('contracts/{id}/shipping-plans', [PurchaseFlowController::class, 'setShippingPlan'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::post('contracts/{id}/tracking', [PurchaseFlowController::class, 'addTracking'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::get('contracts/{id}/shipping', [PurchaseFlowController::class, 'listShipping'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::get('payment-requests/{id}/vouchers', [PurchaseFlowController::class, 'listPaymentVouchers'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::post('payment-requests/{id}/voucher', [PurchaseFlowController::class, 'uploadPaymentVoucher'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::get('payment-requests/{id}/vouchers/{vid}/download', [PurchaseFlowController::class, 'downloadPaymentVoucher'])->whereNumber('id')->whereNumber('vid')->name('purchase-flow.payment-vouchers.download')->middleware('permission:purchase.detail');
    Route::post('payment-requests', [PurchaseFlowController::class, 'createPaymentRequest'])->middleware('permission:purchase.detail');
    Route::post('payment-requests/{id}/approve', [PurchaseFlowController::class, 'approvePaymentRequest'])->whereNumber('id')->middleware('permission:approval.mine|finance.pay');
    Route::post('payments', [PurchaseFlowController::class, 'executePayment'])->middleware('permission:finance.pay');
    Route::post('shipments', [PurchaseFlowController::class, 'createShipment'])->middleware('permission:purchase.detail');
    Route::post('shipments/{id}/update-status', [PurchaseFlowController::class, 'updateShipmentStatus'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::post('shipments/{id}/auto-inbound', [PurchaseFlowController::class, 'autoInbound'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::post('shipments/{id}/confirm-inbound', [PurchaseFlowController::class, 'confirmInbound'])->whereNumber('id')->middleware('permission:purchase.detail');
    Route::get('logs', [PurchaseFlowController::class, 'logs'])->middleware('permission:purchase.detail');
    Route::get('orders-list', [PurchaseFlowController::class, 'listOrders'])->middleware('permission:purchase.order|purchase.detail');
    Route::get('requirements-list', [PurchaseFlowController::class, 'listRequirements'])->middleware('permission:purchase.requirement');
    Route::get('contracts-list', [PurchaseFlowController::class, 'listPurchaseContracts'])->middleware('permission:purchase.detail');
    Route::post('from-work-order/{workOrderId}', [PurchaseFlowController::class, 'fromWorkOrder'])->whereNumber('workOrderId')->middleware('permission:purchase.requirement');
    Route::post('from-external-work/{workId}', [PurchaseFlowController::class, 'fromExternalWork'])->whereNumber('workId')->middleware('permission:purchase.requirement');
});

// ========== 采购管理 ==========
Route::prefix('purchase')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::prefix('requirements')->middleware('permission:purchase.requirement')->group(function () {
        Route::get('/', [PurchaseRequirementController::class, 'index']);
        Route::get('stats', [PurchaseRequirementController::class, 'stats']);
        Route::post('/', [PurchaseRequirementController::class, 'store']);
        Route::put('{requirement}', [PurchaseRequirementController::class, 'update']);
        Route::delete('{requirement}', [PurchaseRequirementController::class, 'destroy']);
    });

    Route::prefix('plans')->middleware('permission:purchase.order')->group(function () {
        Route::get('/', [PurchasePlanController::class, 'index']);
        Route::get('stats', [PurchasePlanController::class, 'stats']);
        Route::post('/', [PurchasePlanController::class, 'store']);
        Route::post('{plan}/submit', [PurchasePlanController::class, 'submit']);
        Route::post('{plan}/approve', [PurchasePlanController::class, 'approve'])
            ->withoutMiddleware('permission:purchase.order')
            ->middleware('permission:approval.mine');
        Route::put('{plan}', [PurchasePlanController::class, 'update']);
        Route::delete('{plan}', [PurchasePlanController::class, 'destroy']);
    });

    Route::prefix('contracts')->middleware('permission:purchase.detail')->group(function () {
        Route::get('/', [PurchaseContractController::class, 'index']);
        Route::get('stats', [PurchaseContractController::class, 'stats']);
        Route::post('/', [PurchaseContractController::class, 'store']);
        Route::post('{contract}/ship', [PurchaseContractController::class, 'ship']);
        Route::get('{contract}', [PurchaseContractController::class, 'show']);
        Route::put('{contract}', [PurchaseContractController::class, 'update']);
        Route::delete('{contract}', [PurchaseContractController::class, 'destroy']);
    });

    Route::prefix('payment-requests')->middleware('permission:purchase.detail')->group(function () {
        Route::get('/', [PurchasePaymentRequestController::class, 'index']);
        Route::get('stats', [PurchasePaymentRequestController::class, 'stats']);
        Route::post('/', [PurchasePaymentRequestController::class, 'store']);
        Route::post('{req}/approve', [PurchasePaymentRequestController::class, 'approve'])
            ->withoutMiddleware('permission:purchase.detail')
            ->middleware('permission:approval.mine|finance.pay');
        Route::delete('{req}', [PurchasePaymentRequestController::class, 'destroy']);
    });

    Route::prefix('payments')->middleware('permission:purchase.detail')->group(function () {
        Route::get('/', [PurchasePaymentController::class, 'index']);
        Route::get('stats', [PurchasePaymentController::class, 'stats']);
        Route::post('/', [PurchasePaymentController::class, 'store'])->middleware('permission:finance.pay');
    });

    Route::prefix('shipments')->middleware('permission:purchase.detail')->group(function () {
        Route::get('/', [PurchaseShipmentController::class, 'index']);
        Route::get('stats', [PurchaseShipmentController::class, 'stats']);
        Route::post('{shipment}/logistics-update', [PurchaseLogisticsController::class, 'store']);
        Route::get('{shipment}/logistics', [PurchaseLogisticsController::class, 'index']);
        Route::get('{shipment}/track', [PurchaseLogisticsController::class, 'track']);
        Route::put('{shipment}/logistics/{log}', [PurchaseLogisticsController::class, 'update']);
        Route::get('{shipment}', [PurchaseShipmentController::class, 'show']);
    });

    Route::get('logistics', [PurchaseLogisticsController::class, 'overview'])->middleware('permission:purchase.detail');

    Route::prefix('approvals')->middleware('permission:purchase.detail')->group(function () {
        Route::get('/', [PurchaseApprovalController::class, 'index']);
        Route::post('/', [PurchaseApprovalController::class, 'store']);
        Route::post('{appr}/decide', [PurchaseApprovalController::class, 'decide'])->middleware('permission:finance.pay');
    });
});

// ========== 对外报价 ==========
Route::prefix('external-quotes')->middleware(['auth:sanctum', 'ensure_business', 'permission:sales.external_quote'])->group(function () {
    Route::get('requests', [ExternalQuoteController::class, 'indexRequests']);
    Route::post('requests', [ExternalQuoteController::class, 'storeRequest']);
    Route::post('requests/{id}/close', [ExternalQuoteController::class, 'closeRequest'])->whereNumber('id');
    Route::post('requests/{id}/cancel', [ExternalQuoteController::class, 'cancelRequest'])->whereNumber('id');
    Route::post('requests/{id}/files', [ExternalQuoteController::class, 'uploadRequiredFile'])->whereNumber('id');
    Route::get('requests/{id}/files/{fileId}/download', [ExternalQuoteController::class, 'downloadRequiredFile'])
        ->whereNumber('id')->name('external-quotes.files.download');
    Route::delete('requests/{id}/files', [ExternalQuoteController::class, 'deleteRequiredFile'])->whereNumber('id');
    Route::get('requests/{id}/quotes', [ExternalQuoteController::class, 'listQuotes'])->whereNumber('id');
    Route::get('requests/{id}', [ExternalQuoteController::class, 'showRequest']);
    Route::post('upload-attachment', [ExternalQuoteController::class, 'uploadAttachment']);
    Route::post('{quoteId}/shortlist', [ExternalQuoteController::class, 'shortlistQuote'])->whereNumber('quoteId');
    Route::post('{quoteId}/reject', [ExternalQuoteController::class, 'rejectQuote'])->whereNumber('quoteId');
    Route::post('{quoteId}/award', [ExternalQuoteController::class, 'awardQuote'])->whereNumber('quoteId');
    Route::get('files/download', [ExternalQuoteController::class, 'downloadDraftFile']);
});
