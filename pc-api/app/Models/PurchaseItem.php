<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_order_id', 'item_name', 'specification', 'quantity', 'unit', 'unit_price', 'total_price', 'received_quantity', 'notes'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'total_price' => 'decimal:2', 'received_quantity' => 'decimal:2'];

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
}
