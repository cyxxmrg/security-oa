<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.4.3 施工发包投标 (External Construction Bid)
 *
 * 表: external_construction_bids
 * 主键: id
 *
 * 关系：
 *  - work_id → external_construction_works.id
 *  - supplier_id → suppliers.id
 *  - bidder_user_id → users.id (供应商账号)
 */
class ExternalConstructionBid extends Model
{
    use SoftDeletes;

    protected $table = 'external_construction_bids';

    protected $fillable = [
        'work_id', 'supplier_id', 'bidder_user_id', 'code',
        'bid_amount', 'bid_quantity', 'unit_price',
        'lead_time_days', 'work_plan', 'team_size',
        'attachments', 'note',
        'status', 'score', 'score_comment', 'reviewed_by', 'reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'attachments'    => 'array',
        'bid_amount'     => 'decimal:2',
        'bid_quantity'   => 'decimal:2',
        'unit_price'     => 'decimal:2',
        'lead_time_days' => 'integer',
        'team_size'      => 'integer',
        'score'          => 'decimal:2',
        'reviewed_at'    => 'datetime',
        'submitted_at'   => 'datetime',
    ];

    /** 状态 */
    public const STATUS_SUBMITTED   = 'submitted';    // 已投标
    public const STATUS_SHORTLISTED = 'shortlisted';  // 入围
    public const STATUS_EVALUATED   = 'evaluated';    // 已评标
    public const STATUS_ACCEPTED    = 'accepted';     // 已中标
    public const STATUS_REJECTED    = 'rejected';     // 未中标/驳回
    public const STATUS_WITHDRAWN   = 'withdrawn';    // 撤回

    // ========== 关联 ==========

    public function work(): BelongsTo
    {
        return $this->belongsTo(ExternalConstructionWork::class, 'work_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bidder_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED   => '已投标',
            self::STATUS_SHORTLISTED => '已入围',
            self::STATUS_EVALUATED   => '已评标',
            self::STATUS_ACCEPTED    => '已中标',
            self::STATUS_REJECTED    => '未中标',
            self::STATUS_WITHDRAWN   => '已撤回',
            default                  => $this->status,
        };
    }
}
