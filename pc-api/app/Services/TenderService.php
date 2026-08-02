<?php

namespace App\Services;

use App\Models\TenderProject;
use App\Models\TenderBid;
use App\Models\TenderDeposit;
use App\Models\TenderDepositRule;
use App\Models\ApprovalRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * V0.6.5 招标服务 — 状态机 + 保证金业务封装
 *
 * 所有状态转移走 status-machine + DB::transaction, 写 purchase_status_logs 审计
 *
 * 状态机:
 *   draft ─submit_review→ pending_review ─approve→ open ──withdraw→ withdrawn
 *                                              │              │
 *                                              └─reject→ rejected
 *                                                                │
 *                                            draft ←────────────┘
 *
 *   open/pending_review/etc ─cancel→ cancelled (不可逆)
 *
 * 保证金状态机:
 *   pending ──markPaid──→ paid ──refund──→ refunded
 *                                  ──forfeit──→ forfeited
 *                                  ──partialRefund──→ partial_refund
 */
class TenderService
{
    // ========== 状态机操作 ==========

    /**
     * 提交审核：草稿 → 待审核
     */
    public function submitReview(int $tenderId, ?string $note = null): TenderProject
    {
        return DB::transaction(function () use ($tenderId, $note) {
            $t = TenderProject::lockForUpdate()->findOrFail($tenderId);
            if (!$t->canSubmitReview()) {
                throw new RuntimeException("当前状态 [{$t->status_label}] 不能提交审核 (要求 draft + 必填物料)");
            }
            $t->status = TenderProject::STATUS_PENDING_REVIEW;
            $t->save();
            $this->log($t, null, TenderProject::STATUS_PENDING_REVIEW, 'submit_review', $note ?? '提交审核');

            // V1.2.5: 同步创建审批中心记录 (operation/tender)
            $this->syncApproval($t, 'submit', $note);

            return $t->fresh();
        });
    }

    /**
     * 审核通过：pending_review → open
     */
    public function approve(int $tenderId, ?string $note = null): TenderProject
    {
        return DB::transaction(function () use ($tenderId, $note) {
            $t = TenderProject::lockForUpdate()->findOrFail($tenderId);
            if (!$t->canApprove()) {
                throw new RuntimeException("当前状态 [{$t->status_label}] 不能审核通过 (要求 pending_review)");
            }
            $t->status       = TenderProject::STATUS_OPEN;
            $t->reviewer_id  = Auth::id();
            $t->reviewed_at  = now();
            $t->reject_reason = null;
            $t->publish_at   = $t->publish_at ?? now();
            $t->save();
            $this->log($t, TenderProject::STATUS_PENDING_REVIEW, TenderProject::STATUS_OPEN, 'approve', $note ?? '审核通过');

            // V1.2.5: 同步更新审批中心记录
            $this->syncApproval($t, 'approve', $note);

            return $t->fresh();
        });
    }

    /**
     * 驳回：pending_review → rejected (可打回 draft)
     */
    public function reject(int $tenderId, string $reason, bool $backToDraft = false): TenderProject
    {
        return DB::transaction(function () use ($tenderId, $reason, $backToDraft) {
            $t = TenderProject::lockForUpdate()->findOrFail($tenderId);
            if (!$t->canReject()) {
                throw new RuntimeException("当前状态 [{$t->status_label}] 不能驳回 (要求 pending_review)");
            }
            $t->status        = $backToDraft ? TenderProject::STATUS_DRAFT : TenderProject::STATUS_REJECTED;
            $t->reject_reason = $reason;
            $t->reviewer_id   = Auth::id();
            $t->reviewed_at   = now();
            $t->save();
            $this->log($t, TenderProject::STATUS_PENDING_REVIEW, $t->status, 'reject', "驳回: {$reason}");

            // V1.2.5: 同步更新审批中心记录
            $this->syncApproval($t, 'reject', $reason);

            return $t->fresh();
        });
    }

    /**
     * 撤回：open → withdrawn (仅当 0 个 bid)
     */
    public function withdraw(int $tenderId, string $reason): TenderProject
    {
        return DB::transaction(function () use ($tenderId, $reason) {
            $t = TenderProject::lockForUpdate()->findOrFail($tenderId);
            if (!$t->canWithdraw()) {
                throw new RuntimeException("当前状态 [{$t->status_label}] 且 bid_count=0 才能撤回 (已有投标的撤回请走废标流程)");
            }
            $t->status          = TenderProject::STATUS_WITHDRAWN;
            $t->withdrawn_at    = now();
            $t->withdrawn_by    = Auth::id();
            $t->withdraw_reason = $reason;
            $t->save();
            $this->log($t, TenderProject::STATUS_OPEN, TenderProject::STATUS_WITHDRAWN, 'withdraw', "撤回: {$reason}");
            return $t->fresh();
        });
    }

