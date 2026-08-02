<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposal extends Model
{
    protected $fillable = ['asset_id', 'date', 'method', 'amount', 'reason', 'handler_id', 'remark'];

    protected $casts = ['asset_id' => 'integer', 'amount' => 'float', 'handler_id' => 'integer'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }
}
