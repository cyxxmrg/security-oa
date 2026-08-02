<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 采购发票支持: direction (sales=销项/purchase=进项) + supplier_id
        Schema::table('finance_invoices', function (Blueprint $table) {
            $table->string('direction')->default('sales')->after('id'); // sales=销售发票, purchase=采购发票
            $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained('suppliers')->nullOnDelete();
        });
        DB::statement("CREATE INDEX IF NOT EXISTS finance_invoices_direction_index ON finance_invoices (direction)");
    }

    public function down(): void
    {
        Schema::table('finance_invoices', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['direction', 'supplier_id']);
        });
    }
};
