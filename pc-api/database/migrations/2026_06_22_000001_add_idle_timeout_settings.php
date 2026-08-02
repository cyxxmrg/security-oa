<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 闲置超时相关设置(挂在现有 system_settings 表上,key 区分)
        DB::table('system_settings')->insertOrIgnore([
            [
                'key'         => 'idle_enabled',
                'value'       => json_encode(true, JSON_UNESCAPED_UNICODE),
                'description' => '是否启用闲置自动登出',
                'updated_at'  => now(),
            ],
            [
                'key'         => 'idle_timeout_minutes',
                'value'       => json_encode(30, JSON_UNESCAPED_UNICODE),
                'description' => '无操作超时时间(分钟)',
                'updated_at'  => now(),
            ],
            [
                'key'         => 'idle_warning_seconds',
                'value'       => json_encode(60, JSON_UNESCAPED_UNICODE),
                'description' => '登出前提前弹窗提示秒数',
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'idle_enabled', 'idle_timeout_minutes', 'idle_warning_seconds',
        ])->delete();
    }
};
