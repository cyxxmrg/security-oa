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
 * 路由用法:
 *  - 单权限:   ->middleware('permission:project.view')
 *  - OR 多权限: ->middleware('permission:sales.create|sales.edit')   ← 任一命中即放行
 *  - 模块通配:  ->middleware('permission:vehicle.*')                 ← 该模块任一权限命中即放行
 *
 * 优先级 (短路):
 *  1) system 账号放行
 *  2) admin 角色 (spatie) 放行
 *  3) 候选权限点逐个校验 (OR 语义), 任一命中放行
 *  4) 不通过 → 403 + 写 audit log
 *
 * V1.4.4 修复 (安全审计): 此前直接把 "a|b" 整串当权限名查库, 必然判定
 * "权限未定义" → 所有使用 OR 语法的路由 (99 处) 对非 admin 角色一律 403。
 * 现改为拆分候选后逐个校验, 并且只有"拆分后无一个候选在 DB 注册"才判未定义。
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
        // 2) 解析候选权限点 — 支持 "a|b" 任一权限语义 (与项目既有路由声明保持一致) 与 "module.*" 通配
        //    注意: 实际请求通过 sanctum guard, 但权限注册在 web guard, 必须显式指定
        $candidates = $this->resolveCandidates($permission);
        if (empty($candidates)) {
            Log::warning("CheckPermission: 权限 {$permission} 未在 DB 注册, 拒绝访问");
            return $this->deny($request, $permission, 'permission_not_defined');
        }

        foreach ($candidates as $perm) {
            try {
                // V0.5.3 临时权限: 用 hasActivePermissionTo 绕开 spatie 5min cache
                if ($user->hasActivePermissionTo($perm)) {
                    return $next($request);
                }
            } catch (\Throwable $e) {
                Log::warning("CheckPermission: hasActivePermissionTo({$perm}) 异常: " . $e->getMessage());
            }
        }

        return $this->deny($request, $permission, 'forbidden');
    }

    /**
     * 把路由声明的权限表达式解析成"DB 中确实存在"的权限名列表
     *  - "a|b"      → [a, b] 中已注册的部分
     *  - "module.*" → module.xxx 全部已注册权限
     * 返回空数组表示一个都没注册 (调用方按 permission_not_defined 拒绝)
     */
    private function resolveCandidates(string $expression): array
    {
        $parts = array_filter(array_map('trim', explode('|', $expression)), fn($p) => $p !== '');
        $resolved = [];
        foreach ($parts as $part) {
            if (str_ends_with($part, '.*')) {
                $prefix = substr($part, 0, -1); // 'vehicle.*' → 'vehicle.'
                $names = \Spatie\Permission\Models\Permission::where('guard_name', 'web')
                    ->where('name', 'like', $prefix . '%')
                    ->pluck('name')->all();
                $resolved = array_merge($resolved, $names);
                continue;
            }
            $exists = \Spatie\Permission\Models\Permission::where('name', $part)
                ->where('guard_name', 'web')->exists();
            if ($exists) {
                $resolved[] = $part;
            }
        }
        return array_values(array_unique($resolved));
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
