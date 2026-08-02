<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * 纯 API 项目 — 未认证永远返回 401 (由 withExceptions 统一处理 JSON 响应)
 * 不走 web login 路由 — 项目无 login 路由
 *
 * 注意：此中间件需要在 bootstrap/app.php 中通过 $middleware->alias 替换默认 'auth' 别名
 * 实际未使用 (上面通过 $exceptions->render 覆盖)，但保留作为备份
 */
class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        // 永远不重定向 — 让 withExceptions 接管返回 401 JSON
        return null;
    }
}
