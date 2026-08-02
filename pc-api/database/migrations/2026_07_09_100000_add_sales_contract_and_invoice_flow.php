<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. project_contracts 加 customer_id + quotation_id (销售合同关联客户+报价单)
        Schema::table('project_contracts', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('project_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->after('customer_id')->constrained('quotations')->nullOnDelete();
        });

        // 2. finance_invoices 加 contract_id (发票关联合同)
        Schema::table('finance_invoices', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->after('receivable_id')->constrained('project_contracts')->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->after('contract_id')->constrained('users')->nullOnDelete();
            $table->date('delivery_date')->nullable()->after('issue_date');
        });

        // 3. finance_invoices status CHECK 约束加: requested / pending_approval / delivered
        DB::statement("ALTER TABLE finance_invoices DROP CONSTRAINT IF EXISTS finance_invoices_status_check");
        DB::statement("ALTER TABLE finance_invoices ADD CONSTRAINT finance_invoices_status_check CHECK (status::text = ANY (ARRAY['draft'::character varying::text, 'requested'::character varying::text, 'pending_approval'::character varying::text, 'issued'::character varying::text, 'delivered'::character varying::text, 'cancelled'::character varying::text]))");
    }

    public function down(): void
    {
        Schema::table('finance_invoices', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropForeign(['applicant_id']);
            $table->dropColumn(['contract_id', 'applicant_id', 'delivery_date']);
        });
        DB::statement("ALTER TABLE finance_invoices DROP CONSTRAINT IF EXISTS finance_invoices_status_check");
        DB::statement("ALTER TABLE finance_invoices ADD CONSTRAINT finance_invoices_status_check CHECK (status::text = ANY (ARRAY['draft'::character varying::text, 'issued'::character varying::text, 'cancelled'::character varying::text]))");
        Schema::table('project_contracts', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['quotation_id']);
            $table->dropColumn(['customer_id', 'quotation_id']);
        });
    }
};
