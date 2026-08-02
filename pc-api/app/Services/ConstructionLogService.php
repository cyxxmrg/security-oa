<?php

namespace App\Services;

use App\Models\ConstructionLog;
use App\Models\RectificationDailyRequired;
use App\Models\WorkProcess;
use App\Models\WorkProcessProgress;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.3 施工日志服务
 *
 * 关键流程:
 *  - submitLog:  提交日志 (upsert rectification_daily_required.status=submitted)
 *  - updateProgress: 累加/重算工序进度
 *  - getOverdueLogs: 漏报检测
 */
class ConstructionLogService
{
    /**
     * 提交日志
     *
     * 行为:
     *  1. 写 construction_logs
     *  2. 联动 upsert rectification_daily_required: status=submitted, submitted_log_id=log.id
     *  3. 若带 process_progress, 触发 updateProgress
     */
    public function submitLog(array $data, int $userId, ?int $logId = null): ConstructionLog
    {
        return DB::transaction(function () use ($data, $userId, $logId) {
            // 0) 若指定 logId, 直接从 DB 取出
            if ($logId) {
                $log = ConstructionLog::findOrFail($logId);
                $log->update(['status' => ConstructionLog::STATUS_SUBMITTED]);
            } else {
                // 1) 防重：同一 project+date 同一 user (草稿覆盖)
                // 表字段: content (text) / progress_percentage / problems / solutions
                $payload = [
                    'content'             => $data['content']             ?? $data['work_content']  ?? '',
                    'progress_percentage' => $data['progress_percentage'] ?? 0,
                    'problems'            => $data['problems']            ?? null,
                    'solutions'           => $data['solutions']           ?? null,
                    'weather'             => $data['weather']             ?? null,
                    'photos'              => $data['photos']              ?? null,
                    'work_hours'          => $data['work_hours']          ?? 0,
                    'worker_count'        => $data['worker_count']        ?? 0,
                    'team_id'             => $data['team_id']             ?? null,
                    'process_id'          => $data['process_id']          ?? null,
                    'is_rectification'    => $data['is_rectification']    ?? false,
                ];
                $log = ConstructionLog::updateOrCreate(
                    [
                        'project_id' => $data['project_id'],
                        'user_id'    => $userId,
                        'work_date'  => $data['work_date'],
                    ],
                    array_merge($payload, [
                        'user_id' => $userId,
                        'status'  => $payload['is_rectification'] ? ConstructionLog::STATUS_DRAFT : ($data['status'] ?? ConstructionLog::STATUS_SUBMITTED),
                    ])
                );
            }

            // 2) 联动日报需求单
            $required = RectificationDailyRequired::where('project_id', $log->project_id)
                ->where('work_date', $log->work_date)
                ->where('is_required', true)
                ->first();

            if ($required) {
                $required->update([
                    'status'          => RectificationDailyRequired::STATUS_SUBMITTED,
                    'submitted_log_id' => $log->id,
                ]);
            }

            // 3) 工序进度 (若有)
            if (!empty($data['process_progress']) && is_array($data['process_progress'])) {
                $this->applyProcessProgress($log, $data['process_progress']);
            }

            return $log->fresh(['project', 'user', 'team', 'commencementOrder']);
        });
    }

    /**
     * 更新日志 (草稿编辑)
     */
    public function updateLog(int $logId, array $data): ConstructionLog
    {
        return DB::transaction(function () use ($logId, $data) {
            $log = ConstructionLog::findOrFail($logId);
            if ($log->status !== 'draft') {
                throw new \RuntimeException('已提交的日志不可修改');
            }
            $log->update($data);
            return $log->fresh();
        });
    }

