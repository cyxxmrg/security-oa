<?php

namespace App\Observers;

use App\Models\StockRecord;
use App\Services\ProjectBudgetService;

class StockRecordObserver
{
    public function __construct(private ProjectBudgetService $service) {}

    public function created(StockRecord $record): void
    {
        // 只有入库(in)/出库(out) 且有 project_id 的才记录
        if (!in_array($record->type, ['in', 'out'], true) || !$record->project_id) {
            return;
        }

        $sourceType = $record->type === 'in' ? 'purchase_in' : 'stock_out';
        $verb       = $record->type === 'in' ? '采购入库' : '领料出库';

        $this->service->recordActualCost(
            projectId:   (int) $record->project_id,
            sourceType:  $sourceType,
            sourceId:    (int) $record->id,
            category:    'material',
            amount:      (float) $record->total_amount,
            costDate:    $record->created_at->toDateString(),
            description: "{$verb}: {$record->item_name} x {$record->quantity}",
            metadata:    [
                'item_id'    => $record->item_id ?? null,
                'item_name'  => $record->item_name ?? null,
                'quantity'   => $record->quantity,
                'unit_price' => $record->unit_price ?? null,
            ],
        );
    }
}
