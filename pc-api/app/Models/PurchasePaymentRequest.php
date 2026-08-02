<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePaymentRequest extends Model
{
    use HasFactory, GeneratesUniqueCode;

    protected $fillable = [
        'code', 'contract_id', 'supplier_id', 'amount', 'payment_type', 'request_date',
        'status', 'applicant', 'applicant_id', 'reason',
        'approver_id', 'approved_at', 'approve_remark',
        'payable_id', 'stage_label', 'invoice_received', 'invoice_received_at', 'invoice_no',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'request_date'=> 'date',
        'approved_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (empty($m->code)) {
                $m->code = self::uniqueCode('PR');
            }
        });
    }

    public function contract(): BelongsTo { return $this->belongsTo(PurchaseContract::class, 'contract_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function applicant(): BelongsTo { return $this->belongsTo(User::class, 'applicant_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
    public function payments(): HasMany { return $this->hasMany(PurchasePayment::class, 'payment_request_id'); }
}
