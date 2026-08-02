<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderBidItem extends Model
{
    protected $table = 'tender_bid_items';
    protected $fillable = [
        'tender_bid_id', 'name', 'spec', 'unit',
        'quantity', 'unit_price', 'total_price', 'remark',
    ];

    protected $casts = [
        'quantity'    => 'decimal:2',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function bid(): BelongsTo { return $this->belongsTo(TenderBid::class, 'tender_bid_id'); }
}
