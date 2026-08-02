<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 先用 CASCADE 解除依赖, 保留 opportunities/referral_settlements 的 lead_id 字段作为历史追溯
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS leads CASCADE');
    }

    public function down(): void
    {
        // 不可回滚 — 线索数据已永久删除
        throw new \RuntimeException('leads table has been permanently removed in V1.2.12');
    }
};