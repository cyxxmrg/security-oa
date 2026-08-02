<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_contract_items')) {
            return;
        }

        Schema::table('purchase_contract_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_contract_items', 'inventory_item_id')) {
                $table->foreignId('inventory_item_id')
                    ->nullable()
                    ->after('contract_id')
                    ->constrained('inventory_items')
                    ->nullOnDelete()
                    ->comment('关联库存物料');
                $table->index('inventory_item_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_contract_items') || !Schema::hasColumn('purchase_contract_items', 'inventory_item_id')) {
            return;
        }

        Schema::table('purchase_contract_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
        });
    }
};
