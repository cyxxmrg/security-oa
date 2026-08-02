<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;
    protected $table = 'quotation_items';
    protected $fillable = ['quotation_id', 'inventory_item_id', 'product_id', 'code', 'name', 'specification', 'unit', 'quantity', 'unit_price', 'total_price', 'remark'];
    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total_price' => 'decimal:2'];

    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); }
    public function product(): BelongsTo { return $this->belongsTo(SalesProduct::class, 'product_id'); }
}
