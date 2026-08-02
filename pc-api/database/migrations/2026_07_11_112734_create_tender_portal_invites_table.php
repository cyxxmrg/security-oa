<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P1-7 修复: 供应商门户邀请 token 表
 *
 * - 一次性 token + 30 分钟 TTL + 用完即焚 (used_at)
 * - 存 phone_suffix_hash (不存明文 suffix)
 * - 记录签发 IP/UA, 便于审计追溯
 *
 * 设计要点:
 *   1. supplier_id + 1次验证 → token; 后续 supplier 用 token + 任意 supplier_id 组合都会校验 token 自身绑定
 *   2. used_at 标记后, token 永久失效 (即使未过期)
 *   3. expires_at 默认 30 分钟
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tender_portal_invites')) {
            return;
        }

        Schema::create('tender_portal_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('token', 96)->unique()->comment('一次性邀请 token');
            $table->string('phone_suffix_hash', 128)->comment('手机号后 4 位 + salt 的哈希');
            $table->string('ip', 64)->nullable()->comment('签发 IP');
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('expires_at')->comment('30 分钟过期');
            $table->timestamp('used_at')->nullable()->comment('用完即焚标记');
            $table->timestamps();
            $table->index(['supplier_id', 'expires_at']);
            $table->index('expires_at');
        });

        DB::statement("GRANT ALL PRIVILEGES ON TABLE tender_portal_invites TO oa_user");
        DB::statement("GRANT USAGE, SELECT ON SEQUENCE tender_portal_invites_id_seq TO oa_user");
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_portal_invites');
    }
};