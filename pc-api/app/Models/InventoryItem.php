<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'category', 'category_id', 'specification', 'unit', 'safety_stock', 'min_stock', 'shelf_life_days', 'expiry_date', 'current_stock', 'cost_price', 'sell_price', 'warehouse_id', 'location', 'has_serial', 'status'];

    protected $casts = ['safety_stock' => 'integer', 'min_stock' => 'integer', 'shelf_life_days' => 'integer', 'expiry_date' => 'date', 'current_stock' => 'integer', 'cost_price' => 'decimal:2', 'sell_price' => 'decimal:2', 'has_serial' => 'boolean'];

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function serialNumbers(): HasMany { return $this->hasMany(DeviceSerialNumber::class); }
    public function stockRecords(): HasMany { return $this->hasMany(StockRecord::class); }
    public function categoryRef(): BelongsTo { return $this->belongsTo(InventoryCategory::class, 'category_id'); }

    public function isLowStock(): bool { return $this->min_stock > 0 && $this->current_stock <= $this->min_stock; }
}
