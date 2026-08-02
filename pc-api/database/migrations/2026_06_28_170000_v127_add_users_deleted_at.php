<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2.7 P0 fix: User model 用了 SoftDeletes trait 但 users 表没有 deleted_at 列
 *
 * 修复后所有 User::xxx() 查询会自动带 `where deleted_at is null`，
 * 之前一直 500 (column does not exist)。
 *
 * 注意：保留现有 status 字段 (active/inactive) 作为业务软删除主字段，
 * deleted_at 仅供 Eloquent SoftDeletes trait 使用 (兼容 Laravel 默认行为)。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        if (Schema::hasColumn('users', 'deleted_at')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes()->comment('V1.2.7: SoftDeletes trait 兼容字段');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) return;
        if (!Schema::hasColumn('users', 'deleted_at')) return;
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};