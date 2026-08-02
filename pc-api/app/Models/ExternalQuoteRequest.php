<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalQuoteRequest extends Model
{
    protected $table = 'external_quote_requests';

    protected $fillable = [
        'project_id', 'code', 'title',
        'required_items', 'required_files', 'deadline',
        'status', 'public_token',
        'awarded_supplier_id', 'awarded_quote_id',
        'created_by', 'description',
    ];

    protected $casts = [
        'required_items' => 'array',
        'required_files' => 'array',
        'deadline'       => 'datetime',
    ];

    public const STATUS_OPEN     = 'open';
    public const STATUS_CLOSED   = 'closed';
    public const STATUS_AWARDED  = 'awarded';
    public const STATUS_CANCELLED = 'cancelled';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function awardedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'awarded_supplier_id');
    }

    public function awardedQuote(): BelongsTo
    {
        return $this->belongsTo(ExternalQuote::class, 'awarded_quote_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(ExternalQuote::class, 'request_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN      => '征集中',
            self::STATUS_CLOSED    => '已截止',
            self::STATUS_AWARDED   => '已定标',
            self::STATUS_CANCELLED => '已取消',
            default                => $this->status,
        };
    }
}
