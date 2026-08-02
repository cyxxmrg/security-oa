<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * V1.2.10: 巡检计划/任务的 contract_id 改 nullable
 * 允许无合同的客户也能单独建立巡检计划
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE inspection_plans ALTER COLUMN contract_id DROP NOT NULL');
        DB::statement('ALTER TABLE inspection_tasks ALTER COLUMN contract_id DROP NOT NULL');
    }
    public function down(): void
    {
        // 不回滚, 因为已有 null 数据
    }
};
