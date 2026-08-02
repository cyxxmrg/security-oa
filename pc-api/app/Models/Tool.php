<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 工具台账 — V1.3.4
 *
 * 由库存商品"库存转工具"转换而来, 持有固定资产编号 fixed_asset_no。
 * 领用/退还流水落 stock_records (type=tool_checkout/tool_return),
 * 通过 inventory_item_id 关联库存商品做实际库存增减。
 */
class Tool extends Model
{
    protected $table = 'tools';

    protected $fillable = [
        'inventory_item_id',
        'fixed_asset_no',
        'name',
        'code',
        'specification',
        'unit',
        'warehouse_id',
        'status',
        'remark',
        'created_by',
    ];

    protected $casts = [
        'inventory_item_id' => 'integer',
        'warehouse_id'      => 'integer',
        'created_by'        => 'integer',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
