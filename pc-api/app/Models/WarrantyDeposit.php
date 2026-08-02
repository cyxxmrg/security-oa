<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Concerns\HasDataScope;

/**
 * V0.4.5 质保期管理 - 质保金留置
 *
 * 表: warranty_deposits
 * 主键: id
 *
 * 业务:
 *  - deposit_amount = contract_amount × deposit_rate / 100 (应用层计算)
 *  - remainingAmount() = deposit_amount - release_amount - forfeit_amount
 *  - status 状态机: held → partial_released → fully_released / forfeited
 */
class WarrantyDeposit extends Model
{
    use SoftDeletes, HasDataScope;

    protected $table = 'warranty_deposits';

    protected $fillable = [
        'project_id', 'customer_id',
        'contract_amount', 'deposit_rate', 'deposit_amount',
        'hold_date', 'release_date',
        'status', 'release_amount', 'forfeit_amount', 'reason',
        'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = [
        'contract_amount' => 'decimal:2',
        'deposit_rate'    => 'decimal:2',
        'deposit_amount'  => 'decimal:2',
        'release_amount'  => 'decimal:2',
        'forfeit_amount'  => 'decimal:2',
        'hold_date'       => 'date',
        'release_date'    => 'date',
        'approved_at'     => 'datetime',
    ];

    /** 状态 */
    public const STATUS_HELD            = 'held';             // 留置中
    public const STATUS_PARTIAL_RELEASED = 'partial_released'; // 部分释放
    public const STATUS_FULLY_RELEASED  = 'fully_released';   // 全部释放
    public const STATUS_FORFEITED       = 'forfeited';        // 已没收

    // ========== 关联 ==========

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WarrantyDepositLog::class, 'deposit_id')->orderBy('created_at', 'desc');
    }

    // ========== Scope ==========

    public function scopeHeld($query)
    {
        return $query->where('status', self::STATUS_HELD);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_HELD,
            self::STATUS_PARTIAL_RELEASED,
        ]);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    // ========== Helper ==========

    /**
     * 剩余未结算金额 = 留置总额 - 已释放 - 已没收
     */
    public function remainingAmount(): float
    {
        $deposit  = (float) $this->deposit_amount;
        $released = (float) $this->release_amount;
        $forfeit  = (float) $this->forfeit_amount;
        return round($deposit - $released - $forfeit, 2);
    }

    public function isFullyReleased(): bool
    {
        return $this->status === self::STATUS_FULLY_RELEASED;
    }

    public function isForfeited(): bool
    {
        return $this->status === self::STATUS_FORFEITED;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_HELD             => '留置中',
            self::STATUS_PARTIAL_RELEASED => '部分释放',
            self::STATUS_FULLY_RELEASED   => '全部释放',
            self::STATUS_FORFEITED        => '已没收',
            default                       => (string) $this->status,
        };
    }
}
