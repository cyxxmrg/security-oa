<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    protected $fillable = ['asset_id', 'period', 'month_depreciation', 'accumulated_after', 'net_value_after', 'created_by'];

    protected $casts = [
        'asset_id'           => 'integer',
        'month_depreciation' => 'float',
        'accumulated_after'  => 'float',
        'net_value_after'    => 'float',
        'created_by'         => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }
}