    /**
     * 累加工序进度
     *
     * @param array $progresses [{process_id, completed_qty, percentage?}, ...]
     */
    public function applyProcessProgress(ConstructionLog $log, array $progresses): void
    {
        foreach ($progresses as $row) {
            $processId = (int) ($row['process_id'] ?? 0);
            if ($processId <= 0) {
                continue;
            }
            $qty = (float) ($row['completed_qty'] ?? 0);

            $progress = WorkProcessProgress::where('process_id', $processId)
                ->where('project_id', $log->project_id)
                ->first();

            if (!$progress) {
                // 防御性: 日志到时工序进度记录不存在则补建
                $process = WorkProcess::find($processId);
                if (!$process) {
                    continue;
                }
                $progress = WorkProcessProgress::create([
                    'process_id'            => $process->id,
                    'project_id'            => $log->project_id,
                    'team_id'               => $log->team_id,
                    'planned_quantity'      => $process->planned_quantity,
                    'completed_quantity'    => $qty,
                    'progress_percentage'   => 0,
                    'status'                => WorkProcessProgress::STATUS_IN_PROGRESS,
                ]);
            } else {
                $completed = (float) $progress->completed_quantity + $qty;
                $pct = ((float) $progress->planned_quantity) > 0
                    ? round($completed / (float) $progress->planned_quantity * 100, 2)
                    : 0.00;
                $pct = min(100.0, $pct);

                $newStatus = $pct >= 100.0
                    ? WorkProcessProgress::STATUS_COMPLETED
                    : WorkProcessProgress::STATUS_IN_PROGRESS;

                $progress->update([
                    'completed_quantity'   => $completed,
                    'progress_percentage'  => $pct,
                    'status'               => $newStatus,
                    'last_log_id'          => $log->id,
                    'last_log_date'        => $log->work_date,
                    'updated_by'           => $log->user_id,
                ]);
            }
        }
    }

    /**
     * 单工序手动更新 (供 Controller / 整改流程调用)
     */
    public function updateProgress(int $logId, int $processId, float $completedQty): WorkProcessProgress
    {
        return DB::transaction(function () use ($logId, $processId, $completedQty) {
            $log = ConstructionLog::findOrFail($logId);
            $progress = WorkProcessProgress::where('process_id', $processId)
                ->where('project_id', $log->project_id)
                ->firstOrFail();

            $completed = (float) $progress->completed_quantity + $completedQty;
            $pct = ((float) $progress->planned_quantity) > 0
                ? round($completed / (float) $progress->planned_quantity * 100, 2)
                : 0.00;
            $pct = min(100.0, $pct);

            $progress->update([
                'completed_quantity'   => $completed,
                'progress_percentage'  => $pct,
                'status'               => $pct >= 100.0
                    ? WorkProcessProgress::STATUS_COMPLETED
                    : WorkProcessProgress::STATUS_IN_PROGRESS,
                'last_log_id'          => $log->id,
                'last_log_date'        => $log->work_date,
                'updated_by'           => $log->user_id,
            ]);

            return $progress->fresh();
        });
    }

    /**
     * 漏报日志: rectification_daily_required.status=pending 且 work_date < today
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOverdueLogs(?int $projectId = null, ?int $commencementOrderId = null, int $days = 0)
    {
        $cutoff = now()->subDays($days)->toDateString();
        $q = RectificationDailyRequired::with(['project:id,name,manager_id', 'commencementOrder:id,code,team_id'])
            ->where('status', RectificationDailyRequired::STATUS_PENDING)
            ->where('work_date', '<', $cutoff);

        if ($projectId) {
            $q->where('project_id', $projectId);
        }
        if ($commencementOrderId) {
            $q->where('commencement_order_id', $commencementOrderId);
        }

        return $q->orderBy('work_date')->get();
    }

    /**
     * v0.5.8: 逾期日志列表 (Controller overdue() 调用)
     * 简化: 复用 getOverdueLogs 逻辑
     */
    public function listOverdue(array $params = []): array
    {
        $list = $this->getOverdueLogs(
            $params['project_id'] ?? null,
            null,
            (int)($params['days'] ?? 0)
        );
        return [
            'items' => $list,
            'total' => $list->count(),
        ];
    }

    /**
     * 项目施工日志列表 (含日报需求对照)
     */
    public function listLogs(int $projectId, array $filters = []): array
    {
        $q = ConstructionLog::with(['user:id,name', 'team:id,team_name', 'commencementOrder:id,code'])
            ->where('project_id', $projectId);

        if (!empty($filters['commencement_order_id'])) {
            $q->where('commencement_order_id', $filters['commencement_order_id']);
        }
        if (!empty($filters['team_id'])) {
            $q->where('team_id', $filters['team_id']);
        }
        if (!empty($filters['user_id'])) {
            $q->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['work_date_from'])) {
            $q->where('work_date', '>=', $filters['work_date_from']);
        }
        if (!empty($filters['work_date_to'])) {
            $q->where('work_date', '<=', $filters['work_date_to']);
        }
        if (!empty($filters['is_rectification'])) {
            $q->where('is_rectification', (bool) $filters['is_rectification']);
        }

        $total = (clone $q)->count();
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $size  = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $items = $q->orderByDesc('work_date')->skip(($page - 1) * $size)->take($size)->get();

        return ['items' => $items, 'total' => $total];
    }
}
