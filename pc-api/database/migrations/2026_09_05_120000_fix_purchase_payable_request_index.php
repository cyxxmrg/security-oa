<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_payment_requests')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS uniq_payreq_to_payable');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_payreq_payable ON purchase_payment_requests(payable_id)');
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_payment_requests')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_payreq_payable');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uniq_payreq_to_payable ON purchase_payment_requests(payable_id) WHERE payable_id IS NOT NULL');
    }
};
