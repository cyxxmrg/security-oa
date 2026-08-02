<?php

namespace App\Console\Commands;

use App\Models\ExpenseClaim;
use App\Models\StockRecord;
use App\Services\ProjectBudgetService;
use Illuminate\Console\Command;

class SyncProjectActualCosts extends Command
{
    protected $signature = 'project:sync-actual-costs {--days=1}';

    protected $description = 'T+1 兜底同步项目实际成本';

    public function handle(ProjectBudgetService $service): int
    {
        $days = (int) $this->option('days');
        $since = now()->subDays($days);
        $count = 0;

        // 1. 采购入库
        StockRecord::where('type', 'in')
            ->where('created_at', '>=', $since)
            ->whereNotNull('project_id')
            ->chunk(100, function ($records) use ($service, &$count) {
                foreach ($records as $r) {
                    $service->recordActualCost(
                        projectId: $r->project_id,
                        sourceType: 'purchase_in',
                        sourceId: $r->id,
                        category: 'material',
                        amount: (float) $r->total_amount,
                        costDate: $r->created_at->toDateString(),
                        description: "采购入库: {$r->item_name} x {$r->quantity}",
                        metadata: ['item_id' => $r->item_id, 'quantity' => $r->quantity],
                    );
                    $count++;
                }
            });

        // 2. 领料出库
        StockRecord::where('type', 'out')
            ->where('created_at', '>=', $since)
            ->whereNotNull('project_id')
            ->chunk(100, function ($records) use ($service, &$count) {
                foreach ($records as $r) {
                    $service->recordActualCost(
                        projectId: $r->project_id,
                        sourceType: 'stock_out',
                        sourceId: $r->id,
                        category: 'material',
                        amount: (float) $r->total_amount,
                        costDate: $r->created_at->toDateString(),
                        description: "领料出库: {$r->item_name} x {$r->quantity}",
                        metadata: ['item_id' => $r->item_id, 'quantity' => $r->quantity],
                    );
                    $count++;
                }
            });

        // 3. 报销审批
        ExpenseClaim::where('status', 'approved')
            ->where('approved_at', '>=', $since)
            ->whereNotNull('project_id')
            ->chunk(100, function ($records) use ($service, &$count) {
                foreach ($records as $c) {
                    $category = match (true) {
                        str_contains($c->category, '人工') || str_contains($c->category, '工资') => 'labor',
                        str_contains($c->category, '外包') || str_contains($c->category, '施工') => 'outsource',
                        default => 'other',
                    };
                    $service->recordActualCost(
                        projectId: $c->project_id,
                        sourceType: 'expense',
                        sourceId: $c->id,
                        category: $category,
                        amount: (float) $c->total_amount,
                        costDate: $c->approved_at?->toDateString() ?? now()->toDateString(),
                        description: "报销: {$c->claim_no}",
                        metadata: ['claim_no' => $c->claim_no, 'category' => $c->category],
                    );
                    $count++;
                }
            });

        $this->info("✓ V0.4.1 同步完成，处理 {$count} 条记录");

        return 0;
    }
}
