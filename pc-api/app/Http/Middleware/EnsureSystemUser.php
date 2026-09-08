<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * V1.1 admin 隔离 - 系统路由中间件
 *
 * 用法（挂在系统管理路由 group）:
 *   Route::middleware(['auth:sanctum', 'ensure_system'])
 *
 * 行为:
 *   - 未登录 → 401
 *   - user_type != 'system' → 403 (需要系统管理员权限)
 *   - user_type = 'system' → 放行
 *
 * 用途:
 *   - 初始化向导 / 许可证管理 / 数据库重置 / 系统设置
 *   - 只有 user_type=system 的系统管理账号能进
 */
class EnsureSystemUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'code'    => 401,
                'message' => '未认证',
            ], 401);
        }

        if (($user->user_type ?? 'business') !== 'system') {
            return response()->json([
                'code'    => 403,
                'message' => '需要系统管理员权限',
            ], 403);
        }

        return $next($request);
    }
}
