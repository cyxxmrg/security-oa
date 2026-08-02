<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * V0.5.0 L3 接口授权中间件
 *
 * 路由用法: ->middleware('permission:project.view')
 *
 * 优先级 (短路):
 *  1) admin 角色 (spatie) 放行
 *  2) spatie Permission (auth()->user()->can($perm)) 校验
 *  3) 不通过 → 403 + 写 audit log
 *
 * 特殊豁免:
 *  - 用户名为 'admin' 开头 → 老 admin 兼容
 *  - $perm 形如 'module.*' → 检查该 module 下所有 action 的 OR
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (!$user) {
            return $this->deny($request, $permission, 'unauthenticated');
        }

        // 0) system 账号放行 — 系统管理员可访问所有 system.* 路由
        if (($user->user_type ?? 'business') === 'system') {
            return $next($request);
        }

        // 1) admin 角色 (spatie) 放行
        // V0.5.3 临时权限: 用 activeRoles 过滤过期角色
        $userRoles = [];
        try {
            $userRoles = $user->activeRoles()->pluck('roles.name')->all();
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // 关系未就绪
        }
        if (in_array('admin', $userRoles, true)) {
            return $next($request);
        }
        // 2) spatie Permission 检查
        // 注意: 实际请求通过 sanctum guard, 但权限注册在 web guard, 必须显式指定
        $permExists = \Spatie\Permission\Models\Permission::where('name', $permission)
            ->where('guard_name', 'web')->exists();
        if (!$permExists) {
            Log::warning("CheckPermission: 权限 {$permission} 未在 DB 注册, 拒绝访问");
            return $this->deny($request, $permission, 'permission_not_defined');
        }

        try {
            // V0.5.3 临时权限: 用 hasActivePermissionTo 绕开 spatie 5min cache
            if ($user->hasActivePermissionTo($permission)) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            Log::warning("CheckPermission: hasActivePermissionTo 异常: " . $e->getMessage());
        }

        return $this->deny($request, $permission, 'forbidden');
    }

    private function deny(Request $request, string $permission, string $reason): Response
    {
        $user = $request->user();
        try {
            DB::table('system_logs')->insert([
                'user_id'     => $user?->id,
                'type'        => 'security',
                'module'      => 'permission',
                'action'      => 'permission_denied',
                'description' => "用户 #{$user?->id}({$user?->username}) 尝试访问 {$request->method()} {$request->path()} 但缺权限: {$permission}",
                'ip'          => $request->ip(),
                'user_agent'  => substr((string) $request->userAgent(), 0, 250),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('permission_denied log failed: ' . $e->getMessage());
        }

        return response()->json([
            'code'    => 403,
            'message' => "权限不足: 缺少 {$permission}",
            'reason'  => $reason,
        ], 403);
    }
}
