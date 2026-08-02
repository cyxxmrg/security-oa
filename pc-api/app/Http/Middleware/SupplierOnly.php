<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * V0.4.2 供应商账号专用中间件
 *
 * 校验：当前登录 user.type === 'supplier'，否则 403
 */
class SupplierOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['code' => 401, 'message' => '未登录'], 401);
        }
        if (($user->type ?? 'staff') !== 'supplier') {
            return response()->json(['code' => 403, 'message' => '仅供应商账号可访问'], 403);
        }
        if (empty($user->supplier_id)) {
            return response()->json(['code' => 403, 'message' => '供应商账号未关联供应商'], 403);
        }
        return $next($request);
    }
}
