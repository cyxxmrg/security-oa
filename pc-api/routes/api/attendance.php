<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ScheduleController;
use Illuminate\Support\Facades\Route;

// ========== 考勤管理 ==========
Route::prefix('attendance')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('overview', [AttendanceController::class, 'overview'])->withoutMiddleware('ensure_business')->middleware('permission:attendance.view');
    Route::get('calendar', [AttendanceController::class, 'calendar'])->withoutMiddleware('ensure_business')->middleware('permission:attendance.view');
    Route::post('clock-in', [AttendanceController::class, 'clockIn']);
    Route::post('clock-out', [AttendanceController::class, 'clockOut']);
    Route::post('field-clock', [AttendanceController::class, 'fieldClock']);
    Route::get('today', [AttendanceController::class, 'today'])->withoutMiddleware('ensure_business');
    Route::post('supplement', [AttendanceController::class, 'supplement']);
    // ===== 合并 origin/main: 两端点集合一致, 取本地(HEAD)更严格的逐端点权限门控 =====
    //     记录/报表/请假/加班/统计沿用 withoutMiddleware('ensure_business') 允许 system 账号只读访问
    Route::get('records', [AttendanceController::class, 'records'])->withoutMiddleware('ensure_business')->middleware('permission:attendance.record');
    Route::get('report', [AttendanceController::class, 'report'])->withoutMiddleware('ensure_business')->middleware('permission:attendance.report');
    Route::get('leave', [AttendanceController::class, 'leaveRequests'])->withoutMiddleware('ensure_business');
    Route::post('leave', [AttendanceController::class, 'storeLeaveRequest'])->middleware('permission:approval.mine');
    Route::post('leave/{leave}/approve', [AttendanceController::class, 'approveLeave'])->middleware('permission:attendance.leave');
    Route::delete('leave/{leave}', [AttendanceController::class, 'destroyLeaveRequest'])->middleware('permission:approval.mine');
    Route::get('overtime', [AttendanceController::class, 'overtimeRequests'])->withoutMiddleware('ensure_business');
    Route::post('overtime', [AttendanceController::class, 'storeOvertimeRequest'])->middleware('permission:approval.mine');
    Route::post('overtime/{overtime}/approve', [AttendanceController::class, 'approveOvertime'])->middleware('permission:attendance.overtime');
    Route::delete('overtime/{overtime}', [AttendanceController::class, 'destroyOvertimeRequest'])->middleware('permission:approval.mine');
    Route::get('/', [AttendanceController::class, 'overview'])->withoutMiddleware('ensure_business')->middleware('permission:attendance.view');
    Route::get('stats', [AttendanceController::class, 'stats'])->withoutMiddleware('ensure_business')->middleware('permission:attendance.view');
});

// ========== 排班管理 ==========
Route::prefix('schedules')->middleware(['auth:sanctum', 'ensure_business', 'permission:schedule.view'])->group(function () {
    // ===== 合并 origin/main: 保留两侧全部端点 =====
    //     读端点维持前缀公共 permission:schedule.view 门槛(manager 亦需读班次/班组才能配置排班);
    //     写端点取两侧权限并集(OR: 本地 attendance.* | 远端 schedule.manage), CheckPermission 支持 a|b 任一命中放行
    // 班次
    Route::get('shifts', [ScheduleController::class, 'listShifts']);
    Route::post('shifts', [ScheduleController::class, 'storeShift'])->middleware('permission:attendance.shifts|schedule.manage');
    Route::put('shifts/{shift}', [ScheduleController::class, 'updateShift'])->middleware('permission:attendance.shifts|schedule.manage');
    Route::delete('shifts/{shift}', [ScheduleController::class, 'destroyShift'])->middleware('permission:attendance.shifts|schedule.manage');

    // 班组
    Route::get('groups', [ScheduleController::class, 'listGroups']);
    Route::post('groups', [ScheduleController::class, 'storeGroup'])->middleware('permission:attendance.groups|schedule.manage');
    Route::put('groups/{group}', [ScheduleController::class, 'updateGroup'])->middleware('permission:attendance.groups|schedule.manage');
    Route::delete('groups/{group}', [ScheduleController::class, 'destroyGroup'])->middleware('permission:attendance.groups|schedule.manage');
    Route::post('groups/{group}/members', [ScheduleController::class, 'syncGroupMembers'])->middleware('permission:attendance.groups|schedule.manage');
    Route::post('groups/{group}/add-member', [ScheduleController::class, 'addGroupMember'])->middleware('permission:attendance.groups|schedule.manage');
    Route::delete('groups/{group}/members/{user}', [ScheduleController::class, 'removeGroupMember'])->middleware('permission:attendance.groups|schedule.manage');

    // 排班 (smart-suggest/stats 为本地独有端点, 保留并兼容远端 schedule.manage 角色模型)
    Route::get('/', [ScheduleController::class, 'index']);
    Route::post('/', [ScheduleController::class, 'batchSave'])->middleware('permission:attendance.schedule|schedule.manage');
    Route::post('batch-by-group', [ScheduleController::class, 'batchByGroup'])->middleware('permission:attendance.schedule|schedule.manage');
    Route::delete('{schedule}', [ScheduleController::class, 'destroy'])->middleware('permission:attendance.schedule|schedule.manage');
    Route::get('smart-suggest', [ScheduleController::class, 'smartSuggest'])->middleware('permission:attendance.schedule|schedule.manage');
    Route::get('stats', [ScheduleController::class, 'stats'])->middleware('permission:attendance.schedule|schedule.manage');
    Route::get('my-schedule', [ScheduleController::class, 'mySchedule']);
    Route::get('next-reminder', [ScheduleController::class, 'nextReminder']);
    Route::get('default-shift', [ScheduleController::class, 'defaultShift'])->withoutMiddleware('ensure_business');
});
