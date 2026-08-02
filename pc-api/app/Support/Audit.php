<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * V0.5.2 - 写 audit log 到 system_logs 表
 *
 * 用法:
 *   \App\Support\Audit::write('role_changed', 'admin 改了 x 角色', ['old'=>..., 'new'=>...]);
 *
 * 表结构 (V0.4.10 已知):
 *   system_logs (id, action, description, created_at, user_id, ip)
 *   - 没有 username/record_id/reason 列, 全塞 description
 *   - description 上限 500 字符, 超出截断
 */
class Audit
{
    public static function write(string $action, string $description, array $context = []): ?int
    {
        try {
            $user = auth()->user();
            $ip   = request()?->ip() ?? '0.0.0.0';

            // 截断 description
            if (mb_strlen($description) > 480) {
                $description = mb_substr($description, 0, 477) . '...';
            }

            return DB::table('system_logs')->insertGetId([
                'action'      => $action,
                'description' => $description,
                'user_id'     => $user?->id,
                'ip'          => $ip,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // 不让 audit 写失败影响业务
            try {
                \Illuminate\Support\Facades\Log::warning('Audit::write 失败: ' . $e->getMessage(), [
                    'action' => $action,
                    'context' => $context,
                ]);
            } catch (\Throwable $e2) {
                \Log::error(__METHOD__ . ': catch', ['msg' => $e2->getMessage(), 'file' => $e2->getFile() . ':' . $e2->getLine()]);
                // Log facade 也没初始化 (unit test), 完全静默
            }
            return null;
        }
    }
}
