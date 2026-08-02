<?php

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * V0.5.5 维修工单状态机服务
 *
 * 所有状态转换都走这里, 写 audit + 锁单检查
 */
class WorkOrderService
{
    /**
     * 派单 (pending → assigned)
     */
    public function assign(WorkOrder $wo, int $engineerId, ?string $note = null): WorkOrder
    {
        $this->ensureTransition($wo, WorkOrderStatus::ASSIGNED);

        return DB::transaction(function () use ($wo, $engineerId, $note) {
            $wo->assigned_to = $engineerId;
            $wo->status = WorkOrderStatus::ASSIGNED;
            $wo->save();

            Audit::write('work_order_assigned', sprintf(
                '工单 %s 派单给工程师 #%d%s',
                $wo->code, $engineerId, $note ? "（{$note}）" : ''
            ), [
                'work_order_id' => $wo->id,
                'engineer_id'   => $engineerId,
            ]);

            return $wo->fresh();
        });
    }

    /**
     * 开始 (assigned → in_progress)
     */
    public function start(WorkOrder $wo): WorkOrder
    {
        $this->ensureTransition($wo, WorkOrderStatus::IN_PROGRESS);

        return DB::transaction(function () use ($wo) {
            $wo->status = WorkOrderStatus::IN_PROGRESS;
            $wo->started_at = now();
            $wo->save();

            Audit::write('work_order_started', "工单 {$wo->code} 开始服务", [
                'work_order_id' => $wo->id,
            ]);

            return $wo->fresh();
        });
    }

    /**
     * 完成 (in_progress → resolved)
     */
    public function resolve(WorkOrder $wo, string $resultNotes, float $serviceFee = 0, float $partsCost = 0, ?string $customerSignature = null): WorkOrder
    {
        $this->ensureTransition($wo, WorkOrderStatus::RESOLVED);

        return DB::transaction(function () use ($wo, $resultNotes, $serviceFee, $partsCost, $customerSignature) {
            $wo->status = WorkOrderStatus::RESOLVED;
            $wo->completed_at = now();
            $wo->result_notes = $resultNotes;
            $wo->service_fee = $serviceFee;
            $wo->parts_cost = $partsCost;
            $wo->total_cost = $serviceFee + $partsCost;
            if ($customerSignature) {
                $wo->customer_signature = $customerSignature;
                $wo->customer_signed_at = now();
                $wo->customer_signature_ip = request()->ip();
            }
            $wo->save();
            $wo->lock();

            Audit::write('work_order_resolved', sprintf(
                '工单 %s 已解决 (服务费 ¥%.2f + 配件 ¥%.2f)%s',
                $wo->code, $serviceFee, $partsCost,
                $customerSignature ? ' (含客户签字)' : ''
            ), [
                'work_order_id' => $wo->id,
                'service_fee'   => $serviceFee,
                'parts_cost'    => $partsCost,
                'total_cost'    => $wo->total_cost,
                'has_signature' => (bool) $customerSignature,
            ]);

            return $wo->fresh();
        });
    }

    /**
     * 取消 (任意非终态 → cancelled)
     */
    public function cancel(WorkOrder $wo, string $reason): WorkOrder
    {
        $this->ensureTransition($wo, WorkOrderStatus::CANCELLED);

        return DB::transaction(function () use ($wo, $reason) {
            $wo->status = WorkOrderStatus::CANCELLED;
            $wo->result_notes = "[取消] {$reason}";
            $wo->completed_at = now();
            $wo->save();
            $wo->lock();

            Audit::write('work_order_cancelled', "工单 {$wo->code} 取消: {$reason}", [
                'work_order_id' => $wo->id,
                'reason'        => $reason,
            ]);

            return $wo->fresh();
        });
    }

    /**
     * 状态机检查
     */
    private function ensureTransition(WorkOrder $wo, WorkOrderStatus $to): void
    {
        if ($wo->is_locked) {
            throw new \LogicException("工单 {$wo->code} 已锁定 (转返修/已取消/已解决), 不可变更状态");
        }
        if ($wo->status->isTerminal()) {
            throw new \LogicException("工单 {$wo->code} 已处于终态 ({$wo->status->value}), 不可再变更");
        }
        if (!$wo->status->canTransitionTo($to)) {
            throw new \LogicException("非法状态转换: {$wo->status->value} → {$to->value}");
        }
    }
}
