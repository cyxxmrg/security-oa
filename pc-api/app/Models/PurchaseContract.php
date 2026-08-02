<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueCode;
use App\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseContract extends Model
{
    use HasFactory, HasDataScope, GeneratesUniqueCode;

    protected $fillable = [
        'code', 'plan_id', 'project_id', 'supplier_id', 'title', 'total_amount',
        'signed_at', 'start_date', 'end_date', 'payment_terms',
        'delivery_address', 'status', 'signer', 'signer_id', 'remark',
        'purchase_order_id', 'payment_plan',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'signed_at'    => 'date',
        'start_date'   => 'date',
        'end_date'     => 'date',
        'payment_plan' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (empty($m->code)) {
                $m->code = self::uniqueCode('PC');
            }
        });
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function signer(): BelongsTo { return $this->belongsTo(User::class, 'signer_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(PurchasePlan::class, 'plan_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function shipments(): HasMany { return $this->hasMany(PurchaseShipment::class, 'contract_id'); }
    public function paymentRequests(): HasMany { return $this->hasMany(PurchasePaymentRequest::class, 'contract_id'); }
    public function payments(): HasMany { return $this->hasMany(PurchasePayment::class, 'contract_id'); }
    public function files(): HasMany { return $this->hasMany(PurchaseContractFile::class, 'contract_id'); }
    public function itemsList(): HasMany { return $this->hasMany(PurchaseContractItem::class, 'contract_id'); }
    public function shippingPlans(): HasMany { return $this->hasMany(PurchaseShippingPlan::class, 'contract_id'); }
}
