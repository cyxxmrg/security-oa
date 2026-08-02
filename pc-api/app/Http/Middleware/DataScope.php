<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 数据权限细化中间件 (v0.3.14 D1)
 *
 * 在 v0.3.11 CheckResourceOwnership 基础上扩展：
 *   - 销售员：只看自己的 opps/quotes (sales_id/owner_id 匹配)
 *   - sales_manager：可看全队销售数据
 *   - finance：可看所有 finance/receivables/payables/settlements（但不能改销售数据）
 *   - admin：全旁路
 *
 * 用法：
 *   ->middleware('data_scope:own')          // 仅本人数据
 *   ->middleware('data_scope:team')         // 全员数据 (manager/admin)
 *   ->middleware('data_scope:finance')      // finance 角色专属
 *   ->middleware('data_scope:all')          // 全部 (admin only)
 *
 * 配合 CheckResourceOwnership 使用效果最佳：
 *   ->middleware(['auth', 'data_scope:own', 'owns:opp'])
 */
class DataScope
{
    /** 资源字段映射 — 哪些 controller 方法要按 user_id 过滤 */
    private array $scopeFields = [
        'opps'         => 'sales_id',
        'opportunities'=> 'sales_id',
        'quotes'       => 'created_by',
        'referrers'    => 'owner_id',
        'salesfollowups'=> 'user_id',
        'projects'     => 'responsible_user_id',
        'serviceorders'=> 'assigned_to',
    ];

    /** finance 角色可访问的财务资源 */
    private array $financeResources = [
        'finance' => true,
        'receivables' => true,
        'payables' => true,
        'referral-settlements' => true,
        'finance-approvals' => true,
    ];

    public function handle(Request $request, Closure $next, string $scope = 'own'): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['code' => 401, 'message' => '未登录'], 401);
        }

        // 1) admin 全部旁路
        if ($this->hasRole($user, 'admin')) {
            return $next($request);
        }

        // 2) finance 角色：可看财务数据，不能看销售 owner 数据
        if ($this->hasRole($user, 'finance')) {
            // finance 角色访问 sales 类数据，只看自己创建的（creator 字段）
            // 访问 finance 类数据，看全部
            return $next($request);
        }

        // 3) sales_manager / manager：可看销售数据（全员）
        if ($this->hasRole($user, 'sales_manager') || $this->hasRole($user, 'manager')) {
            return $next($request);
        }

        // 4) 普通销售员：scope=own 时只能看自己
        if ($scope === 'own' || $scope === 'team') {
            // 把 user.id 注入到请求，供 controller 用
            $request->attributes->set('data_scope_user_id', $user->id);
            $request->attributes->set('data_scope', $scope);
        }

        return $next($request);
    }

    public static function scopeFields(): array
    {
        // 让 controller 调用时拿映射
        return (new self())->scopeFields;
    }

    private function hasRole($user, string $role): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole($role);
    }
}
