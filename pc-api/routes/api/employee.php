<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeOnboardingController;
use App\Http\Controllers\Api\EmployeeResignationController;
use App\Http\Controllers\Api\RoleController;
use Illuminate\Support\Facades\Route;

// ========== 员工管理 (V1.2.4u: system + 业务用户都能进) ==========
Route::prefix('employees')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->withoutMiddleware('ensure_business');
    Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employee.create');

    // 部门
    Route::get('departments', [EmployeeController::class, 'departments'])->withoutMiddleware('ensure_business');
    Route::post('departments', [EmployeeController::class, 'storeDepartment']);
    Route::put('departments/{department}', [EmployeeController::class, 'updateDepartment']);
    Route::delete('departments/{department}', [EmployeeController::class, 'destroyDepartment']);

    // 岗位
    Route::get('positions', [EmployeeController::class, 'positions'])->withoutMiddleware('ensure_business');
    Route::post('positions', [EmployeeController::class, 'storePosition']);
    Route::put('positions/{position}', [EmployeeController::class, 'updatePosition']);
    Route::delete('positions/{position}', [EmployeeController::class, 'destroyPosition']);

    // 技能
    Route::get('skills', [EmployeeController::class, 'skills'])->withoutMiddleware('ensure_business');
    Route::post('skills', [EmployeeController::class, 'storeSkill']);
    Route::put('skills/{skillTag}', [EmployeeController::class, 'updateSkill']);
    Route::delete('skills/{skillTag}', [EmployeeController::class, 'destroySkill']);
    Route::post('skills/{skillTag}/attach', [EmployeeController::class, 'attachSkill']);
    Route::post('skills/{skillTag}/detach', [EmployeeController::class, 'detachSkill']);
    Route::get('{user}/skills', [EmployeeController::class, 'userSkills'])->withoutMiddleware('ensure_business');

    Route::post('import', [EmployeeController::class, 'import']);
    Route::get('certificates', [EmployeeController::class, 'certificates']);

    // 员工 CRUD（放最后）
    Route::get('{user}', [EmployeeController::class, 'show']);
    Route::put('{user}', [EmployeeController::class, 'update']);
    Route::delete('{user}', [EmployeeController::class, 'destroy']);
});

// ========== 员工入职档案 ==========
Route::prefix('employee-onboardings')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [EmployeeOnboardingController::class, 'index']);
    Route::post('/', [EmployeeOnboardingController::class, 'store']);
    Route::get('{onboarding}', [EmployeeOnboardingController::class, 'show']);
    Route::put('{onboarding}', [EmployeeOnboardingController::class, 'update']);
    Route::delete('{onboarding}', [EmployeeOnboardingController::class, 'destroy']);
});

// ========== 员工离职记录 ==========
Route::prefix('employee-resignations')->middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    Route::get('/', [EmployeeResignationController::class, 'index']);
    Route::post('/', [EmployeeResignationController::class, 'store']);
    Route::get('settlement-preview', [EmployeeResignationController::class, 'settlementPreview']);
    Route::get('{resignation}', [EmployeeResignationController::class, 'show']);
    Route::put('{resignation}', [EmployeeResignationController::class, 'update']);
    Route::post('{resignation}/submit', [EmployeeResignationController::class, 'submit']);
    Route::post('{resignation}/approve', [EmployeeResignationController::class, 'approve']);
    Route::post('{resignation}/cancel', [EmployeeResignationController::class, 'cancel']);
    Route::post('{resignation}/complete', [EmployeeResignationController::class, 'complete']);
});

// ========== 用户管理 (前端 /api/users 别名) ==========
// P0-1 修复: 加 permission:user.manage 中间件, 防止业务用户越权改任意 user
Route::prefix('users')->middleware(['auth:sanctum', 'ensure_business', 'permission:user.manage'])->group(function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::post('/', [EmployeeController::class, 'store']);
    // 字面量子路径必须在 {user} 通配之前
    Route::get('{user}', [EmployeeController::class, 'show']);
    Route::put('{user}', [EmployeeController::class, 'update']);
    Route::delete('{user}', [EmployeeController::class, 'destroy']);
    Route::post('{user}/reset-password', [EmployeeController::class, 'resetPassword']);
});
