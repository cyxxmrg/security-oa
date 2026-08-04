<?php

namespace App\Http\Middleware;

use App\Support\SensitiveDataRedactor;
use App\Services\ErrorReporter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 审计日志中间件
 *
 * 监听所有写操作 (POST/PUT/PATCH/DELETE) 并记录到 audit_logs 表
 *
 * 排除路径:
 *   - /api/auth/login  (登录入口 — 单独在 AuthController 写 system_logs)
 *   - /api/settings    (系统设置 — 写频繁，且非敏感)
 *   - /api/health      (健康检查 — 监控探活)
 *
 * 注意: 此中间件在 throttle:api 之后、auth:sanctum 之前执行
 *       未认证用户也能触发写审计 (记录 user_id = null)
 */
class AuditLogger
{
    /** 写操作 HTTP methods */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** 不审计的路径前缀 */
    private const EXCLUDED_PATHS = [
        'api/auth/login',
        'api/settings',
        'api/health',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $method = $request->getMethod();
        $path = $request->path();
        $shouldAudit = $this->shouldAudit($request);

        $response = null;
        $exception = null;
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            // 业务中间件(尤其是 auth:sanctum)失败时, pipeline 可能 throw 异常
            // 此时仍然要审计 — 记 response_code=401/403/500
            $exception = $e;
        }

        // 即使 throw 出来 (auth:sanctum 失败), 也要审计
        if (! $shouldAudit) {
            if ($exception) {
                throw $exception;
            }
            return $response;
        }

        try {
            // 如果有异常,模拟 401/403/500 响应
            if ($exception) {
                $code = 500;
                if ($exception instanceof \Illuminate\Auth\AuthenticationException) $code = 401;
                elseif ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) $code = 403;
                $response = response()->json(['code' => $code, 'message' => $exception->getMessage()], $code);
            }
            $this->writeLog($request, $response, $path);
        } catch (\Throwable $e) {
            // 审计失败不能影响业务 — 仅记录到 laravel.log
            Log::warning('AuditLogger failed', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
        }

        if ($exception) {
            throw $exception;
        }
        return $response;
    }

    private function shouldAudit(Request $request): bool
    {
        if (! in_array($request->getMethod(), self::WRITE_METHODS, true)) {
            return false;
        }

        $path = ltrim($request->path(), '/');
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if ($path === $excluded || str_starts_with($path . '/', $excluded . '/')) {
                return false;
            }
        }

        return true;
    }

    private function writeLog(Request $request, Response $response, string $path): void
    {
        $user = Auth::user();
        $userId = $user?->id;

        $payload = SensitiveDataRedactor::redact($request->all());

        DB::table('audit_logs')->insert([
            'user_id'       => $userId,
            'method'        => $request->getMethod(),
            'path'          => '/' . ltrim($path, '/'),
            'ip'            => $request->ip(),
            'user_agent'    => substr((string) $request->userAgent(), 0, 500),
            'payload'       => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'response_code' => $response->getStatusCode(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
