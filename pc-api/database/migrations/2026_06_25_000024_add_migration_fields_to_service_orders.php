<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.5.8 修复: 幂等 (避免重跑时 42701)
        if (!Schema::hasColumn('service_orders', 'migrated_to_work_order_id')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('migrated_to_work_order_id')->nullable()->after('id');
                $table->timestamp('migrated_at')->nullable()->after('migrated_to_work_order_id');
                $table->index('migrated_to_work_order_id');
            });
        }

        // work_orders 加迁移来源字段 (idempotent)
        if (Schema::hasTable('work_orders') && !Schema::hasColumn('work_orders', 'migrated_from_service_order_id')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('migrated_from_service_order_id')->nullable()->after('id');
                $table->index('migrated_from_service_order_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn(['migrated_to_work_order_id', 'migrated_at']);
        });
        if (Schema::hasColumn('work_orders', 'migrated_from_service_order_id')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->dropColumn('migrated_from_service_order_id');
            });
        }
    }
};
