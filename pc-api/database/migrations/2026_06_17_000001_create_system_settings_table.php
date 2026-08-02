<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('system_settings')) {
            return;
        }

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->jsonb('value')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->unsignedInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });

        // 初始默认数据
        \DB::table('system_settings')->insertOrIgnore([
            ['key' => 'system_name',       'value' => json_encode('安防运维OA办公系统'),                'description' => '系统名称'],
            ['key' => 'system_short_name', 'value' => json_encode('安防OA'),                            'description' => '系统简称'],
            ['key' => 'copyright',         'value' => json_encode('@2026zsk'),                          'description' => '版权信息'],
            ['key' => 'copyright_url',     'value' => json_encode('https://www.example.com'),            'description' => '版权链接'],
            ['key' => 'announcement',      'value' => json_encode(''),                                  'description' => '系统公告'],
            ['key' => 'icp',               'value' => json_encode('粤ICP备2026000000号-1'),              'description' => 'ICP备案号'],
            ['key' => 'contact_email',     'value' => json_encode('admin@example.com'),                 'description' => '联系邮箱'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
