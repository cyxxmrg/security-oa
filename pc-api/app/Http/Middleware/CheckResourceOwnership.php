<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 资源所有权中间件 (v0.3.11 P0)
 *
 * 解决 PRD 1.3 / 3.3 / 4.1 验收点：
 *   - 销售员 A 试图改销售员 B 的商机 → 403
 *   - 销售员只能看自己的线索/商机/报价单
 *   - 跨用户下载附件 → 403
 *
 * 工作机制：
 *   1. 路由定义时 use { 'owns:resource' }，resource 是 route 参数名（lead/opp/quote/referrer/...）
 *   2. 中间件从 route 参数拿到 model，自动判断 owner 字段
 *   3. 当前 user.id 不等于 model 的 owner 字段 → 403
 *   4. sales_manager / admin 角色可旁路
 *
 * 支持的 owner 字段自动识别：
 *   - lead/quote/referrer: owner_id
 *   - opp: sales_id
 *   - project: responsible_user_id
 *   - service_order: assigned_to
 *   - vehicle: responsible_user_id
 *
 * 用法: Route::put('{quote}', [...])->middleware('owns:quote');
 */
class CheckResourceOwnership
{
    /**
     * owner 字段映射表 (model_class 短名 => owner 字段)
     * route 参数名小写后查这个表
     */
    private array $ownerFields = [
        'lead'         => 'owner_id',
        'opportunity'  => 'sales_id',
        'opp'          => 'sales_id',
        'quote'        => 'created_by',
        'quotation'    => 'created_by',
        'referrer'     => 'owner_id',
        'project'      => 'responsible_user_id',
        'serviceorder' => 'assigned_to',
        'vehicle'      => 'responsible_user_id',
        'followup'     => 'user_id',
        'salesfollowup'=> 'user_id',
        // v0.3.11 块五: 附件 owner 字段是 follow_up_id，需查关联
        'salesfollowupattachment' => 'follow_up_id',
        'att'          => 'follow_up_id',
    ];

    public function handle(Request $request, Closure $next, ?string $resourceParam = null): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // 1) admin / manager 旁路
        if ($this->isAdmin($user) || $this->isManager($user)) {
            return $next($request);
        }

        // 2) 找 model
        $param = $resourceParam ?? $this->detectParam($request);
        $model = $param ? $request->route($param) : null;
        if (!$model) {
            return $next($request);
        }

        $classBase = strtolower(class_basename($model));
        $ownerField = $this->ownerFields[$classBase]
            ?? $this->ownerFields[strtolower($param)]
            ?? null;

        if (!$ownerField) {
            return $next($request);
        }

        // 3) 附件走 follow_up 关联
        if ($classBase === 'salesfollowupattachment' || strtolower($param) === 'att') {
            $att = $model;
            $followUpId = $att->follow_up_id;
            $followUp = \App\Models\SalesFollowUp::find($followUpId);
            if ($followUp && (int) $followUp->user_id === (int) $user->id) {
                return $next($request);
            }
            return response()->json([
                'code'    => 403,
                'message' => "无权操作该附件 (follow_up_id={$followUpId} 不属于你)",
            ], 403);
        }

        // 4) 普通资源 — owner 匹配
        $ownerId = $model->{$ownerField} ?? null;
        if ($ownerId === null) {
            return $next($request);
        }
        if ((int) $ownerId === (int) $user->id) {
            return $next($request);
        }

        return response()->json([
            'code'    => 403,
            'message' => "无权操作该资源 (需要 owner_id={$ownerId} 或销售经理权限)",
        ], 403);
    }

    private function detectParam(Request $request): ?string
    {
        // 从 route 找第一个 model 类型的参数
        foreach ($request->route()->parameters() as $name => $value) {
            if (is_object($value) && method_exists($value, 'getKey')) {
                return $name;
            }
        }
        return null;
    }

    private function isAdmin($user): bool
    {
        // Spatie HasRoles trait 提供 hasRole()
        return method_exists($user, 'hasRole') && $user->hasRole('admin');
    }

    private function isManager($user): bool
    {
        if (!method_exists($user, 'hasRole')) {
            return false;
        }
        return $user->hasRole('sales_manager')
            || $user->hasRole('manager')
            || $user->hasRole('admin');
    }
}
