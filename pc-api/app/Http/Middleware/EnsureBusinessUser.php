<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * V1.1 admin 隔离 - 业务路由中间件
 *
 * 用法（挂在业务路由 group）:
 *   Route::middleware(['auth:sanctum', 'permission:project.view', 'ensure_business'])
 *
 * 行为:
 *   - 未登录 → 401 (Authenticate 处理)
 *   - user_type='system' → 403 (系统账号不能操作业务)
 *   - 其他 → 放行
 *
 * 注意: admin 业务管理员 user_type='admin' 可以通过, 仅 system 被拦截。
 * spatie 权限由 CheckPermission 单独判断, 本中间件不重复。
 */
class EnsureBusinessUser
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

        // 系统账号不能进业务
        if (($user->user_type ?? 'business') === 'system') {
            return response()->json([
                'code'    => 403,
                'message' => '系统账号不能操作业务数据',
            ], 403);
        }

        return $next($request);
    }
}
