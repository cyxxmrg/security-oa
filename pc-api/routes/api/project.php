<?php

use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProcessController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\WorkOrderController;
use App\Http\Controllers\Api\RepairOrderController;
use App\Http\Controllers\Api\RepairShipmentController;
use App\Http\Controllers\Api\RepairMethodController;
use App\Http\Controllers\Api\RepairProgressLogController;
use App\Http\Controllers\Api\RepairStepPhotoController;
use App\Http\Controllers\Api\RepairCostSummaryController;
use Illuminate\Support\Facades\Route;

// ========== 项目管理 ==========
Route::prefix('projects')->middleware(['auth:sanctum', 'ensure_business', 'permission:project.view', 'field_mask'])->group(function () {
    Route::get('/', [ProjectController::class, 'index']);
    Route::post('/', [ProjectController::class, 'store'])->middleware('permission:project.create');
    Route::get('stages', [ProjectController::class, 'stages']);
    Route::get('dashboard-summary', [ProjectController::class, 'dashboardSummary']);
    Route::get('payment-calendar', [ProjectController::class, 'paymentCalendar']);
    Route::get('board', [ProjectController::class, 'board']);
    Route::get('suppliers', [ProjectController::class, 'suppliers']);
    Route::post('suppliers', [ProjectController::class, 'storeSupplier']);
    Route::put('{project}/stage', [ProjectController::class, 'updateStage']);
    Route::get('{project}/stage-logs', [ProjectController::class, 'stageLogs']);
    Route::post('{project}/stage-logs', [ProjectController::class, 'storeStageLog']);
    Route::get('{project}/construction-logs', [ProjectController::class, 'constructionLogs']);
    Route::post('{project}/construction-logs', [ProjectController::class, 'storeConstructionLog']);
    Route::get('{project}/suppliers', [ProjectController::class, 'projectSuppliers']);
    Route::get('{project}/materials', [ProjectController::class, 'materials']);
    Route::get('{project}/contracts', [ProjectController::class, 'projectContracts']);
    // V1.2.10: 独立合同列表端点 (支持 customer_id 过滤, 供发票申请选用)
    Route::get('contracts', [ProjectController::class, 'contractsList']);
    // V1.2.12m: 项目结算单
    Route::get('{project}/settlements', [ProjectController::class, 'settlements']);
    Route::post('{project}/settlements', [ProjectController::class, 'storeSettlement'])->middleware('permission:project.edit');
    Route::get('{project}/tracking', [ProjectController::class, 'tracking']);
    Route::get('{project}/maintenance', [ProjectController::class, 'maintenance']);
    Route::get('{project}', [ProjectController::class, 'show']);
    Route::put('{project}', [ProjectController::class, 'update']);
    Route::delete('{project}', [ProjectController::class, 'destroy']);
});

