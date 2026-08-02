<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - 安防运维OA系统
|--------------------------------------------------------------------------
*/

// ========== 健康检查端点 (T7) — 公开路由,无认证 ==========
// 放最前面: 1) 监控系统探活用  2) 负载均衡器后端健康检查
Route::get('/health', function () {
    $checks = [
        'status' => 'ok',
        'time'   => now()->toIso8601String(),
    ];

    try {
        $r = DB::select('SELECT 1 AS ok');
        $checks['db'] = (! empty($r) && $r[0]->ok == 1) ? 'up' : 'down';
    } catch (\Throwable $e) {
        $checks['db'] = 'down';
    }

    try {
        Cache::put('health_check', '1', 5);
        $v = Cache::get('health_check');
        $checks['cache'] = ($v === '1') ? 'up' : 'down';
    } catch (\Throwable $e) {
        $checks['cache'] = 'down';
    }

    $allUp = ($checks['db'] === 'up') && ($checks['cache'] === 'up');
    return response()->json([
        'code'    => $allUp ? 0 : 1001,
        'message' => $allUp ? 'healthy' : 'degraded',
        'data'    => $checks,
    ], $allUp ? 200 : 503);
});

// ========== 加载模块路由 (按业务模块拆分) ==========
// V1.1 admin 隔离: 业务路由一刀切 ensure_business
// V1.2.9f: auth.php 拎到外面, 因为 system 也要能 logout/change-password/me, 不该被 ensure_business 卡
//   finance.php / system.php 子文件内已经个别加 ->withoutMiddleware('ensure_business')
// system 类路由在子文件内用 ->withoutMiddleware('ensure_business')->middleware('ensure_system') 单独开启
Route::middleware(['auth:sanctum', 'ensure_business'])->group(function () {
    require __DIR__.'/api/attendance.php';
    require __DIR__.'/api/employee.php';
    require __DIR__.'/api/customer.php';
    require __DIR__.'/api/project.php';
    require __DIR__.'/api/sales.php';
    require __DIR__.'/api/purchase.php';
    require __DIR__.'/api/finance.php';
    require __DIR__.'/api/system.php';
    // V1.2.7 E2: BI 报表 (业务模块, 业务管理员可看)
    require __DIR__.'/api/analytics.php';
});

// ========== V1.2.9f: auth 路由独立加载 (system 也要能 logout/me/change-password) ==========
// 不能放进 ensure_business group, 不然 system 登录后被 403 卡死
Route::middleware(['auth:sanctum'])->group(function () {
    require __DIR__.'/api/auth.php';
});

// ========== 公开路由 (无需认证) ==========
require __DIR__.'/api/portal.php';

// ========== 顶层部门/岗位快捷路由 ==========
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('departments', [App\Http\Controllers\Api\EmployeeController::class, 'departments']);
    Route::get('positions', [App\Http\Controllers\Api\EmployeeController::class, 'positions']);
});

// ========== V1.2 写操作只允许 system ==========
Route::middleware(['auth:sanctum', 'ensure_system'])->group(function () {
    Route::post('departments', [App\Http\Controllers\Api\EmployeeController::class, 'storeDepartment']);
    Route::put('departments/{department}', [App\Http\Controllers\Api\EmployeeController::class, 'updateDepartment']);
    Route::delete('departments/{department}', [App\Http\Controllers\Api\EmployeeController::class, 'destroyDepartment']);
});
