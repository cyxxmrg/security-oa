<?php

/**
 * V1.2.16: 给 finance_payments 加 supplier_id 字段
 * 用于付款单关联供应商
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('finance_payments', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('project_id');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_payments', function (Blueprint $table) {
            if (Schema::hasColumn('finance_payments', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            }
        });
    }
};