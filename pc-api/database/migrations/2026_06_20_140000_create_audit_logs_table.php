<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 审计日志表 (T4)
     *
     * 监听所有 POST/PUT/PATCH/DELETE 请求 (排除 /api/auth/login, /api/settings, /api/health)
     * 由 App\Http\Middleware\AuditLogger 写入
     */
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作人 user_id, 未登录为 null');
            $table->string('method', 10)->comment('HTTP method: POST/PUT/PATCH/DELETE');
            $table->string('path', 255)->comment('请求路径, 如 /api/employees/123');
            $table->string('ip', 45)->nullable()->comment('客户端 IP, 支持 IPv6');
            $table->string('user_agent', 500)->nullable();
            $table->jsonb('payload')->nullable()->comment('请求体 (敏感字段已脱敏)');
            $table->unsignedSmallInteger('response_code')->comment('HTTP 响应码');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('method');
            $table->index('path');
            $table->index('response_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