    /**
     * 废标：任意非终态 → cancelled
     */
    public function cancel(int $tenderId, string $reason): TenderProject
    {
        return DB::transaction(function () use ($tenderId, $reason) {
            $t = TenderProject::lockForUpdate()->findOrFail($tenderId);
            if (!$t->canCancel()) {
                throw new RuntimeException("当前状态 [{$t->status_label}] 已终态, 不能废标");
            }
            $t->status           = TenderProject::STATUS_CANCELLED;
            $t->cancelled_at     = now();
            $t->cancelled_by     = Auth::id();
            $t->cancelled_reason = $reason;
            $t->save();
            // 已有 bid 全部撤回
            $t->bids()->whereNotIn('status', ['rejected', 'withdrawn'])->update(['status' => 'withdrawn']);
            $this->log($t, null, TenderProject::STATUS_CANCELLED, 'cancel', "废标: {$reason}");
            return $t->fresh();
        });
    }

    // ========== 保证金操作 ==========

    /**
     * 设置/更新 招标的保证金规则
     */
    public function setDepositRule(int $tenderId, array $data): TenderDepositRule
    {
        return DB::transaction(function () use ($tenderId, $data) {
            $t = TenderProject::lockForUpdate()->findOrFail($tenderId);
            if (in_array($t->status, [TenderProject::STATUS_CLOSED, TenderProject::STATUS_CANCELLED, TenderProject::STATUS_WITHDRAWN], true)) {
                throw new RuntimeException("终态招标不能修改保证金规则");
            }

            $payload = [
                'required'                  => (bool) ($data['required'] ?? true),
                'amount'                    => (float) ($data['amount'] ?? 0),
                'deadline_hours_before_open' => (int) ($data['deadline_hours_before_open'] ?? 24),
                'refund_policy'             => $data['refund_policy'] ?? ['auto_refund_days' => 7, 'forfeit_on_no_contract_sign_days' => 14],
                'bank_account'              => $data['bank_account'] ?? null,
                'note'                      => $data['note'] ?? null,
            ];

            $rule = TenderDepositRule::updateOrCreate(
                ['tender_project_id' => $tenderId],
                array_merge($payload, [
                    'created_by' => $payload['created_by'] ?? Auth::id(),
                ])
            );

            // 同步金额到所有现有 pending 状态 deposit (不影响 paid/refunded)
            TenderDeposit::where('tender_project_id', $tenderId)
                ->where('status', TenderDeposit::STATUS_PENDING)
                ->update(['amount' => $payload['amount']]);

            $this->log($t, null, $t->status, 'set_deposit_rule', "设置保证金: required={$payload['required']}, amount={$payload['amount']}");
            return $rule->fresh();
        });
    }

    /**
     * 标记已收保证金（财务手动确认）
     */
    public function markDepositPaid(int $depositId, ?string $voucherPath = null): TenderDeposit
    {
        return DB::transaction(function () use ($depositId, $voucherPath) {
            $d = TenderDeposit::lockForUpdate()->findOrFail($depositId);
            if ($d->status !== TenderDeposit::STATUS_PENDING) {
                throw new RuntimeException("当前状态 [{$d->status_label}] 不能标记已收 (要求 pending)");
            }
            $d->status            = TenderDeposit::STATUS_PAID;
            $d->paid_at           = now();
            $d->paid_voucher_path = $voucherPath;
            $d->marked_paid_by    = Auth::id();
            $d->save();
            return $d->fresh();
        });
    }

    /**
     * 退还保证金（未中标方 / 中标方合同签后退）
     */
    public function refundDeposit(int $depositId, float $refundAmount, string $method, string $reason, ?string $voucherPath = null): TenderDeposit
    {
        return DB::transaction(function () use ($depositId, $refundAmount, $method, $reason, $voucherPath) {
            $d = TenderDeposit::lockForUpdate()->findOrFail($depositId);
            if ($d->status !== TenderDeposit::STATUS_PAID) {
                throw new RuntimeException("当前状态 [{$d->status_label}] 不能退款 (要求 paid)");
            }
            if ($refundAmount <= 0 || $refundAmount > (float)$d->amount) {
                throw new RuntimeException("退款金额必须在 (0, {$d->amount}] 区间");
            }

            $d->refund_amount        = $refundAmount;
            $d->refunded_at          = now();
            $d->refunded_by          = Auth::id();
            $d->refund_method        = $method;
            $d->refund_reason        = $reason;
            $d->refunded_voucher_path = $voucherPath;
            $d->status = ($refundAmount < (float)$d->amount)
                ? TenderDeposit::STATUS_PARTIAL_REFUND
                : TenderDeposit::STATUS_REFUNDED;
            $d->save();
            return $d->fresh();
        });
    }

