<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 旧阶段 → 新阶段映射 (V1.2.12i: 去掉销售阶段, 只保留施工)
        $map = [
            'initiation'   => 'mobilization',
            'inquiry'      => 'mobilization',
            'design'       => 'mobilization',
            'bidding'      => 'mobilization',
            'contract'     => 'mobilization',
            'purchase'     => 'construction',
            'construction' => 'construction',
            'acceptance'   => 'acceptance',
            'settlement'   => 'settlement',
            'warranty'     => 'warranty',
            'closed'       => 'closed',
        ];

        // project_stage_logs 的 stage_key
        foreach ($map as $old => $new) {
            DB::table('project_stage_logs')->where('stage_key', $old)->update(['stage_key' => $new]);
        }

        // projects 的 stage
        foreach ($map as $old => $new) {
            DB::table('projects')->where('stage', $old)->update(['stage' => $new]);
        }

        echo "  ✓ 已迁移项目阶段: 旧阶段归一化到新 6 段\n";
    }

    public function down(): void
    {
        // 不可逆 — 新旧是一对多映射，信息有损
    }
};