// ========== 深化施工 工序验收 ==========
Route::prefix('process')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    // 工序模板
    Route::get('industries', [ProcessController::class, 'industries']);
    Route::get('templates', [ProcessController::class, 'templates']);
    Route::post('templates', [ProcessController::class, 'storeTemplate'])->middleware('permission:process.create|process.edit');
    Route::post('templates/{template}/apply', [ProcessController::class, 'applyTemplate'])->middleware('permission:process.create|process.edit');
    Route::get('templates/{template}', [ProcessController::class, 'showTemplate']);
    Route::put('templates/{template}', [ProcessController::class, 'updateTemplate'])->middleware('permission:process.create|process.edit');
    Route::delete('templates/{template}', [ProcessController::class, 'destroyTemplate'])->middleware('permission:process.create|process.edit');
    // 工序实例
    Route::get('instances', [ProcessController::class, 'instances']);
    Route::post('instances', [ProcessController::class, 'storeInstance'])->middleware('permission:process.create|process.edit');
    Route::get('instances/{process}', [ProcessController::class, 'showInstance']);
    Route::put('instances/{process}', [ProcessController::class, 'updateInstance'])->middleware('permission:process.create|process.edit');
    Route::delete('instances/{process}', [ProcessController::class, 'destroyInstance'])->middleware('permission:process.create|process.edit');
    Route::post('instances/{process}/progress', [ProcessController::class, 'updateProgress'])->middleware('permission:process.create|process.edit');
    Route::post('instances/{process}/accept', [ProcessController::class, 'acceptInstance'])->middleware('permission:process.approve');
    Route::post('instances/{process}/reject', [ProcessController::class, 'rejectInstance'])->middleware('permission:process.approve');
    // 验收记录
    Route::get('inspections', [ProcessController::class, 'inspections']);
    Route::post('inspections', [ProcessController::class, 'storeInspection'])->middleware('permission:process.create|process.edit');
    Route::get('inspections/{inspection}', [ProcessController::class, 'showInspection']);
    Route::put('inspections/{inspection}', [ProcessController::class, 'updateInspection'])->middleware('permission:process.create|process.edit');
    Route::delete('inspections/{inspection}', [ProcessController::class, 'destroyInspection'])->middleware('permission:process.create|process.edit');
    // 影像
    Route::get('images', [ProcessController::class, 'images']);
    Route::post('images/upload', [ProcessController::class, 'uploadImages'])->middleware('permission:process.create|process.edit');
    Route::get('images/{image}', [ProcessController::class, 'showImage']);
    Route::put('images/{image}', [ProcessController::class, 'updateImageMeta'])->middleware('permission:process.create|process.edit');
    Route::delete('images/{image}', [ProcessController::class, 'destroyImage'])->middleware('permission:process.create|process.edit');
    // 签字
    Route::get('signatures', [ProcessController::class, 'signatures']);
    Route::post('signatures', [ProcessController::class, 'storeSignature'])->middleware('permission:process.create|process.edit');
    Route::post('signatures/{signature}/verify', [ProcessController::class, 'verifySignature'])->middleware('permission:process.create|process.edit');
    Route::delete('signatures/{signature}', [ProcessController::class, 'destroySignature'])->middleware('permission:process.create|process.edit');
});

// ========== 巡检计划 ==========
Route::prefix('inspections')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('stats', [InspectionController::class, 'stats']);
    Route::get('overview', [InspectionController::class, 'overview']);
    Route::get('active-contracts', [InspectionController::class, 'activeContracts']);
    Route::post('dev/create-contract', [InspectionController::class, 'createContract']);
    // 计划
    Route::get('plans', [InspectionController::class, 'index']);
    Route::post('plans', [InspectionController::class, 'store']);
    Route::get('plans/{id}', [InspectionController::class, 'show'])->whereNumber('id');
    Route::put('plans/{id}', [InspectionController::class, 'update'])->whereNumber('id');
    Route::delete('plans/{id}', [InspectionController::class, 'destroy'])->whereNumber('id');
    Route::post('plans/{id}/toggle', [InspectionController::class, 'toggle'])->whereNumber('id');
    Route::post('plans/{id}/cancel', [InspectionController::class, 'cancel'])->whereNumber('id');
    Route::post('plans/{id}/generate', [InspectionController::class, 'generate'])->whereNumber('id');
    // 任务
    Route::get('tasks', [InspectionController::class, 'tasks']);
    Route::get('tasks/mine', [InspectionController::class, 'myTasks']);
    Route::get('tasks/{id}', [InspectionController::class, 'taskDetail'])->whereNumber('id');
    Route::post('tasks/{id}/skip', [InspectionController::class, 'skip'])->whereNumber('id');
    Route::post('tasks/{id}/checkin', [InspectionController::class, 'checkin'])->whereNumber('id');
    // 记录
    Route::get('records', [InspectionController::class, 'records']);
    Route::get('records/{id}', [InspectionController::class, 'recordDetail'])->whereNumber('id');
    Route::post('records/{id}/checkout', [InspectionController::class, 'checkout'])->whereNumber('id');
    // 异常
    Route::get('issues', [InspectionController::class, 'issues']);
    Route::get('issues/{id}', [InspectionController::class, 'issueDetail'])->whereNumber('id');
    Route::post('issues/{id}/resolve', [InspectionController::class, 'resolveIssue'])->whereNumber('id');
    Route::post('issues/{id}/ignore', [InspectionController::class, 'ignoreIssue'])->whereNumber('id');
    Route::post('issues/{id}/convert-to-work-order', [InspectionController::class, 'convertIssue'])->whereNumber('id');
});

