<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：列已 nullable 则跳过
        // 不能用 hasColumn(列已存在但可能非 nullable)，要查 is_nullable
        $isNullable = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns WHERE table_name = 'payables' AND column_name = 'supplier_id'"
        );
        if ($isNullable && $isNullable->is_nullable === 'YES') {
            return;
        }

        Schema::table('payables', function (Blueprint $table) {
            // 先删外键再改 nullable，最后加回外键
            $table->dropForeign(['supplier_id']);
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // v0.3.18 幂等保护：列已非 nullable 则跳过
        $isNullable = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns WHERE table_name = 'payables' AND column_name = 'supplier_id'"
        );
        if ($isNullable && $isNullable->is_nullable === 'NO') {
            return;
        }

        Schema::table('payables', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
        });
    }
};
