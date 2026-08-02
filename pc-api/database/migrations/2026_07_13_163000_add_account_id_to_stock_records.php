<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_records', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('payment_method')->comment('付款账户ID');
        });
    }

    public function down(): void
    {
        Schema::table('stock_records', function (Blueprint $table) {
            $table->dropColumn('account_id');
        });
    }
};
