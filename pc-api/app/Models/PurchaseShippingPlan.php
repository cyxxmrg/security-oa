<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.6.2.2 发货计划 + 快递单号 (合表)
 * - contract_item_id 为空 → 整单发货
 * - contract_item_id 不空 → 该物料单独发 (同一合同可分批发不同物料)
 */
class PurchaseShippingPlan extends Model
{
    use HasFactory;

    protected $table = 'purchase_shipping_plans';

    protected $fillable = [
        'contract_id', 'contract_item_id', 'expected_at',
        'carrier', 'tracking_no', 'shipped_at', 'status', 'remark',
    ];

    protected $casts = [
        'expected_at' => 'date',
        'shipped_at' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(PurchaseContract::class, 'contract_id');
    }

    public function contractItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseContractItem::class, 'contract_item_id');
    }
}
