<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * V0.6.2.2 合同清单 (行项目)
 * 合同创建时从 PO.line_items 自动同步, 单价允许编辑, 小计 = qty * unit_price 自动算
 */
class PurchaseContractItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_contract_items';

    protected $fillable = [
        'contract_id', 'inventory_item_id', 'material', 'spec', 'qty', 'unit',
        'unit_price', 'subtotal', 'remark',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(PurchaseContract::class, 'contract_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function shippingPlans(): HasMany
    {
        return $this->hasMany(PurchaseShippingPlan::class, 'contract_item_id');
    }
}
