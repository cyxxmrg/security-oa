<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ScheduleController;
use Illuminate\Support\Facades\Route;

// ========== 考勤管理 ==========
Route::prefix('attendance')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('overview', [AttendanceController::class, 'overview'])->withoutMiddleware('ensure_business');
    Route::get('calendar', [AttendanceController::class, 'calendar'])->withoutMiddleware('ensure_business');
    Route::post('clock-in', [AttendanceController::class, 'clockIn']);
    Route::post('clock-out', [AttendanceController::class, 'clockOut']);
    Route::post('field-clock', [AttendanceController::class, 'fieldClock']);
    Route::get('today', [AttendanceController::class, 'today'])->withoutMiddleware('ensure_business');
    Route::post('supplement', [AttendanceController::class, 'supplement']);
    Route::get('records', [AttendanceController::class, 'records'])->withoutMiddleware('ensure_business');
    Route::get('report', [AttendanceController::class, 'report'])->middleware('permission:attendance.report')->withoutMiddleware('ensure_business');
    Route::get('leave', [AttendanceController::class, 'leaveRequests'])->withoutMiddleware('ensure_business');
    Route::post('leave', [AttendanceController::class, 'storeLeaveRequest']);
    Route::post('leave/{leave}/approve', [AttendanceController::class, 'approveLeave'])->middleware('permission:attendance.leave');
    Route::delete('leave/{leave}', [AttendanceController::class, 'destroyLeaveRequest']);
    Route::get('overtime', [AttendanceController::class, 'overtimeRequests'])->withoutMiddleware('ensure_business');
    Route::post('overtime', [AttendanceController::class, 'storeOvertimeRequest']);
    Route::post('overtime/{overtime}/approve', [AttendanceController::class, 'approveOvertime'])->middleware('permission:attendance.overtime');
    Route::delete('overtime/{overtime}', [AttendanceController::class, 'destroyOvertimeRequest']);
    Route::get('/', [AttendanceController::class, 'overview'])->withoutMiddleware('ensure_business');
    Route::get('stats', [AttendanceController::class, 'stats'])->withoutMiddleware('ensure_business');
});

// ========== 排班管理 ==========
Route::prefix('schedules')->middleware(['auth:sanctum', 'ensure_business', 'permission:schedule.view'])->group(function () {
    // 班次
    Route::get('shifts', [ScheduleController::class, 'listShifts']);
    Route::post('shifts', [ScheduleController::class, 'storeShift'])->middleware('permission:schedule.manage');
    Route::put('shifts/{shift}', [ScheduleController::class, 'updateShift'])->middleware('permission:schedule.manage');
    Route::delete('shifts/{shift}', [ScheduleController::class, 'destroyShift'])->middleware('permission:schedule.manage');

    // 班组
    Route::get('groups', [ScheduleController::class, 'listGroups']);
    Route::post('groups', [ScheduleController::class, 'storeGroup'])->middleware('permission:schedule.manage');
    Route::put('groups/{group}', [ScheduleController::class, 'updateGroup'])->middleware('permission:schedule.manage');
    Route::delete('groups/{group}', [ScheduleController::class, 'destroyGroup'])->middleware('permission:schedule.manage');
    Route::post('groups/{group}/members', [ScheduleController::class, 'syncGroupMembers'])->middleware('permission:schedule.manage');
    Route::post('groups/{group}/add-member', [ScheduleController::class, 'addGroupMember'])->middleware('permission:schedule.manage');
    Route::delete('groups/{group}/members/{user}', [ScheduleController::class, 'removeGroupMember'])->middleware('permission:schedule.manage');

    // 排班
    Route::get('/', [ScheduleController::class, 'index']);
    Route::post('/', [ScheduleController::class, 'batchSave'])->middleware('permission:schedule.manage');
    Route::post('batch-by-group', [ScheduleController::class, 'batchByGroup'])->middleware('permission:schedule.manage');
    Route::delete('{schedule}', [ScheduleController::class, 'destroy'])->middleware('permission:schedule.manage');
    Route::get('my-schedule', [ScheduleController::class, 'mySchedule']);
    Route::get('smart-suggest', [ScheduleController::class, 'smartSuggest']);
    Route::get('next-reminder', [ScheduleController::class, 'nextReminder']);
    Route::get('stats', [ScheduleController::class, 'stats']);
    Route::get('default-shift', [ScheduleController::class, 'defaultShift'])->withoutMiddleware('ensure_business');
});
