<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.3.5: 库存转工具支持选择数量
 *
 * tools 表加 quantity 列 — 一种库存商品可只转部分数量为工具台账
 * (例如库存 100 台电钻, 只转 50 台为固定资产工具)。
 *
 * 幂等: hasColumn 守卫
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tools') && !Schema::hasColumn('tools', 'quantity')) {
            Schema::table('tools', function (Blueprint $table) {
                $table->unsignedInteger('quantity')->default(1)->after('unit')->comment('工具件数');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tools') && Schema::hasColumn('tools', 'quantity')) {
            Schema::table('tools', function (Blueprint $table) {
                $table->dropColumn('quantity');
            });
        }
    }
};
