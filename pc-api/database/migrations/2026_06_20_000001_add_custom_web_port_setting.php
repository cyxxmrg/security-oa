<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 给 system_settings 表加 custom_web_port 默认值（端口配置）
 * 不改表结构（JSONB 自由 key），只补一行默认数据
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            [
                'key'         => 'custom_web_port',
                'value'       => json_encode(80),
                'description' => '自定义网站访问端口（1-65535，修改后需重启 web 服务）',
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'custom_web_port')->delete();
    }
};
