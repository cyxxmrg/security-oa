<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesProduct extends Model
{
    use HasFactory;
    protected $table = 'sales_products';
    protected $fillable = [
        'code', 'name', 'category_id', 'unit', 'spec',
        'sale_price', 'cost_price', 'description', 'status',
    ];
    protected $casts = [
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($prod) {
            if (empty($prod->code)) {
                $prod->code = 'SP-' . date('YmdHis') . substr((string) microtime(true), -4) . random_int(100, 999);
            }
        });
    }

    public function categoryRef(): BelongsTo { return $this->belongsTo(InventoryCategory::class, 'category_id'); }
}
