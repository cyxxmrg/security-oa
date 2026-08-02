<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 固定资产台账 — V1.4.0
 *
 * source=tool 的记录与工具使用单打通 (tool_id -> tools.id), 由库存转工具自动生成。
 */
class FixedAsset extends Model
{
    protected $table = 'fixed_assets';

    protected $fillable = [
        'asset_no', 'category_id', 'name', 'specification', 'unit', 'quantity',
        'source', 'tool_id', 'inventory_item_id',
        'original_value', 'net_residual_value', 'useful_life_months', 'acquisition_date',
        'depreciation_method', 'accumulated_depreciation', 'net_book_value',
        'status', 'location', 'keeper_id', 'remark', 'created_by',
    ];

    protected $casts = [
        'category_id'           => 'integer',
        'quantity'              => 'integer',
        'tool_id'               => 'integer',
        'inventory_item_id'     => 'integer',
        'original_value'        => 'float',
        'net_residual_value'    => 'float',
        'useful_life_months'    => 'integer',
        'accumulated_depreciation' => 'float',
        'net_book_value'        => 'float',
        'keeper_id'             => 'integer',
        'created_by'            => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'tool_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function keeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keeper_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class, 'asset_id')->orderBy('period');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class, 'asset_id')->orderByDesc('date');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class, 'asset_id')->orderByDesc('date');
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class, 'asset_id')->orderByDesc('date');
    }

    /** 月折旧额 (直线法) */
    public function monthlyDepreciation(): float
    {
        if ((int) $this->useful_life_months <= 0) {
            return 0;
        }
        $base = (float) $this->original_value - (float) $this->net_residual_value;
        return round(max(0, $base) / (int) $this->useful_life_months, 2);
    }
}
