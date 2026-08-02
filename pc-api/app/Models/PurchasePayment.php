<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePayment extends Model
{
    use HasFactory, GeneratesUniqueCode;

    protected $fillable = [
        'code', 'payment_request_id', 'contract_id', 'supplier_id', 'amount',
        'payment_method', 'paid_at', 'voucher_no', 'operator', 'operator_id', 'status', 'remark',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (empty($m->code)) {
                $m->code = self::uniqueCode('PAY');
            }
        });
    }

    public function paymentRequest(): BelongsTo { return $this->belongsTo(PurchasePaymentRequest::class, 'payment_request_id'); }
    public function contract(): BelongsTo { return $this->belongsTo(PurchaseContract::class, 'contract_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function operatorUser(): BelongsTo { return $this->belongsTo(User::class, 'operator_id'); }
}
