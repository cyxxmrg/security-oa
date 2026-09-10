<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * V1.4.5 (REVIEW P0-2 修复): 备份 cron token 生成/查看/轮换
 *
 * 之前 system_settings.backup_cron_token 无任何写入方, 公开端点 POST /api/backups/run-due
 * 永远 403。本命令提供写入方, 供运维在 crontab 中配置:
 *
 *   php artisan oa:backup-token                 # 查看当前 token(未配置则生成)
 *   php artisan oa:backup-token --rotate        # 重新生成 token
 *
 * crontab 示例: 0 2 * * * curl -s -X POST 'https://oa.example.com/api/backups/run-due?token=<token>'
 */
class BackupCronToken extends Command
{
    protected $signature = 'oa:backup-token {--rotate : 重新生成 token}';

    protected $description = '查看或生成自动备份 cron 触发 token (存 system_settings.backup_cron_token)';

    public function handle(): int
    {
        $raw = DB::table('system_settings')->where('key', 'backup_cron_token')->value('value');
        $decoded = json_decode((string) $raw, true);
        $existing = is_string($decoded) ? $decoded : (is_scalar($raw) ? (string) $raw : '');

        if ($existing !== '' && !$this->option('rotate')) {
            $this->info('当前备份 cron token: ' . $existing);
            $this->line('触发示例: curl -X POST ' . url('/api/backups/run-due') . '?token=' . $existing);
            $this->warn('请勿泄露; 泄露时执行 php artisan oa:backup-token --rotate 重新生成。');
            return self::SUCCESS;
        }

        $token = bin2hex(random_bytes(24)); // 48 hex chars
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'backup_cron_token'],
            [
                'value'       => json_encode($token, JSON_UNESCAPED_UNICODE),
                'description' => '自动备份 cron 触发 token (X-Backup-Cron-Token 头或 ?token=)',
                'updated_at'  => now(),
            ]
        );

        $verb = $existing !== '' ? '重新生成' : '生成';
        $this->info("备份 cron token 已{$verb}: {$token}");
        $this->line('触发示例: curl -X POST ' . url('/api/backups/run-due') . '?token=' . $token);
        return self::SUCCESS;
    }
}
