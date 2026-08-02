<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 出入库单扩展字段
 * - party_type: 往来单位类型 (customer/supplier)
 * - party_id:   往来单位 ID
 * - settle_id:  结算单位 ID (默认同 party_id)
 * - project_id: 关联项目 ID
 * - out_method: 出库方式 (领用/调拨/销售/报废/借出/赠送,仅出库有效)
 */
return new class extends Migration
{
    public function up(): void
    {
        // v0.3.17 幂等保护：列已存在则跳过
        if (Schema::hasColumn('stock_records', 'party_type')) {
            return;
        }

        Schema::table('stock_records', function (Blueprint $table) {
            $table->string('party_type', 20)->nullable()->comment('往来单位类型(customer/supplier)')->after('warehouse_id');
            $table->unsignedBigInteger('party_id')->nullable()->comment('往来单位ID')->after('party_type');
            $table->unsignedBigInteger('settle_id')->nullable()->comment('结算单位ID(默认同party_id)')->after('party_id');
            $table->unsignedBigInteger('project_id')->nullable()->comment('关联项目ID')->after('settle_id');
            $table->string('out_method', 20)->nullable()->comment('出库方式(pickup/transfer/sale/scrap/lend/gift),仅出库有效')->after('project_id');

            $table->index('party_type');
            $table->index('party_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('stock_records', 'party_type')) {
            return;
        }

        Schema::table('stock_records', function (Blueprint $table) {
            $table->dropIndex(['party_type']);
            $table->dropIndex(['party_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn(['party_type', 'party_id', 'settle_id', 'project_id', 'out_method']);
        });
    }
};
