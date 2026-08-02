<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockRecord extends Model
{
    use HasFactory;

    protected $fillable = ['record_no', 'inventory_item_id', 'warehouse_id', 'source_warehouse_id', 'target_warehouse_id', 'is_transfer', 'type', 'quantity', 'remaining_stock', 'related_id', 'related_type', 'party_type', 'party_id', 'settle_id', 'project_id', 'out_method', 'logistics_company', 'logistics_no', 'parent_request_id', 'order_no', 'operator_id', 'remark', 'unit_cost', 'total_amount', 'payment_method', 'account_id'];

    protected $casts = ['quantity' => 'integer', 'remaining_stock' => 'integer', 'is_transfer' => 'boolean'];

    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function sourceWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'source_warehouse_id'); }
    public function targetWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'target_warehouse_id'); }
    public function operator(): BelongsTo { return $this->belongsTo(User::class, 'operator_id'); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    /**
     * 动态往来单位关联: customer (Customer 模型) / supplier (Supplier 模型)
     * 通过 party_type + party_id 解析
     */
    public function party()
    {
        return $this->party_type === 'customer'
            ? $this->belongsTo(Customer::class, 'party_id')
            : $this->belongsTo(Supplier::class, 'party_id');
    }
}
