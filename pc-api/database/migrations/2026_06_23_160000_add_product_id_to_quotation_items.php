<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 报价单 items 加 product_id (关联 sales_products) + code (产品编码快照)
 * v0.3.11 块四
 */
return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：列已存在则跳过
        if (Schema::hasColumn('quotation_items', 'product_id')) {
            return;
        }

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('inventory_item_id')->comment('销售产品库 ID');
            $table->string('code', 64)->nullable()->after('product_id')->comment('产品编码快照');
        });
    }

    public function down(): void
    {
        // v0.3.18 幂等保护：列不存在则跳过 rollback
        if (!Schema::hasColumn('quotation_items', 'product_id')) {
            return;
        }

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['product_id', 'code']);
        });
    }
};
