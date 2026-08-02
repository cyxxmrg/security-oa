<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2.5 商机推荐人 (E2E 阶段4 发现的 BUG 修复)
 *
 * 问题: oppsMarkWon 只在 lead.referrer_id 非空时建 ReferralSettlement,
 *       但销售可以直接"新建商机"绕过线索 → 佣金永远建不出来
 * 修法: opportunities 加 referrer_id (nullable, FK to referrers),
 *       oppsMarkWon 同时看 lead.referrer_id 和 opp.referrer_id
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('opportunities')) {
            return;
        }
        if (!Schema::hasColumn('opportunities', 'referrer_id')) {
            Schema::table('opportunities', function (Blueprint $t) {
                $t->unsignedBigInteger('referrer_id')->nullable()->after('lead_id');
                $t->index('referrer_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('opportunities') && Schema::hasColumn('opportunities', 'referrer_id')) {
            Schema::table('opportunities', function (Blueprint $t) {
                $t->dropIndex(['referrer_id']);
                $t->dropColumn('referrer_id');
            });
        }
    }
};
