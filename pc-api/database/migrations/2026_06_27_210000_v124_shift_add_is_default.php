<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2.4v 考勤 — 班次增加 is_default 标志 + 系统默认 "正常班"
 *
 *  - is_default = true: 系统内置班次, 不允许删除, 但可手动修改
 *  - 当 is_default 列不存在时, 自动加上, 并把 code='day' (或 name='正常班') 的班次标记为默认
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('shifts', 'is_default')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('is_active')
                    ->comment('是否系统默认班次(不可删, 可改)');
                $table->index('is_default');
            });

            // 把当前已存在的"正常班" (code='day') 标记为默认
            \DB::table('shifts')->where('code', 'day')->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shifts', 'is_default')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->dropIndex(['is_default']);
                $table->dropColumn('is_default');
            });
        }
    }
};
