<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2.4: users.password 改为 nullable
 * 让 wipeAll 后 system 账号可以 password=null (首次登录强制设置)
 * 之前的 NOT NULL 限制导致 wipeAll Phase 3 重建 system 账号失败
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
