<?php

namespace App\Models;

use App\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseShipmentItem extends Model
{
    use HasFactory, HasDataScope;

    protected $fillable = ['shipment_id', 'material', 'spec', 'quantity', 'unit', 'remark'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function shipment(): BelongsTo { return $this->belongsTo(PurchaseShipment::class, 'shipment_id'); }
}
