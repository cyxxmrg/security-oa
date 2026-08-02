<?php

namespace App\Models;

use App\Enums\ShipmentDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairShipment extends Model
{
    use HasFactory;

    protected $table = 'repair_shipments';

    protected $fillable = [
        'repair_order_id', 'direction',
        'carrier', 'tracking_no', 'cost',
        'shipped_at', 'estimated_arrival', 'actual_arrival', 'delivery_status',
        'sender_name', 'sender_phone', 'sender_address',
        'receiver_name', 'receiver_phone', 'receiver_address',
        'remarks', 'created_by',
    ];

    protected $casts = [
        'shipped_at'         => 'datetime',
        'estimated_arrival'  => 'datetime',
        'actual_arrival'     => 'datetime',
        'direction'          => ShipmentDirection::class,
        'cost'               => 'decimal:2',
    ];

    public function repairOrder() { return $this->belongsTo(RepairOrder::class); }
}
