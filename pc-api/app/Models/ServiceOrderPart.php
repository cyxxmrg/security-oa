<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderPart extends Model
{
    use HasFactory;

    protected $fillable = ['service_order_id', 'inventory_item_id', 'part_name', 'quantity', 'unit_cost', 'total_cost'];

    protected $casts = ['quantity' => 'integer', 'unit_cost' => 'decimal:2', 'total_cost' => 'decimal:2'];

    public function serviceOrder(): BelongsTo { return $this->belongsTo(ServiceOrder::class); }
}
