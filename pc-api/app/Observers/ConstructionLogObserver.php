<?php

namespace App\Observers;

use App\Models\ConstructionLog;
use App\Models\RectificationDailyRequired;
use App\Services\ConstructionLogService;

/**
 * V0.4.3 施工日志 Observer
 *
 * 行为:
 *  - created / updated 时, 把对应 rectification_daily_required.status = submitted
 *  - 若 log 含 process_progress, 走 Service 累加工序进度
 *  - 删除时, 把对应日报需求回退为 pending (便于重报)
 *
 * V0.4.4 修复: rectification_daily_required 表实际字段是 is_required (不是 is_rectification)
 *             is_rectification 在 construction_logs 表
 */
class ConstructionLogObserver
{
    public function __construct(private ConstructionLogService $service) {}

    public function created(ConstructionLog $log): void
    {
        $this->syncDailyRequired($log, RectificationDailyRequired::STATUS_SUBMITTED);
    }

    public function updated(ConstructionLog $log): void
    {
        // 状态变为已提交/已审批时再次同步
        if (in_array($log->status, [
            ConstructionLog::STATUS_SUBMITTED,
            ConstructionLog::STATUS_APPROVED,
        ], true)) {
            $this->syncDailyRequired($log, RectificationDailyRequired::STATUS_SUBMITTED);
        }
    }

    public function deleted(ConstructionLog $log): void
    {
        // 删除后日报需求回退为 pending (允许重新提交)
        RectificationDailyRequired::where('submitted_log_id', $log->id)
            ->update([
                'status'          => RectificationDailyRequired::STATUS_PENDING,
                'submitted_log_id' => null,
                'updated_at'      => now(),
            ]);
    }

    /**
     * 同步日报需求单
     */
    private function syncDailyRequired(ConstructionLog $log, string $status): void
    {
        if (!$log->project_id || !$log->work_date) {
            return;
        }

        $req = RectificationDailyRequired::where('project_id', $log->project_id)
            ->where('work_date', $log->work_date)
            ->where('is_required', true)
            ->first();

        if ($req) {
            $req->update([
                'status'          => $status,
                'submitted_log_id' => $log->id,
            ]);
        }

        // 累加工序进度 (仅 submitted/approved 状态)
        if (!empty($log->process_progress) && is_array($log->process_progress)) {
            $this->service->applyProcessProgress($log, $log->process_progress);
        }
    }
}
