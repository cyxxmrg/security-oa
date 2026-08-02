<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V0.6.5 招标保证金规则 — 每个招标独立 1 条
 *
 * - required: 是否必须缴纳 (允许某些招标不要求保证金)
 * - amount: 保证金金额
 * - deadline_hours_before_open: 距开标 N 小时前必须缴清
 * - refund_policy: {auto_refund_days: 7, forfeit_on_no_contract_sign_days: 14}
 *   auto_refund_days: 未中标方开标后 N 天自动退款
 *   forfeit_on_no_contract_sign_days: 中标方 N 天内不签合同 → 没收
 * - bank_account: 收款银行账户 (可空, 前端展示)
 */
class TenderDepositRule extends Model
{
    protected $table = 'tender_deposit_rules';

    protected $fillable = [
        'tender_project_id', 'required', 'amount',
        'deadline_hours_before_open', 'refund_policy',
        'bank_account', 'note', 'created_by',
    ];

    protected $casts = [
        'required'   => 'boolean',
        'amount'     => 'decimal:2',
        'refund_policy' => 'array',
    ];

    public function tender(): BelongsTo
    {
        return $this->belongsTo(TenderProject::class, 'tender_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 退款策略 — 默认值
     */
    public function getAutoRefundDaysAttribute(): int
    {
        return (int) ($this->refund_policy['auto_refund_days'] ?? 7);
    }

    public function getForfeitOnNoContractDaysAttribute(): int
    {
        return (int) ($this->refund_policy['forfeit_on_no_contract_sign_days'] ?? 14);
    }
}
