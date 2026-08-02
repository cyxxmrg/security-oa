<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v0.5.8 商机看板 BUG 修复
 *
 * 问题：DB opportunities.stage 字段是 string(20) 注释为 6 段 (requirement/solution/negotiation/contracting/won/lost)
 *      后端 SalesController::oppsUpdateStage 的 oppStageMap 把 proposal/negotiating 都硬归一到 negotiation，
 *      导致商机看板「方案报价」「报价谈判」两列永远空。
 * 修法：业务上把 opportunities.stage 升级为 7 段真值（兼容老数据）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('opportunities')) {
            return;
        }
        DB::statement("COMMENT ON COLUMN opportunities.stage IS '状态: inquiry/qualification/proposal/negotiating/quoted/requirement/solution/contracting/won/lost (v0.5.8 7 段独立)'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('opportunities')) {
            return;
        }
        DB::statement("COMMENT ON COLUMN opportunities.stage IS '需求确认/方案制定/报价谈判/合同拟定/won/lost'");
    }
};
