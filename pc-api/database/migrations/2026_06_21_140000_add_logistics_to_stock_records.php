<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.17 幂等保护：列已存在则跳过
        if (Schema::hasColumn('stock_records', 'logistics_company')) {
            return;
        }

        Schema::table('stock_records', function (Blueprint $table) {
            $table->string('logistics_company', 50)->nullable()->comment('快递公司')->after('out_method');
            $table->string('logistics_no', 100)->nullable()->comment('快递单号')->after('logistics_company');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('stock_records', 'logistics_company')) {
            return;
        }

        Schema::table('stock_records', function (Blueprint $table) {
            $table->dropColumn(['logistics_company', 'logistics_no']);
        });
    }
};
