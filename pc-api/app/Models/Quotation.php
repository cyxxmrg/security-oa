<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;
    protected $table = 'quotations';
    protected $fillable = [
        'quote_no', 'opportunity_id', 'version', 'subtotal', 'discount_rate', 'discount_amount',
        'tax_rate', 'tax_amount', 'total_amount', 'valid_until', 'status', 'notes',
        'created_by', 'approved_by', 'sent_at', 'responded_at'
    ];
    protected $casts = ['subtotal' => 'decimal:2', 'discount_rate' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_rate' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'valid_until' => 'date', 'sent_at' => 'datetime', 'responded_at' => 'datetime'];

    protected static function booted()
    {
        static::creating(function ($quote) {
            if (empty($quote->quote_no)) {
                $quote->quote_no = 'QT-' . date('YmdHis') . substr((string) microtime(true), -4) . random_int(100, 999);
            }
        });
    }

    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function items(): HasMany { return $this->hasMany(QuotationItem::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
