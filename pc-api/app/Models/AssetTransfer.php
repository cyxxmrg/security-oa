<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransfer extends Model
{
    protected $fillable = ['asset_id', 'date', 'from_location', 'to_location', 'from_keeper_id', 'to_keeper_id', 'remark', 'created_by'];

    protected $casts = ['asset_id' => 'integer', 'from_keeper_id' => 'integer', 'to_keeper_id' => 'integer', 'created_by' => 'integer'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }
}
