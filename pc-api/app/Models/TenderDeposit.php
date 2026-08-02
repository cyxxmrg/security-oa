<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.6.5 招标保证金缴纳/退款记录 — 每个 (招标, 供应商) 1 条
 *
 * 状态机:
 *   pending → paid → refunded (退：未中标/合同签后退中标方)
 *                  → forfeited (没收：流标/中标不签合同)
 *                  → partial_refund (按比例退，违约场景)
 */
class TenderDeposit extends Model
{
    protected $table = 'tender_deposits';

    protected $fillable = [
        'tender_project_id', 'supplier_id', 'amount', 'status',
        'paid_at', 'paid_voucher_path', 'marked_paid_by',
        'refunded_at', 'refund_amount', 'refunded_voucher_path',
        'refunded_by', 'refund_reason', 'refund_method',
        'forfeited_at', 'forfeited_by', 'forfeit_reason',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'paid_at'      => 'datetime',
        'refunded_at'  => 'datetime',
        'forfeited_at' => 'datetime',
    ];

    public const STATUS_PENDING       = 'pending';
    public const STATUS_PAID          = 'paid';
    public const STATUS_REFUNDED      = 'refunded';
    public const STATUS_FORFEITED     = 'forfeited';
    public const STATUS_PARTIAL_REFUND = 'partial_refund';

    public function tender(): BelongsTo
    {
        return $this->belongsTo(TenderProject::class, 'tender_project_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function markedPaidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_paid_by');
    }

    public function refundedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function forfeitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forfeited_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING        => '待缴纳',
            self::STATUS_PAID           => '已缴纳',
            self::STATUS_REFUNDED       => '已退还',
            self::STATUS_FORFEITED      => '已没收',
            self::STATUS_PARTIAL_REFUND => '部分退还',
            default                     => $this->status ?? '未知',
        };
    }

    /** 是否可以开始投标 (已缴) */
    public function isEligibleToBid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
