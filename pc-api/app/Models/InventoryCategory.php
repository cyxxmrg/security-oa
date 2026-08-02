<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCategory extends Model
{
    use HasFactory;
    protected $table = 'inventory_categories';
    protected $fillable = ['parent_id', 'name', 'code', 'sort_order', 'description'];
    protected $casts = ['sort_order' => 'integer'];
    public function parent(): BelongsTo { return $this->belongsTo(InventoryCategory::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(InventoryCategory::class, 'parent_id'); }
    public function items(): HasMany { return $this->hasMany(InventoryItem::class, 'category_id'); }
}
