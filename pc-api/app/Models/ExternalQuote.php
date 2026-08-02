<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalQuote extends Model
{
    protected $table = 'external_quotes';

    protected $fillable = [
        'request_id', 'supplier_id', 'code',
        'items', 'total_amount', 'valid_until',
        'lead_time_days', 'payment_terms',
        'attachments', 'note',
        'submitted_by', 'submitted_at',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'items'         => 'array',
        'attachments'   => 'array',
        'total_amount'  => 'decimal:2',
        'valid_until'   => 'date',
        'lead_time_days' => 'integer',
        'submitted_at'  => 'datetime',
        'reviewed_at'   => 'datetime',
    ];

    public const STATUS_SUBMITTED   = 'submitted';
    public const STATUS_SHORTLISTED = 'shortlisted';
    public const STATUS_AWARDED     = 'awarded';
    public const STATUS_REJECTED    = 'rejected';

    public function request(): BelongsTo
    {
        return $this->belongsTo(ExternalQuoteRequest::class, 'request_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED   => '已提交',
            self::STATUS_SHORTLISTED => '入围',
            self::STATUS_AWARDED     => '已中标',
            self::STATUS_REJECTED    => '已驳回',
            default                  => $this->status,
        };
    }
}