// ========== 维修工单 ==========
Route::prefix('work-orders')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('stats', [WorkOrderController::class, 'stats']);
    Route::get('/', [WorkOrderController::class, 'index']);
    Route::post('/', [WorkOrderController::class, 'store']);
    Route::get('{id}', [WorkOrderController::class, 'show'])->whereNumber('id');
    Route::put('{id}', [WorkOrderController::class, 'update'])->whereNumber('id');
    Route::delete('{id}', [WorkOrderController::class, 'destroy'])->whereNumber('id');
    Route::post('{id}/assign', [WorkOrderController::class, 'assign'])->whereNumber('id');
    Route::post('{id}/start', [WorkOrderController::class, 'start'])->whereNumber('id');
    Route::post('{id}/resolve', [WorkOrderController::class, 'resolve'])->whereNumber('id');
    Route::post('{id}/cancel', [WorkOrderController::class, 'cancel'])->whereNumber('id');
    Route::post('{id}/convert-to-repair', [WorkOrderController::class, 'convertToRepair'])->whereNumber('id');
});

// ========== 返修管理 ==========
Route::prefix('repair-orders')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('stats', [RepairOrderController::class, 'stats']);
    Route::get('/', [RepairOrderController::class, 'index']);
    Route::post('/', [RepairOrderController::class, 'store']);
    Route::get('{id}', [RepairOrderController::class, 'show'])->whereNumber('id');
    Route::put('{id}', [RepairOrderController::class, 'update'])->whereNumber('id');
    Route::delete('{id}', [RepairOrderController::class, 'destroy'])->whereNumber('id');
    Route::post('{id}/cancel', [RepairOrderController::class, 'cancel'])->whereNumber('id');
    Route::post('{id}/ship-out', [RepairOrderController::class, 'shipOut'])->whereNumber('id');
    Route::post('{id}/ship-back', [RepairOrderController::class, 'shipBack'])->whereNumber('id');
    Route::post('{id}/in-repair', [RepairOrderController::class, 'markInRepair'])->whereNumber('id');
    Route::post('{id}/repaired', [RepairOrderController::class, 'markRepaired'])->whereNumber('id');
    Route::post('{id}/close', [RepairOrderController::class, 'close'])->whereNumber('id');
});

// 物流子资源
Route::prefix('repair-orders/{repairOrderId}/shipments')->whereNumber('repairOrderId')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [RepairShipmentController::class, 'index']);
    Route::post('/', [RepairShipmentController::class, 'store']);
    Route::put('{id}', [RepairShipmentController::class, 'update'])->whereNumber('id');
    Route::delete('{id}', [RepairShipmentController::class, 'destroy'])->whereNumber('id');
});

// 维修方式
Route::prefix('repair-orders/{repairOrderId}/methods')->whereNumber('repairOrderId')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [RepairMethodController::class, 'index']);
    Route::post('/', [RepairMethodController::class, 'store']);
    Route::put('{id}', [RepairMethodController::class, 'update'])->whereNumber('id');
    Route::delete('{id}', [RepairMethodController::class, 'destroy'])->whereNumber('id');
});

// 维修进度日志
Route::prefix('repair-orders/{repairOrderId}/progress-logs')->whereNumber('repairOrderId')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [RepairProgressLogController::class, 'index']);
    Route::post('/', [RepairProgressLogController::class, 'store']);
    Route::delete('{id}', [RepairProgressLogController::class, 'destroy'])->whereNumber('id');
});

// 维修附件
Route::prefix('repair-orders/{repairOrderId}/attachments')->whereNumber('repairOrderId')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [RepairOrderController::class, 'listAttachments']);
    Route::get('{id}/download', [RepairOrderController::class, 'downloadAttachment'])->whereNumber('id');
    Route::post('/', [RepairOrderController::class, 'uploadAttachment']);
    Route::delete('{id}', [RepairOrderController::class, 'deleteAttachment'])->whereNumber('id');
});

// 维修过程照片
Route::prefix('step-photos')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [RepairStepPhotoController::class, 'index']);
    Route::post('/', [RepairStepPhotoController::class, 'store']);
    Route::delete('{id}', [RepairStepPhotoController::class, 'destroy'])->whereNumber('id');
});

// 维修成本归集
Route::prefix('repair-cost')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('overview', [RepairCostSummaryController::class, 'overview']);
    Route::get('by-month', [RepairCostSummaryController::class, 'byMonth']);
    Route::get('by-project', [RepairCostSummaryController::class, 'byProject']);
    Route::get('by-customer', [RepairCostSummaryController::class, 'byCustomer']);
    Route::get('by-method', [RepairCostSummaryController::class, 'byMethod']);
});
