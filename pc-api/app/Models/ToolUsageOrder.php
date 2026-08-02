<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 工具使用单 — V1.3.3
 *
 * 施工工具领用/归还跟踪单据头。
 * 领用/退还流水落 stock_records (type=tool_checkout / tool_return),
 * 通过 order_no = tool_usage_orders.code 关联。
 */
class ToolUsageOrder extends Model
{
    protected $table = 'tool_usage_orders';

    protected $fillable = [
        'code',
        'warehouse_id',
        'project_id',
        'applicant_id',
        'status',
        'remark',
        'created_by',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'project_id'   => 'integer',
        'applicant_id' => 'integer',
        'created_by'   => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 本单的领用/退还流水（stock_records.order_no = 本单 code）
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockRecord::class, 'order_no', 'code');
    }
}
