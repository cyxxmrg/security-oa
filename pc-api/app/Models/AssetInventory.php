<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetInventory extends Model
{
    protected $fillable = ['no', 'date', 'status', 'remark', 'created_by'];

    protected $casts = ['created_by' => 'integer'];

    public function items(): HasMany
    {
        return $this->hasMany(AssetInventoryItem::class, 'inventory_id');
    }
}
