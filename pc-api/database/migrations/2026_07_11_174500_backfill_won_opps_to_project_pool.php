<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

return new class extends Migration {
    public function up(): void
    {
        // 回填：将所有已成交但还没有项目池记录的商机插入项目池
        $wonOpps = DB::table('opportunities')
            ->where('stage', 'won')
            ->whereNotIn('id', function ($q) {
                $q->select('opportunity_id')->from('project_pool')->whereNotNull('opportunity_id');
            })
            ->get();

        $now = now();
        $inserts = [];
        foreach ($wonOpps as $opp) {
            $poolNo = 'POOL-' . date('YmdHis') . random_int(100, 999);
            $inserts[] = [
                'pool_no'         => $poolNo,
                'opportunity_id'  => $opp->id,
                'name'            => $opp->name ?? '项目',
                'customer_id'     => $opp->customer_id,
                'contract_amount' => $opp->estimated_amount ?? 0,
                'status'          => 'pending',
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        if (!empty($inserts)) {
            DB::table('project_pool')->insert($inserts);
        }

        echo "  ✓ 已为 " . count($inserts) . " 条已成交商机创建项目池记录\n";
    }

    public function down(): void
    {
        // 仅回滚这批插入的记录（移除无 related_project_id 的池记录）
        DB::table('project_pool')
            ->whereNull('related_project_id')
            ->whereNotNull('opportunity_id')
            ->whereIn('opportunity_id', function ($q) {
                $q->select('id')->from('opportunities')->where('stage', 'won');
            })
            ->delete();
    }
};