<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 推荐人居间费结算单 (v0.3.11 P0)
 *
 * 触发：oppsMarkWon 事务内自动建 pending 单（若 lead.referrer_id 非空）
 * 状态：pending → approved (财务审核) → paid (财务发放 + 上传回单)
 */
class ReferralSettlement extends Model
{
    use HasFactory;
    protected $table = 'referral_settlements';
    protected $fillable = [
        'opportunity_id', 'referrer_id', 'lead_id', 'amount', 'commission_rate', 'contract_amount',
        'status', 'created_by', 'approved_by', 'approved_at', 'paid_by', 'paid_at',
        'payment_voucher', 'payment_no', 'notes',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'contract_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function referrer(): BelongsTo { return $this->belongsTo(Referrer::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function payer(): BelongsTo { return $this->belongsTo(User::class, 'paid_by'); }
}
