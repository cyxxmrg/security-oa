<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 兼容: 加 tender_id 列 (purchase_orders)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->after('po_no');
            $table->string('title', 200)->nullable();
            $table->unsignedBigInteger('tender_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
        });
        // payables
        Schema::table('payables', function (Blueprint $table) {
            $table->string('ref_no', 50)->nullable()->unique()->after('id');
            $table->unsignedBigInteger('po_id')->nullable()->index();
            $table->unsignedBigInteger('tender_id')->nullable()->index();
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['code', 'title', 'tender_id', 'created_by']);
        });
        Schema::table('payables', function (Blueprint $table) {
            $table->dropColumn(['ref_no', 'po_id', 'tender_id', 'description']);
        });
    }
};
