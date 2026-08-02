<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 幂等迁移：确保 Sanctum 的 personal_access_tokens 表存在。
 *
 * 背景：Sanctum 的 personal_access_tokens 迁移默认只通过
 * `php artisan vendor:publish --tag=sanctum-migrations` 发布，
 * 本项目的部署流程从未发布该迁移，导致全新部署后登录时
 * `createToken()` 报 "relation personal_access_tokens does not exist"。
 *
 * 这里直接以项目迁移的形式固化该表结构，并用 hasTable 守卫保证幂等：
 * - 全新部署：表不存在 → 创建
 * - 既有服务器（117/152/202 已手动修复）：表已存在 → 跳过，绝不报错
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
