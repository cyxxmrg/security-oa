<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetInventoryItem extends Model
{
    protected $fillable = ['inventory_id', 'asset_id', 'book_qty', 'actual_qty', 'difference', 'note'];

    protected $casts = ['inventory_id' => 'integer', 'asset_id' => 'integer', 'book_qty' => 'integer', 'actual_qty' => 'integer', 'difference' => 'integer'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }
}
