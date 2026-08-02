<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderBid extends Model
{
    protected $table = 'tender_bids';
    protected $fillable = [
        'tender_project_id', 'supplier_id', 'code',
        'total_amount', 'lead_time_days', 'technical_proposal', 'remark',
        'status', 'submitted_at', 'scores', 'total_score', 'submitter_user_id',
    ];

    protected $casts = [
        'total_amount'  => 'decimal:2',
        'total_score'   => 'decimal:2',
        'scores'        => 'array',
        'submitted_at'  => 'datetime',
    ];

    public function project(): BelongsTo { return $this->belongsTo(TenderProject::class, 'tender_project_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(TenderBidItem::class, 'tender_bid_id'); }
    public function attachments(): HasMany { return $this->hasMany(TenderAttachment::class, 'tender_bid_id'); }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'       => '草稿',
            'submitted'   => '已投标',
            'shortlisted' => '已入围',
            'awarded'     => '已中标',
            'rejected'    => '未中标',
            'withdrawn'   => '已撤回',
            default       => $this->status,
        };
    }
}
