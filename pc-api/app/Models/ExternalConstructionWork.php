<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * V0.4.3 施工发包 (External Construction Work)
 *
 * 表: external_construction_works
 * 主键: id
 *
 * 与 external_quotes (V0.4.2 对外询价) 区别：
 *  - 本表是 *施工发包*，不走 PO 中标链路，直接走 标段/合同/应付 链路
 *  - 复用 supplier 账号投标
 *  - 中标后产生 supplier_payables (type=construction) + project_actual_costs (category=outsource)
 */
class ExternalConstructionWork extends Model
{
    use SoftDeletes;

    protected $table = 'external_construction_works';

    protected $fillable = [
        'project_id', 'code', 'title', 'description',
        'work_scope', 'work_quantity', 'unit', 'bid_type',
        'budget_amount', 'bid_deadline', 'start_date', 'end_date',
        'requirements', 'attachments',
        'status', 'awarded_supplier_id', 'awarded_bid_id',
        'awarded_at', 'awarded_by',
        'created_by', 'remark',
    ];

    protected $casts = [
        'requirements'  => 'array',
        'attachments'   => 'array',
        'work_quantity' => 'decimal:2',
        'budget_amount' => 'decimal:2',
        'bid_deadline'  => 'datetime',
        'start_date'    => 'date',
        'end_date'      => 'date',
        'awarded_at'    => 'datetime',
    ];

    /** 状态 */
    public const STATUS_DRAFT       = 'draft';       // 草稿
    public const STATUS_OPEN        = 'open';        // 投标中
    public const STATUS_SHORTLIST   = 'shortlist';   // 评标中
    public const STATUS_AWARDED     = 'awarded';     // 已定标
    public const STATUS_CANCELLED   = 'cancelled';   // 已取消
    public const STATUS_CLOSED      = 'closed';      // 关闭

    // ========== 关联 ==========

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function awardedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'awarded_supplier_id');
    }

    public function awardedBid(): BelongsTo
    {
        return $this->belongsTo(ExternalConstructionBid::class, 'awarded_bid_id');
    }

    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(ExternalConstructionBid::class, 'work_id');
    }

    // ========== Helper ==========

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => '草稿',
            self::STATUS_OPEN      => '投标中',
            self::STATUS_SHORTLIST => '评标中',
            self::STATUS_AWARDED   => '已定标',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_CLOSED    => '已关闭',
            default                => $this->status,
        };
    }
}