    /**
     * 没收保证金（流标 / 中标方不签合同）
     */
    public function forfeitDeposit(int $depositId, string $reason): TenderDeposit
    {
        return DB::transaction(function () use ($depositId, $reason) {
            $d = TenderDeposit::lockForUpdate()->findOrFail($depositId);
            if (!in_array($d->status, [TenderDeposit::STATUS_PAID, TenderDeposit::STATUS_PENDING], true)) {
                throw new RuntimeException("当前状态 [{$d->status_label}] 不能没收 (要求 paid 或 pending)");
            }
            $d->status         = TenderDeposit::STATUS_FORFEITED;
            $d->forfeited_at   = now();
            $d->forfeited_by   = Auth::id();
            $d->forfeit_reason = $reason;
            $d->save();
            return $d->fresh();
        });
    }

    /**
     * 中标时联动保证金 — winner 留 paid (待合同后退)，其他未中标 supplier 自动 refund
     * 由 TenderController.award() 调用
     */
    public function onTenderAward(int $tenderId, int $winnerSupplierId, string $winnerReason = '中标方保证金待合同签订后退还'): void
    {
        $deposits = TenderDeposit::where('tender_project_id', $tenderId)
            ->whereIn('status', [TenderDeposit::STATUS_PAID, TenderDeposit::STATUS_PENDING])
            ->get();

        foreach ($deposits as $d) {
            if ($d->supplier_id === $winnerSupplierId) {
                // 中标方：保持 paid, 加备注
                $d->refund_reason = $winnerReason;
                $d->save();
            } else {
                // 其他供应商：自动发起退款（amount = 全额）
                $this->refundDeposit(
                    $d->id,
                    (float) $d->amount,
                    'bank_transfer',
                    '招标中标方已定, 自动退还未中标方保证金',
                    null
                );
            }
        }
    }

    /**
     * 检查供应商对招标的保证金状态：是否可投标
     *
     * @return array ['eligible' => bool, 'reason' => string, 'deposit' => TenderDeposit|null]
     */
    public function checkBidEligibility(int $tenderId, int $supplierId): array
    {
        $rule = TenderDepositRule::where('tender_project_id', $tenderId)->first();
        if (!$rule || !$rule->required || (float) $rule->amount <= 0) {
            return ['eligible' => true, 'reason' => '本招标不需要保证金', 'deposit' => null];
        }

        $deposit = TenderDeposit::where('tender_project_id', $tenderId)
            ->where('supplier_id', $supplierId)
            ->first();

        if (!$deposit) {
            return [
                'eligible' => false,
                'reason'   => "本招标需要保证金 ¥{$rule->amount}, 您尚未登记缴纳",
                'deposit'  => null,
            ];
        }

        if ($deposit->status !== TenderDeposit::STATUS_PAID) {
            return [
                'eligible' => false,
                'reason'   => "保证金状态: {$deposit->status_label}, 需先缴纳",
                'deposit'  => $deposit,
            ];
        }

        return ['eligible' => true, 'reason' => 'ok', 'deposit' => $deposit];
    }

    /**
     * 列出招标的所有保证金缴纳记录 (内部视图用)
     */
    public function listDeposits(int $tenderId)
    {
        return TenderDeposit::where('tender_project_id', $tenderId)
            ->with(['supplier:id,name,code,account_name,legal_person', 'markedPaidByUser:id,name', 'refundedByUser:id,name', 'forfeitedByUser:id,name'])
            ->orderBy('id')
            ->get();
    }

    /**
     * 审核队列（待审核列表）
     */
    public function listPendingReview()
    {
        return TenderProject::where('status', TenderProject::STATUS_PENDING_REVIEW)
            ->with(['creator:id,name', 'project:id,name,code'])
            ->orderBy('updated_at')
            ->paginate(20);
    }

    // ========== 内部 ==========

    private function log(TenderProject $t, ?string $from, ?string $to, string $action, string $note): void
    {
        // 复用 purchase_status_logs (采购 8 步通用审计表, 字段名是 remark 不是 note)
        DB::table('purchase_status_logs')->insert([
            'entity_type'    => 'tender',
            'entity_id'      => $t->id,
            'from_status'    => $from,
            'to_status'      => $to,
            'action'         => $action,
            'operator_id'    => Auth::id(),
            'operator_name'  => Auth::user()?->name ?? Auth::user()?->username ?? 'system',
            'remark'         => $note,
            'created_at'     => now(),
        ]);
    }
}
