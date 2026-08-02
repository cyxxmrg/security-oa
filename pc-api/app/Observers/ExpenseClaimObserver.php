<?php

namespace App\Observers;

use App\Models\ExpenseClaim;
use App\Services\ProjectBudgetService;

class ExpenseClaimObserver
{
    public function __construct(private ProjectBudgetService $service) {}

    public function updated(ExpenseClaim $claim): void
    {
        if (!$claim->wasChanged('status')) {
            return;
        }
        if ($claim->status !== 'approved') {
            return;
        }
        if (!$claim->project_id) {
            return;
        }

        $category = $this->mapCategory((string) ($claim->category ?? ''));

        $this->service->recordActualCost(
            projectId:   (int) $claim->project_id,
            sourceType:  'expense',
            sourceId:    (int) $claim->id,
            category:    $category,
            amount:      (float) $claim->total_amount,
            costDate:    $claim->approved_at?->toDateString() ?? now()->toDateString(),
            description: "报销: {$claim->claim_no} ({$claim->category_label})",
            metadata:    [
                'claim_no'       => $claim->claim_no,
                'category'       => $claim->category,
                'category_label' => $claim->category_label,
            ],
        );
    }

    private function mapCategory(string $cat): string
    {
        return match (true) {
            str_contains($cat, '人工'), str_contains($cat, '工资') => 'labor',
            str_contains($cat, '外包'), str_contains($cat, '施工') => 'outsource',
            default                                                    => 'other',
        };
    }
}
