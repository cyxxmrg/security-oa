<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * V0.5.3 临时权限 - 路径级过期安全网中间件
 *
 * 用法（挂在权限敏感路径 group）:
 *   Route::middleware(['auth:sanctum', 'role_active', 'permission:project.view'])
 *
 * 行为:
 *   - 用户没有任何 spatie 角色 → 403 (无角色)
 *   - 用户所有角色都过期 → 403 (角色已过期)
 *   - 至少一个角色有效 → 放行
 *
 * 这个中间件不依赖 spatie cache（绕过），实时查 DB。
 * 大多数路由不需要挂这个 — CheckPermission 已用 hasActivePermissionTo 实时校验。
 * 只在以下场景显式挂: 业务侧"角色过期应完全拒绝"的强诉求。
 */
class CheckRoleActive
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

        // 1) 至少一个有效角色
        try {
            $activeCount = $user->activeRoles()->count();
        } catch (\Throwable $e) {
            Log::warning('CheckRoleActive: activeRoles 异常 ' . $e->getMessage());
            $activeCount = 0;
        }

        if ($activeCount > 0) {
            return $next($request);
        }

        // 2) 检查是否真的"一个角色都没有" vs "全过期"
        $totalCount = 0;
        try {
            $totalCount = \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->where('model_type', \App\Models\User::class)
                ->where('model_id', $user->id)
                ->count();
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // ignore
        }

        if ($totalCount === 0) {
            return response()->json([
                'code'    => 403,
                'message' => '您未分配任何角色，请联系管理员',
            ], 403);
        }

        return response()->json([
            'code'    => 403,
            'message' => '您的所有角色均已过期，请联系管理员续期',
        ], 403);
    }
}
