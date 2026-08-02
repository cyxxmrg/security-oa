<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// ========== 公开路由（无需认证）==========
// 父 routes/api.php 加了 ['auth:sanctum', 'ensure_business'] group, login 必须排除
// 注意: 用两个 withoutMiddleware 单参数, 避免 middleware 变 array (Scramble 不支持 array 形式解析)
Route::prefix('auth')->group(function () {
    // T2 登录限流: 1 分钟 30 次防暴力破解, LoginThrottle 5 次失败锁 30 分钟
    Route::post('login', [AuthController::class, 'login'])
        ->withoutMiddleware('auth:sanctum')
        ->withoutMiddleware('ensure_business')
        ->middleware('throttle:30,1')
        ->middleware(\App\Http\Middleware\LoginThrottle::class);
});

// ========== 需要认证的认证相关路由 ==========
// V1.2.9f: system 账号也要能 logout / change-password (顶部铃铛 + 改密)
// 整组从 ensure_business group 拎出来, 否则 system 一登出就被卡
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    // V1.2 fix: /auth/userinfo 和 /auth/me 必须允许 system 访问 (登录后路由守卫要先拿 user_type)
    Route::get('auth/userinfo', [AuthController::class, 'userInfo']);
    Route::get('auth/me', [AuthController::class, 'userInfo']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    // T2: 修改密码限流 1 分钟 5 次 — 防爆破
    // V1.2: system 也要能改密 (强制改密)
    Route::post('auth/change-password', [AuthController::class, 'changePassword'])
        ->middleware('throttle:5,1');
});
