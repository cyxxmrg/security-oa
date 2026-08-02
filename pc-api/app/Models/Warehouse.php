<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'type', 'address', 'manager_id', 'status', 'description'];

    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_id'); }
    public function inventoryItems(): HasMany { return $this->hasMany(InventoryItem::class); }
}
