<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.4 补丁: 给 rectification_daily_required 加 commencement_order_id + overdue_notified_at
 *
 * 旧 V0.4.3 migration 漏了这两字段
 * - commencement_order_id 关联 project_commencement_orders (开工单)
 * - 实际表已有 overdue_notified_at, 仅补 commencement_order_id
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rectification_daily_required')) {
            return;
        }
        if (Schema::hasColumn('rectification_daily_required', 'commencement_order_id')) {
            return;
        }

        Schema::table('rectification_daily_required', function (Blueprint $table) {
            $table->unsignedBigInteger('commencement_order_id')->nullable()->after('project_id')
                ->comment('关联开工单ID');
            $table->foreign('commencement_order_id')->references('id')->on('project_commencement_orders')->onDelete('cascade');
            $table->index('commencement_order_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('rectification_daily_required', 'commencement_order_id')) {
            Schema::table('rectification_daily_required', function (Blueprint $table) {
                $table->dropForeign(['commencement_order_id']);
                $table->dropColumn('commencement_order_id');
            });
        }
    }
};
