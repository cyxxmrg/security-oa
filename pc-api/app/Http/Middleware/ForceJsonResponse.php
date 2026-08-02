<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 强制 API 请求返回 JSON
 *
 * 即使客户端没带 Accept: application/json，也确保 404/500/异常 走 withExceptions
 * 的 JSON 渲染分支，而不是 Laravel 默认的 HTML 错误页
 *
 * 范围：只对 /api/* 生效
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }
        return $next($request);
    }
}
