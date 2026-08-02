<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivables', function (Blueprint $table) {
            $table->string('source', 20)->default('business')->after('status');
        });

        Schema::table('payables', function (Blueprint $table) {
            $table->string('source', 20)->default('business')->after('status');
        });

        // 写入锁标记: 默认未锁定
        $exists = DB::table('system_settings')->where('key', 'opening_balances_locked')->exists();
        if (!$exists) {
            DB::table('system_settings')->insert([
                'key'         => 'opening_balances_locked',
                'value'       => 'false',
                'description' => '期初数据锁定状态 | true=已锁定, false=未锁定',
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('receivables', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::table('payables', fn (Blueprint $t) => $t->dropColumn('source'));
        DB::table('system_settings')->where('key', 'opening_balances_locked')->delete();
    }
};
