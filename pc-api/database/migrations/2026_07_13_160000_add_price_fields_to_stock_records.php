<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_records', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('quantity')->comment('单价');
            $table->decimal('total_amount', 14, 2)->nullable()->after('unit_cost')->comment('金额');
            $table->string('payment_method', 20)->nullable()->after('total_amount')->comment('付款方式: cash/credit');
        });
    }

    public function down(): void
    {
        Schema::table('stock_records', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_amount', 'payment_method']);
        });
    }
};
