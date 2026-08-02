<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：列已存在则跳过
        if (Schema::hasColumn('customers', 'pipeline_stage')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            // 销售漏斗阶段: lead/contacted/quoted/negotiating/won/lost
            $table->string('pipeline_stage', 20)->default('lead')->after('status')->comment('销售漏斗阶段');
            // 预计成交金额
            $table->decimal('expected_amount', 12, 2)->default(0)->after('pipeline_stage')->comment('预计金额');
            // 预计成交日期
            $table->date('expected_close_date')->nullable()->after('expected_amount')->comment('预计成交日期');
            // 最后活动时间（拖拽/跟进/联系都更新）
            $table->timestamp('last_activity_at')->nullable()->after('expected_close_date')->comment('最后活动时间');

            $table->index('pipeline_stage', 'idx_customers_pipeline_stage');
            $table->index('expected_close_date', 'idx_customers_expected_close_date');
            $table->index('last_activity_at', 'idx_customers_last_activity_at');
        });
    }

    public function down(): void
    {
        // v0.3.18 幂等保护：列不存在则跳过 rollback
        if (!Schema::hasColumn('customers', 'pipeline_stage')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_pipeline_stage');
            $table->dropIndex('idx_customers_expected_close_date');
            $table->dropIndex('idx_customers_last_activity_at');
            $table->dropColumn(['pipeline_stage', 'expected_amount', 'expected_close_date', 'last_activity_at']);
        });
    }
};
