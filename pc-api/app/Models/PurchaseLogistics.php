<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLogistics extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'tracking_no', 'event_at', 'location', 'status', 'description', 'operator',
    ];

    protected $casts = ['event_at' => 'datetime'];

    public function shipment(): BelongsTo { return $this->belongsTo(PurchaseShipment::class, 'shipment_id'); }
}
