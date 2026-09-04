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
 *  - work_process_progress 表无 commencement_order_id 列 → 按 project_id + team_id 激活
 *  - project_commencement_orders 仅使用数据库实际存在的日期字段
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
            WorkProcessProgress::where('project_id', $order->project_id)
                ->where('team_id', $order->team_id)
                ->where('status', WorkProcessProgress::STATUS_PENDING)
                ->update(['status' => WorkProcessProgress::STATUS_IN_PROGRESS]);
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
