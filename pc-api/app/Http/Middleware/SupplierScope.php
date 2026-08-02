<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * V0.4.2 供应商数据范围中间件
 *
 * 如果当前 user.type='supplier'，自动注入:
 *   - request attributes: 'current_supplier_id' = user.supplier_id
 *
 * Controller 收到后，所有查询/写入应使用该 ID 而非从 request 传入。
 *
 * 用法:
 *   Route::middleware(['auth', 'supplier.scope'])
 *        ->prefix('supplier-portal')
 *        ->group(...)
 */
class SupplierScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ($user->type ?? 'staff') === 'supplier') {
            $request->attributes->set('current_supplier_id', (int) $user->supplier_id);
        }
        return $next($request);
    }
}
