<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use App\Scopes\DataScope;
use App\Support\AuthScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V0.4.6 / V0.4.7 — Controller 列表/详情共享 Trait
 *
 * 提供:
 *  - applyDataScope()  列表 query 套/旁路 scope
 *  - findScoped()      详情查带 scope 限制, 不存在返回 null
 *  - respondNotFound() 404/403 友好提示 (区分 admin/finance vs 普通员工)
 *  - logScopeDenied()  V0.4.7 写审计日志
 *
 * 用法:
 *   class WarrantyController {
 *       use HandlesDataScope;
 *       public function show(Request $r, int $id) {
 *           $m = $this->findScoped($r, Warranty::class, $id);
 *           return $m ? response()->json(['code'=>0,'data'=>$m])
 *                      : $this->respondNotFound($r, 'warranty', $id);
 *       }
 *   }
 */
trait HandlesDataScope
{
    /**
     * 给列表 query 应用 data scope
     *  - admin/finance: 一律返回 ALL
     *  - 普通员工: ?scope=all 拒绝 (403)
     *  - 默认: 套 scope
     */
    protected function applyDataScope(\Illuminate\Database\Eloquent\Builder $query, Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $user = $request->user();
        $wantsAll = $request->boolean('scope_all') || $request->query('scope') === 'all';

        if (AuthScope::isUnrestricted($user)) {
            return $query; // admin/finance 全量
        }

        if ($wantsAll) {
            abort(403, '权限不足: 仅管理员/财务可查看全部数据');
        }

        // 套默认 scope (global scope 已挂, query() 默认带)
        return $query;
    }

    /**
     * 找一条带 scope 限制的记录
     *  - 步骤 1: 用 global scope 找, 找到 → 返回
     *  - 步骤 2: 找不到, 临时 bypass scope 再找一次
     *    - 找到 → 说明是被 scope 拦了, 写 audit log, 返回 null
     *    - 还是没 → 真的不存在
     */
    protected function findScoped(Request $request, string $modelClass, int $id): ?Model
    {
        $record = $modelClass::query()->find($id);
        if ($record) {
            return $record;
        }

        // bypass 再查一次, 区分"被 scope 拦" vs "真不存在"
        $realRecord = $modelClass::query()->withoutGlobalScope(DataScope::class)->find($id);
        if ($realRecord) {
            // 审计: scope 拒绝访问
            DataScope::logDeniedAccess(
                (new $modelClass)->getTable(),
                (int) $request->user()?->id,
                $id,
                'find'
            );
        }
        return null;
    }

    /**
     * 详情页 404/403 统一响应
     *  - admin/finance: 始终返回 404 (因为他们用 bypass 都不会 null, 走到这里说明真不存在)
     *  - 普通员工: 返回 403 + "不存在或您没有访问权限" (避免泄漏资源存在性)
     */
    protected function respondNotFound(Request $request, string $resourceName = '资源', int $id = 0): JsonResponse
    {
        $user = $request->user();
        if (AuthScope::isAdmin($user) || AuthScope::isFinance($user)) {
            return response()->json(['code' => 404, 'message' => $resourceName . '不存在'], 404);
        }
        return response()->json([
            'code'    => 403,
            'message' => $resourceName . '不存在或您没有访问权限',
        ], 403);
    }
}
