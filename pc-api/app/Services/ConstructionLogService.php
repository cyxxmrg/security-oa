<?php

namespace App\Services;

use App\Models\ConstructionLog;
use App\Models\ConstructionTeam;
use App\Models\Project;
use App\Models\ProjectCommencementOrder;
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
            $previousProgress = [];
            $shouldApplyStoredProgress = false;

            // 0) 若指定 logId, 直接从 DB 取出
            if ($logId) {
                $log = ConstructionLog::lockForUpdate()->findOrFail($logId);
                if (!in_array($log->status, [ConstructionLog::STATUS_DRAFT, ConstructionLog::STATUS_REJECTED], true)) {
                    throw new \RuntimeException('只有草稿或已驳回日志可提交');
                }
                $this->validateReferences($log->project_id, [
                    'team_id'               => $log->team_id,
                    'commencement_order_id' => $log->commencement_order_id,
                    'process_id'            => $log->process_id,
                    'process_progress'      => $log->process_progress ?? [],
                ]);
                $shouldApplyStoredProgress = $log->status === ConstructionLog::STATUS_DRAFT
                    && is_array($log->process_progress);
                $log->update(['status' => ConstructionLog::STATUS_SUBMITTED]);
            } else {
                $projectId = (int) ($data['project_id'] ?? 0);
                Project::lockForUpdate()->findOrFail($projectId);
                $this->validateReferences($projectId, $data);

                // 1) 防重：同一 project+date 同一 user (草稿覆盖)
                // 表字段: content (text) / progress_percentage / problems / solutions
                $payload = $this->buildPayload($data);
                $log = ConstructionLog::where('project_id', $projectId)
                    ->where('user_id', $userId)
                    ->where('work_date', $data['work_date'])
                    ->lockForUpdate()
                    ->first();
                if ($log) {
                    if (!in_array($log->status, [ConstructionLog::STATUS_DRAFT, ConstructionLog::STATUS_REJECTED], true)) {
                        throw new \RuntimeException('当天日志已提交，不可重复提交');
                    }
                    $previousProgress = $log->process_progress ?? [];
                    if (array_key_exists('team_id', $data)
                        && (int) ($log->team_id ?? 0) !== (int) ($data['team_id'] ?? 0)) {
                        throw new \RuntimeException('同一日报不可更换施工团队');
                    }
                    $log->update(array_merge($payload, [
                        'status' => ($data['is_rectification'] ?? $log->is_rectification)
                            ? ConstructionLog::STATUS_DRAFT
                            : ($data['status'] ?? ConstructionLog::STATUS_SUBMITTED),
                    ]));
                } else {
                    $log = ConstructionLog::create(array_merge([
                        'content'             => $data['content'] ?? $data['work_content'] ?? '',
                        'progress_percentage' => 0,
                        'work_hours'          => 0,
                        'worker_count'        => 0,
                        'is_rectification'    => false,
                    ], $payload, [
                        'project_id' => $projectId,
                        'user_id'    => $userId,
                        'work_date'  => $data['work_date'],
                        'status'     => ($data['is_rectification'] ?? false)
                            ? ConstructionLog::STATUS_DRAFT
                            : ($data['status'] ?? ConstructionLog::STATUS_SUBMITTED),
                    ]));
                }
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
            if ($shouldApplyStoredProgress) {
                $this->applyProcessProgress($log, $log->process_progress, []);
            } elseif (array_key_exists('process_progress', $data)
                && is_array($data['process_progress'])
                && in_array($log->status, [ConstructionLog::STATUS_SUBMITTED, ConstructionLog::STATUS_APPROVED], true)) {
                $this->applyProcessProgress($log, $data['process_progress'], $previousProgress);
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
            $log = ConstructionLog::lockForUpdate()->findOrFail($logId);
            if ($log->status !== ConstructionLog::STATUS_DRAFT) {
                throw new \RuntimeException('已提交的日志不可修改');
            }
            $log->update($data);
            return $log->fresh();
        });
    }

    private function buildPayload(array $data): array
    {
        $fields = [
            'content'             => $data['content'] ?? $data['work_content'] ?? null,
            'progress_percentage' => $data['progress_percentage'] ?? null,
            'problems'            => $data['problems'] ?? null,
            'solutions'           => $data['solutions'] ?? null,
            'weather'             => $data['weather'] ?? null,
            'photos'              => $data['photos'] ?? null,
            'work_hours'          => $data['work_hours'] ?? null,
            'worker_count'        => $data['worker_count'] ?? null,
            'location'            => $data['location'] ?? null,
            'commencement_order_id' => $data['commencement_order_id'] ?? null,
            'team_id'             => $data['team_id'] ?? null,
            'process_id'          => $data['process_id'] ?? null,
            'process_progress'    => $data['process_progress'] ?? null,
            'is_rectification'    => $data['is_rectification'] ?? null,
            'rectification_order_id' => $data['rectification_order_id'] ?? null,
        ];

        return array_filter($fields, static fn ($value) => $value !== null);
    }

    private function validateReferences(int $projectId, array $data): void
    {
        if (!empty($data['team_id'])) {
            ConstructionTeam::where('project_id', $projectId)
                ->findOrFail($data['team_id']);
        }

        if (!empty($data['commencement_order_id'])) {
            $order = ProjectCommencementOrder::where('project_id', $projectId)
                ->findOrFail($data['commencement_order_id']);

            if (!empty($data['team_id']) && $order->team_id !== null
                && (int) $order->team_id !== (int) $data['team_id']) {
                throw new \RuntimeException('施工团队与开工单不匹配');
            }
        }

        if (!empty($data['process_id'])) {
            $this->findProcessForProject($projectId, (int) $data['process_id']);
        }

        foreach (($data['process_progress'] ?? []) as $row) {
            $processId = (int) ($row['process_id'] ?? 0);
            if ($processId <= 0) {
                throw new \RuntimeException('工序编号无效');
            }
            $this->findProcessForProject($projectId, $processId);
        }
    }

    private function findProcessForProject(int $projectId, int $processId): WorkProcess
    {
        return WorkProcess::where('id', $processId)
            ->where(function ($query) use ($projectId) {
                $query->where('project_id', $projectId)->orWhereNull('project_id');
            })
            ->firstOrFail();
    }

    /**
     * 累加工序进度
     *
     * @param array $progresses [{process_id, completed_qty, percentage?}, ...]
     * @param array $previousProgresses 同一日志更新前的工程量，用于按差量累计
     */
    public function applyProcessProgress(ConstructionLog $log, array $progresses, array $previousProgresses = []): void
    {
        if (!$log->team_id) {
            throw new \RuntimeException('更新工序进度前必须选择施工团队');
        }

        $currentByProcess = [];
        foreach ($progresses as $row) {
            $processId = (int) ($row['process_id'] ?? 0);
            if ($processId > 0) {
                $currentByProcess[$processId] = (float) ($row['completed_qty'] ?? 0);
            }
        }

        $previousByProcess = [];
        foreach ($previousProgresses as $row) {
            $processId = (int) ($row['process_id'] ?? 0);
            if ($processId > 0) {
                $previousByProcess[$processId] = (float) ($row['completed_qty'] ?? 0);
            }
        }

        $processIds = array_unique(array_merge(array_keys($currentByProcess), array_keys($previousByProcess)));
        foreach ($processIds as $processId) {
            $qty = ($currentByProcess[$processId] ?? 0) - ($previousByProcess[$processId] ?? 0);
            if ($qty === 0.0) {
                continue;
            }

            $progress = WorkProcessProgress::where('process_id', $processId)
                ->where('project_id', $log->project_id)
                ->where('team_id', $log->team_id)
                ->lockForUpdate()
                ->firstOrFail();

            $completed = max(0.0, (float) $progress->completed_quantity + $qty);
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
        }
    }

    /**
     * 单工序手动更新 (供 Controller / 整改流程调用)
     */
    public function updateProgress(int $logId, int $processId, float $completedQty): WorkProcessProgress
    {
        return DB::transaction(function () use ($logId, $processId, $completedQty) {
            $log = ConstructionLog::lockForUpdate()->findOrFail($logId);
            if (!$log->team_id) {
                throw new \RuntimeException('更新工序进度前必须选择施工团队');
            }
            $progress = WorkProcessProgress::where('process_id', $processId)
                ->where('project_id', $log->project_id)
                ->where('team_id', $log->team_id)
                ->lockForUpdate()
                ->firstOrFail();

            $completed = max(0.0, (float) $progress->completed_quantity + $completedQty);
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
