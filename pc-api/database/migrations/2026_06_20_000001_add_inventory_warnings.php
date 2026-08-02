<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：列已存在则跳过整个 migration
        if (Schema::hasColumn('inventory_items', 'min_stock')
            && Schema::hasColumn('inventory_items', 'shelf_life_days')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            // 最低库存阈值（<=min_stock 视为低库存预警；0 表示不预警）
            $table->unsignedInteger('min_stock')->default(0)->after('safety_stock')->comment('最低库存阈值（低库存预警）');
            // 保质期天数（NULL 表示无保质期；用于临期预警 expiry_date = updated_at + shelf_life_days）
            $table->unsignedInteger('shelf_life_days')->nullable()->after('min_stock')->comment('保质期天数（NULL=无保质期）');
            // expiry_date 字段在生产表上可能不存在，安全创建
            if (!Schema::hasColumn('inventory_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('shelf_life_days')->comment('到期日期（用于临期预警）');
            }
            $table->index('min_stock');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        // v0.3.18 幂等保护：列不存在则跳过
        if (!Schema::hasColumn('inventory_items', 'min_stock')
            || !Schema::hasColumn('inventory_items', 'shelf_life_days')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'expiry_date')) {
                $table->dropIndex(['expiry_date']);
                $table->dropColumn('expiry_date');
            }
            $table->dropIndex(['min_stock']);
            $table->dropColumn(['min_stock', 'shelf_life_days']);
        });
    }
};
