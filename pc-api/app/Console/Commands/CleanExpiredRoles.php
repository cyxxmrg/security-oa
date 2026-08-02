<?php

namespace App\Console\Commands;

use App\Support\TemporaryRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Models\User;

/**
 * V0.5.3 临时权限 — 每日清理过期角色
 *
 * 行为:
 *   1) 删 model_has_roles.expires_at < now() 的所有记录
 *   2) 扫描 7 天内即将过期的，通知该用户 + 该用户所属 admins
 *   3) 输出统计
 *
 * 用法:
 *   php artisan oa:clean-expired-roles
 *   php artisan oa:clean-expired-roles --dry-run
 *   php artisan oa:clean-expired-roles --no-notify
 */
class CleanExpiredRoles extends Command
{
    protected $signature = 'oa:clean-expired-roles
                            {--dry-run : 仅统计不删}
                            {--no-notify : 不发送到期提醒}';

    protected $description = 'V0.5.3 - 清理过期角色 + 提醒即将过期用户';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $sendNotify = !$this->option('no-notify');

        // 1) 清理过期
        if ($isDryRun) {
            $toDelete = \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->count();
            $this->info("[DRY-RUN] 将清理 {$toDelete} 条过期角色");
        } else {
            $count = TemporaryRole::cleanExpired();
            $this->info("清理过期角色: {$count} 条");
            Log::info("oa:clean-expired-roles 删除 {$count} 条过期角色");
        }

        // 2) 提醒即将过期
        $expiring = TemporaryRole::expiringSoon(7);
        $this->info("即将过期 (7 天内): " . count($expiring) . " 条");

        if ($sendNotify && !$isDryRun && count($expiring) > 0) {
            foreach ($expiring as $row) {
                $days = (int) now()->diffInDays(\Carbon\Carbon::parse($row['expires_at']), false);
                $msg = sprintf(
                    '您的「%s」角色将于 %s 后过期（%s），如需续期请联系管理员',
                    $row['role_name'],
                    $days . ' 天',
                    \Carbon\Carbon::parse($row['expires_at'])->toDateString()
                );
                // 写站内通知（不依赖 email）— 用 DatabaseNotification.create
                try {
                    \Illuminate\Support\Facades\DB::table('notifications')->insert([
                        'id'              => (string) \Illuminate\Support\Str::uuid(),
                        'type'            => 'App\Notifications\TemporaryRoleExpiring',
                        'notifiable_type' => User::class,
                        'notifiable_id'   => $row['user_id'],
                        'data'            => json_encode([
                            'title'    => '角色即将过期',
                            'content'  => $msg,
                            'role'     => $row['role_name'],
                            'expires_at' => $row['expires_at'],
                        ]),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('通知写入失败: ' . $e->getMessage());
                }
            }
            $this->info("已给 " . count($expiring) . " 个用户写站内通知");
        }

        return self::SUCCESS;
    }
}
