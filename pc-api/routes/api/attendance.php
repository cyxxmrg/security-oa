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
    Route::get('records', [AttendanceController::class, 'records'])->withoutMiddleware('ensure_business')->middleware('permission:attendance.record');
    Route::get('report', [AttendanceController::class, 'report'])->withoutMiddleware('ensure_business')->middleware('permission:attendance.report');
    Route::get('leave', [AttendanceController::class, 'leaveRequests'])->withoutMiddleware('ensure_business');
    Route::post('leave', [AttendanceController::class, 'storeLeaveRequest']);
    Route::post('leave/{leave}/approve', [AttendanceController::class, 'approveLeave'])->middleware('permission:attendance.leave');
    Route::delete('leave/{leave}', [AttendanceController::class, 'destroyLeaveRequest']);
    Route::get('overtime', [AttendanceController::class, 'overtimeRequests'])->withoutMiddleware('ensure_business');
    Route::post('overtime', [AttendanceController::class, 'storeOvertimeRequest']);
    Route::post('overtime/{overtime}/approve', [AttendanceController::class, 'approveOvertime'])->middleware('permission:attendance.overtime');
    Route::delete('overtime/{overtime}', [AttendanceController::class, 'destroyOvertimeRequest']);
    Route::get('/', [AttendanceController::class, 'overview'])->withoutMiddleware('ensure_business');
    Route::get('stats', [AttendanceController::class, 'stats'])->withoutMiddleware('ensure_business')->middleware('permission:attendance.view');
});

// ========== 排班管理 ==========
Route::prefix('schedules')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    // 班次
    Route::middleware('permission:attendance.shifts')->group(function () {
        Route::get('shifts', [ScheduleController::class, 'listShifts']);
        Route::post('shifts', [ScheduleController::class, 'storeShift']);
        Route::put('shifts/{shift}', [ScheduleController::class, 'updateShift']);
        Route::delete('shifts/{shift}', [ScheduleController::class, 'destroyShift']);
    });

    // 班组
    Route::middleware('permission:attendance.groups')->group(function () {
        Route::get('groups', [ScheduleController::class, 'listGroups']);
        Route::post('groups', [ScheduleController::class, 'storeGroup']);
        Route::put('groups/{group}', [ScheduleController::class, 'updateGroup']);
        Route::delete('groups/{group}', [ScheduleController::class, 'destroyGroup']);
        Route::post('groups/{group}/members', [ScheduleController::class, 'syncGroupMembers']);
        Route::post('groups/{group}/add-member', [ScheduleController::class, 'addGroupMember']);
        Route::delete('groups/{group}/members/{user}', [ScheduleController::class, 'removeGroupMember']);
    });

    // 排班
    Route::middleware('permission:attendance.schedule')->group(function () {
        Route::get('/', [ScheduleController::class, 'index']);
        Route::post('/', [ScheduleController::class, 'batchSave']);
        Route::post('batch-by-group', [ScheduleController::class, 'batchByGroup']);
        Route::delete('{schedule}', [ScheduleController::class, 'destroy']);
        Route::get('smart-suggest', [ScheduleController::class, 'smartSuggest']);
        Route::get('stats', [ScheduleController::class, 'stats']);
    });
    Route::get('my-schedule', [ScheduleController::class, 'mySchedule']);
    Route::get('next-reminder', [ScheduleController::class, 'nextReminder']);
    Route::get('default-shift', [ScheduleController::class, 'defaultShift'])->withoutMiddleware('ensure_business');
});
