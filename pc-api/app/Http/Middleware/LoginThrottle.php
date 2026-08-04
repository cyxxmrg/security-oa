<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * V0.4.9 C2 - 登录失败锁定中间件
 *
 * 策略:
 *  - 5 分钟内连续失败 5 次 → 锁 30 分钟
 *  - 锁定后所有 login 请求直接 429, 不走 password 校验
 *  - 登录成功清零失败计数
 */
class LoginThrottle
{
    const MAX_ATTEMPTS  = 5;
    const DECAY_MINUTES  = 5;   // 失败计数衰减窗口
    const LOCK_MINUTES   = 30;  // 锁定时长

    public function handle(Request $request, Closure $next): Response
    {
        $username = trim((string) $request->input('username', ''));

        $lockKey = "login:lock:{$username}";

        // 已锁定
        if (Cache::has($lockKey)) {
            $ttl = (int) Cache::get($lockKey);
            return response()->json([
                'code'    => 429,
                'message' => "账号已锁定 (剩余 {$ttl} 分钟), 请稍后再试",
            ], 429);
        }

        $response = $next($request);

        // 登录失败 (非 200) → 累加
        $status = $response->getStatusCode();
        if ($status === 401 || $status === 422) {
            $failKey = "login:fail:{$username}";
            $count   = (int) Cache::get($failKey, 0) + 1;
            Cache::put($failKey, $count, now()->addMinutes(self::DECAY_MINUTES));

            if ($count >= self::MAX_ATTEMPTS) {
                // 锁定 30 分钟
                Cache::put($lockKey, self::LOCK_MINUTES, now()->addMinutes(self::LOCK_MINUTES));
                Log::warning('V0.4.9 login lockout', [
                    'username' => $username,
                    'attempts' => $count,
                    'lock_min' => self::LOCK_MINUTES,
                    'ip'       => $request->ip(),
                ]);
                return response()->json([
                    'code'    => 429,
                    'message' => '登录失败次数过多, 账号已锁定 30 分钟',
                ], 429);
            }
        }

        // 登录成功 → 清零
        if ($status === 200) {
            Cache::forget("login:fail:{$username}");
        }

        return $response;
    }
}
