<?php

namespace App\Observers;

use App\Models\ProjectCommencementOrder;
use App\Models\WorkProcessProgress;

/**
 * V0.4.3 开工单 Observer
 *
 * 行为:
 *  - status 变 in_progress: 激活该项目下 pending 工序进度 (Service 已做,这里兜底)
 *  - status 变 completed:  收尾未完成工序 (保守策略: 标 completed 不强制,留给人工)
 *  - status 变 cancelled:  关闭日报需求
 *
 * V0.4.4 修复:
 *  - work_process_progress 表无 commencement_order_id 列 → 改用 project_id 激活
 *  - 取消 actual_start_date/actual_end_date 列 (project_commencement_orders 表无)
 */
class CommencementOrderObserver
{
    public function updated(ProjectCommencementOrder $order): void
    {
        if (!$order->wasChanged('status')) {
            return;
        }

        $newStatus = $order->status;

        if ($newStatus === ProjectCommencementOrder::STATUS_IN_PROGRESS) {
            // 兜底激活 (V0.4.4: 改用 project_id, work_process_progress 表无 commencement_order_id 列)
            WorkProcessProgress::where('project_id', $order->project_id)
                ->where('status', WorkProcessProgress::STATUS_NOT_STARTED ?? 'not_started')
                ->update(['status' => WorkProcessProgress::STATUS_IN_PROGRESS ?? 'in_progress']);
        }

        if ($newStatus === ProjectCommencementOrder::STATUS_CANCELLED) {
            // 关闭日报需求 (Service cancel 已做, 这里冗余兜底)
            \App\Models\RectificationDailyRequired::where('commencement_order_id', $order->id)
                ->where('status', \App\Models\RectificationDailyRequired::STATUS_PENDING ?? 'pending')
                ->update([
                    'status'     => \App\Models\RectificationDailyRequired::STATUS_EXCUSED ?? 'excused',
                    'updated_at' => now(),
                ]);
        }
    }
}
