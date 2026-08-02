<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderProject extends Model
{
    protected $table = 'tender_projects';
    protected $fillable = [
        'code', 'name', 'description', 'project_id', 'rfq_id', 'created_by',
        'type', 'status', 'required_items', 'invited_supplier_ids',
        'publish_at', 'deadline', 'open_at', 'public_token',
        'awarded_bid_id', 'awarded_supplier_id', 'awarded_at', 'score_config',
        // V0.6.5 Sprint 4 审核/撤回字段
        'reject_reason', 'reviewer_id', 'reviewed_at',
        'withdrawn_at', 'withdrawn_by', 'withdraw_reason',
        'cancelled_at', 'cancelled_by', 'cancelled_reason',
    ];

    protected $casts = [
        'required_items'      => 'array',
        'invited_supplier_ids' => 'array',
        'score_config'        => 'array',
        'publish_at'          => 'datetime',
        'deadline'            => 'datetime',
        'open_at'             => 'datetime',
        'awarded_at'          => 'datetime',
        'reviewed_at'         => 'datetime',
        'withdrawn_at'        => 'datetime',
        'cancelled_at'        => 'datetime',
    ];

    /** V0.6.5: 合法状态枚举 — 状态机白名单 */
    public const STATUS_DRAFT          = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_OPEN           = 'open';
    public const STATUS_WITHDRAWN      = 'withdrawn';
    public const STATUS_REJECTED       = 'rejected';
    public const STATUS_CANCELLED      = 'cancelled';
    public const STATUS_CLOSED         = 'closed';

    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_OPEN,
        self::STATUS_WITHDRAWN,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_CLOSED,
    ];

    public function bids(): HasMany { return $this->hasMany(TenderBid::class); }
    public function attachments(): HasMany { return $this->hasMany(TenderAttachment::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function rfq(): BelongsTo { return $this->belongsTo(ExternalQuoteRequest::class, 'rfq_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function awardedSupplier(): BelongsTo { return $this->belongsTo(Supplier::class, 'awarded_supplier_id'); }
    public function awardedBid(): BelongsTo { return $this->belongsTo(TenderBid::class, 'awarded_bid_id'); }
    // V0.6.4 招标联动
    public function awardedPO(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'awarded_po_id'); }
    /** 中标后所有下游 PO (理论上 1-1, 但留 hasMany 兼容重定标) */
    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class, 'tender_id'); }

    // V0.6.5 Sprint 4: 审核/撤回
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function withdrawnByUser(): BelongsTo { return $this->belongsTo(User::class, 'withdrawn_by'); }
    public function cancelledByUser(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    // 保证金
    public function depositRule(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(TenderDepositRule::class, 'tender_project_id'); }
    public function deposits(): HasMany { return $this->hasMany(TenderDeposit::class, 'tender_project_id'); }

    /** V0.6.5: 状态机 — 哪些转移允许 */
    public const TRANSITIONS = [
        self::STATUS_DRAFT          => [self::STATUS_PENDING_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_PENDING_REVIEW => [self::STATUS_OPEN, self::STATUS_REJECTED, self::STATUS_DRAFT],
        self::STATUS_REJECTED       => [self::STATUS_DRAFT, self::STATUS_CANCELLED],
        self::STATUS_OPEN           => [self::STATUS_WITHDRAWN, self::STATUS_CANCELLED, self::STATUS_CLOSED],
        self::STATUS_WITHDRAWN      => [],
        self::STATUS_CANCELLED      => [],
        self::STATUS_CLOSED         => [],
    ];

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function canSubmitReview(): bool
    {
        return $this->canTransitionTo(self::STATUS_PENDING_REVIEW)
            && !empty($this->required_items);
    }

    public function canApprove(): bool
    {
        return $this->canTransitionTo(self::STATUS_OPEN);
    }

    public function canReject(): bool
    {
        return $this->canTransitionTo(self::STATUS_REJECTED);
    }

    public function canWithdraw(): bool
    {
        // 已发布且还没收到任何 bid 才能撤回
        return $this->canTransitionTo(self::STATUS_WITHDRAWN)
            && $this->bids()->count() === 0;
    }

    public function canCancel(): bool
    {
        return $this->canTransitionTo(self::STATUS_CANCELLED);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT          => '草稿',
            self::STATUS_PENDING_REVIEW => '待审核',
            self::STATUS_OPEN           => '已发布',
            self::STATUS_WITHDRAWN      => '已撤回',
            self::STATUS_REJECTED       => '已驳回',
            self::STATUS_CANCELLED      => '已废标',
            self::STATUS_CLOSED         => '已定标/截止',
            // 兼容旧状态
            'published', 'bidding', 'evaluating' => '已发布',
            'awarded'    => '已定标',
            default      => $this->status ?? '未知',
        };
    }

    /** V0.6.5: 当前状态下可执行的操作 (前端按钮显隐) */
    public function availableActions(): array
    {
        $actions = [];
        if ($this->canSubmitReview()) $actions[] = 'submit_review';
        if ($this->canApprove())      $actions[] = 'approve';
        if ($this->canReject())       $actions[] = 'reject';
        if ($this->canWithdraw())     $actions[] = 'withdraw';
        if ($this->canCancel())       $actions[] = 'cancel';
        return $actions;
    }
}
