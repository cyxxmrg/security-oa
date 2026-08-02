<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseShipment extends Model
{
    use HasFactory, GeneratesUniqueCode;

    protected $fillable = [
        'code', 'contract_id', 'supplier_id', 'shipped_at', 'expected_arrival_at',
        'arrived_at', 'carrier', 'tracking_no', 'status', 'consignee', 'remark',
        'stock_record_id', 'inbound_confirmed', 'inbound_confirmed_by', 'inbound_confirmed_at',
    ];

    protected $casts = [
        'shipped_at'           => 'date',
        'expected_arrival_at'  => 'date',
        'arrived_at'           => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (empty($m->code)) {
                $m->code = self::uniqueCode('SH');
            }
        });
    }

    public function contract(): BelongsTo { return $this->belongsTo(PurchaseContract::class, 'contract_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseShipmentItem::class, 'shipment_id'); }
    public function logistics(): HasMany { return $this->hasMany(PurchaseLogistics::class, 'shipment_id'); }
}
