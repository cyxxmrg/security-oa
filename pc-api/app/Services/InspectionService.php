<?php

namespace App\Services;

use App\Models\InspectionPlan;
use App\Models\InspectionTask;
use App\Models\InspectionRecord;
use App\Models\InspectionIssue;
use App\Models\InspectionSchedule;
use App\Models\MaintenanceContract;
use App\Models\WorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * V0.7 巡检计划服务
 *
 * 核心能力：
 * - 巡检计划 CRUD + 状态机
 * - 任务自动生成 (按排程)
 * - 现场打卡
 * - 异常 → 自动转工单
 * - 排程 cron 触发
 *
 * 状态机：
 *   Plan:   active ⇄ paused → expired/cancelled
 *   Task:   pending → in_progress → completed
 *                      ↘ overdue (超 scheduled_at 24h)
 *                      ↘ skipped/cancelled
 *   Record: checked_in → checked_out
 *   Issue:  open → work_order_created → resolved
 *                          ↘ ignored
 */
class InspectionService
{
    // ========== 巡检计划 ==========

    /**
     * 创建巡检计划
     */
    public function createPlan(array $data, ?User $user = null): InspectionPlan
    {
        return DB::transaction(function () use ($data, $user) {
            // V1.2.10: contract_id 可选, 没合同时直接用传入的 customer_id
            if (!empty($data['contract_id'])) {
                $contract = MaintenanceContract::findOrFail($data['contract_id']);
                $data['customer_id'] = $data['customer_id'] ?? $contract->customer_id;
            }
            if (empty($data['customer_id'])) {
                throw new \InvalidArgumentException('客户ID不能为空');
            }
            $data['created_by']  = $user?->id ?? Auth::id();

            $plan = InspectionPlan::create($data);

            // 初始化排程器
            InspectionSchedule::create([
                'plan_id'             => $plan->id,
                'last_generated_date' => null,
                'next_scheduled_date' => $data['start_date'] ?? now()->toDateString(),
            ]);

            // 立即生成首批任务 (按 ahead_generate_days)
            $this->generateInitialTasks($plan);

            return $plan->fresh();
        });
    }

    /**
     * 暂停 / 启用
     */
    public function toggleStatus(int $planId): InspectionPlan
    {
        return DB::transaction(function () use ($planId) {
            $plan = InspectionPlan::lockForUpdate()->findOrFail($planId);
            $newStatus = $plan->status === InspectionPlan::STATUS_ACTIVE
                ? InspectionPlan::STATUS_PAUSED
                : InspectionPlan::STATUS_ACTIVE;
            $plan->status = $newStatus;
            $plan->save();
            return $plan->fresh();
        });
    }

    /**
     * 取消计划
     */
    public function cancelPlan(int $planId, ?string $reason = null): InspectionPlan
    {
        return DB::transaction(function () use ($planId, $reason) {
            $plan = InspectionPlan::lockForUpdate()->findOrFail($planId);
            if ($plan->status === InspectionPlan::STATUS_CANCELLED) {
                throw new RuntimeException('计划已取消');
            }
            $plan->status = InspectionPlan::STATUS_CANCELLED;
            $plan->save();
            // 级联取消未执行的任务
            InspectionTask::where('plan_id', $plan->id)
                ->whereIn('status', [InspectionTask::STATUS_PENDING, InspectionTask::STATUS_IN_PROGRESS])
                ->update(['status' => InspectionTask::STATUS_CANCELLED]);
            return $plan->fresh();
        });
    }

    // ========== 任务生成 ==========

    /**
     * 初始生成 (创建计划时调用, 按 ahead_generate_days 提前批量生成)
     */
    public function generateInitialTasks(InspectionPlan $plan): int
    {
        $days = $plan->ahead_generate_days ?? 30;
        $endDate = now()->addDays($days);
        $count = 0;
        $date = new \DateTime($plan->start_date->toDateString());

        while ($date <= $endDate) {
            if ($plan->end_date && $date > $plan->end_date) break;
            $this->createTaskForDate($plan, $date);
            $count++;
            $nextDate = $plan->calculateNextDate($date);
            if (!$nextDate) break;
            $date = $nextDate;
        }

        // 更新排程器
        InspectionSchedule::updateOrCreate(
            ['plan_id' => $plan->id],
            [
                'last_generated_date' => $endDate->toDateString(),
                'next_scheduled_date' => $endDate->modify('+1 day')->toDateString(),
                'generated_count'     => $count,
                'last_run_at'         => now(),
            ]
        );

        $plan->increment('total_generated', $count);
        return $count;
    }

    /**
     * 增量生成 (cron 调用, 按 next_scheduled_date 推进)
     */
    public function generateIncremental(?int $planId = null): array
    {
        $results = ['generated' => 0, 'plans' => 0];
        $query = InspectionPlan::where('status', InspectionPlan::STATUS_ACTIVE);
        if ($planId) $query->where('id', $planId);

        $plans = $query->with('schedule')->get();
        foreach ($plans as $plan) {
            $schedule = $plan->schedule()->first();
            if (!$schedule) continue;
            $now = now()->toDateString();
            if ($schedule->next_scheduled_date && $schedule->next_scheduled_date->toDateString() > $now) continue;

            $count = $this->generateInitialTasks($plan);
            $results['generated'] += $count;
            $results['plans']++;
        }
        return $results;
    }

    /**
     * 单日创建任务
     */
    protected function createTaskForDate(InspectionPlan $plan, \DateTimeInterface $date): InspectionTask
    {
        $scheduledHour = 9;
        if ($plan->cycle_weekday !== null && in_array($plan->frequency, ['weekly', 'biweekly'])) {
            // weekly/biweekly 按 weekday
        }
        $scheduledAt = (new \DateTime($date->format('Y-m-d')))->setTime($scheduledHour, 0, 0);

        $assignedTo = null;
        if ($plan->assigned_to) {
            $assignees = json_decode($plan->assigned_to, true);
            $assignedTo = is_array($assignees) ? ($assignees[array_rand($assignees)] ?? null) : $plan->assigned_to;
        }

        return InspectionTask::create([
            'plan_id'        => $plan->id,
            'contract_id'    => $plan->contract_id,
            'customer_id'    => $plan->customer_id,
            'scheduled_date' => $date->format('Y-m-d'),
            'scheduled_hour' => $scheduledHour,
            'scheduled_at'   => $scheduledAt,
            'assigned_to'    => $assignedTo,
            'status'         => InspectionTask::STATUS_PENDING,
        ]);
    }

    // ========== 现场打卡 ==========

    /**
     * 工程师到达现场打卡
     */
    public function checkin(int $taskId, array $data, ?User $user = null): InspectionRecord
    {
        return DB::transaction(function () use ($taskId, $data, $user) {
            $task = InspectionTask::lockForUpdate()->findOrFail($taskId);
            if (!in_array($task->status, [InspectionTask::STATUS_PENDING, InspectionTask::STATUS_IN_PROGRESS, InspectionTask::STATUS_OVERDUE])) {
                throw new RuntimeException("任务 [{$task->task_no}] 当前状态 [{$task->status}] 不能打卡");
            }

            // 切换任务为执行中
            if ($task->status !== InspectionTask::STATUS_IN_PROGRESS) {
                $task->status = InspectionTask::STATUS_IN_PROGRESS;
                $task->started_at = now();
                $task->save();
            }

            $record = InspectionRecord::create([
                'task_id'           => $task->id,
                'plan_id'           => $task->plan_id,
                'user_id'           => $user?->id ?? Auth::id(),
                'checkin_at'        => $data['checkin_at'] ?? now(),
                'checkin_location'  => $data['checkin_location'] ?? null,
                'checkin_lat'       => $data['checkin_lat'] ?? null,
                'checkin_lng'       => $data['checkin_lng'] ?? null,
                'checkin_photos'    => $data['checkin_photos'] ?? null,
                'status'            => InspectionRecord::STATUS_CHECKED_IN,
            ]);

            return $record->fresh();
        });
    }

    /**
     * 现场完成 (提交检查项答案 + 异常清单)
     */
    public function checkout(int $recordId, array $data, ?User $user = null): InspectionRecord
    {
        return DB::transaction(function () use ($recordId, $data, $user) {
            $record = InspectionRecord::lockForUpdate()->findOrFail($recordId);
            if ($record->status !== InspectionRecord::STATUS_CHECKED_IN) {
                throw new RuntimeException('记录已提交，不能重复 checkout');
            }

            // 计算正常 / 异常项
            $answers = $data['checklist_answers'] ?? [];
            $normal = 0;
            $abnormal = 0;
            foreach ($answers as $key => $value) {
                if (is_string($value) && str_contains(strtolower($value), '异常')) {
                    $abnormal++;
                } else {
                    $normal++;
                }
            }

            $record->update([
                'checkout_at'         => $data['checkout_at'] ?? now(),
                'checkout_location'   => $data['checkout_location'] ?? null,
                'checkout_lat'        => $data['checkout_lat'] ?? null,
                'checkout_lng'        => $data['checkout_lng'] ?? null,
                'checklist_answers'   => $answers,
                'normal_count'        => $normal,
                'abnormal_count'      => $abnormal,
                'summary'             => $data['summary'] ?? null,
                'rating'              => $data['rating'] ?? null,
                'status'              => InspectionRecord::STATUS_CHECKED_OUT,
            ]);

            // 关联异常清单
            $issues = $data['issues'] ?? [];
            $issueModels = [];
            foreach ($issues as $issue) {
                $im = InspectionIssue::create([
                    'record_id'          => $record->id,
                    'task_id'            => $record->task_id,
                    'plan_id'            => $record->plan_id,
                    'contract_id'        => $record->task->contract_id,
                    'customer_id'        => $record->task->customer_id,
                    'inventory_item_id'  => $issue['inventory_item_id'] ?? null,
                    'equipment_name'     => $issue['equipment_name'] ?? '未知设备',
                    'equipment_location' => $issue['equipment_location'] ?? null,
                    'issue_type'         => $issue['issue_type'] ?? 'hardware',
                    'severity'           => $issue['severity'] ?? 'medium',
                    'title'              => $issue['title'] ?? '巡检发现',
                    'description'        => $issue['description'] ?? '',
                    'photos'             => $issue['photos'] ?? null,
                    'status'             => InspectionIssue::STATUS_OPEN,
                ]);
                $issueModels[] = $im;
            }

            // 完成任务
            $task = $record->task;
            $task->update([
                'status'        => InspectionTask::STATUS_COMPLETED,
                'completed_at'  => now(),
                'duration_minutes' => $record->checkout_at && $record->checkin_at
                    ? (int) $record->checkin_at->diffInMinutes($record->checkout_at)
                    : null,
                'equipment_count' => $normal + $abnormal,
                'issue_count'     => count($issueModels),
            ]);

            // 更新计划统计
            $plan = $record->plan;
            $plan->increment('total_completed');
            if (count($issueModels) > 0) {
                $plan->increment('total_issues', count($issueModels));
            }

            // 自动转工单 (高严重度异常自动建)
            foreach ($issueModels as $im) {
                if (in_array($im->severity, ['high', 'critical'])) {
                    $this->convertIssueToWorkOrder($im);
                }
            }

            return $record->fresh()->load('issues');
        });
    }

    // ========== 异常 → 工单 ==========

    /**
     * 异常转工单
     */
    public function convertIssueToWorkOrder(InspectionIssue $issue): WorkOrder
    {
        return DB::transaction(function () use ($issue) {
            $task = $issue->task;
            $plan = $issue->plan;

            $priority = match ($issue->severity) {
                'critical' => 'urgent',
                'high'     => 'high',
                'medium'   => 'medium',
                'low'      => 'low',
                default    => 'medium',
            };

            $today = date('Ymd');
            $count = WorkOrder::whereDate('created_at', today())->count() + 1;
            $code = 'WO-' . $today . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $wo = WorkOrder::create([
                'code'               => $code,
                'customer_id'        => $issue->customer_id,
                'project_id'         => null,
                'equipment_id'       => $issue->inventory_item_id,
                'contact_name'       => ($task->customer && $task->customer->name) ? $task->customer->name : '客户联系人',
                'contact_phone'      => ($task->customer && $task->customer->phone) ? $task->customer->phone : '13800000000',
                'address'            => $issue->equipment_location ?: (($task->customer && $task->customer->address) ? $task->customer->address : null),
                'service_type'       => 'inspection',
                'priority'           => $priority,
                'fault_description'  => "[巡检异常 #{$issue->issue_no}] {$issue->title}\n\n{$issue->description}\n\n来源: 巡检任务 {$task->task_no} / 计划 {$plan->plan_no} / 合同 {$issue->contract->contract_no}",
                'equipment_brand'    => null,
                'equipment_model'    => null,
                'serial_no'          => null,
                'assigned_to'        => $task->assigned_to,
                'scheduled_at'       => $issue->severity === 'critical' ? now()->addHours(2) : now()->addDay(),
                'status'             => 'pending',
                'is_billable'        => false, // 维保合同内免费
                'service_fee'        => 0,
                'parts_cost'         => 0,
                'total_cost'         => 0,
                'result_notes'       => null,
                'created_by'         => Auth::id(),
            ]);

            $issue->update([
                'status'         => InspectionIssue::STATUS_WORK_ORDER_CREATED,
                'work_order_id'  => $wo->id,
            ]);

            return $wo->fresh();
        });
    }

    /**
     * 解决异常 (手动标记, 不走工单)
     */
    public function resolveIssue(int $issueId, string $resolution, ?User $user = null): InspectionIssue
    {
        return DB::transaction(function () use ($issueId, $resolution, $user) {
            $issue = InspectionIssue::lockForUpdate()->findOrFail($issueId);
            if ($issue->status === InspectionIssue::STATUS_RESOLVED) {
                throw new RuntimeException('异常已解决');
            }
            $issue->update([
                'status'      => InspectionIssue::STATUS_RESOLVED,
                'resolution'  => $resolution,
                'resolved_at' => now(),
                'resolved_by' => $user?->id ?? Auth::id(),
            ]);
            return $issue->fresh();
        });
    }

    /**
     * 忽略异常
     */
    public function ignoreIssue(int $issueId, string $reason): InspectionIssue
    {
        return DB::transaction(function () use ($issueId, $reason) {
            $issue = InspectionIssue::lockForUpdate()->findOrFail($issueId);
            $issue->update([
                'status'     => InspectionIssue::STATUS_IGNORED,
                'resolution' => '已忽略: ' . $reason,
                'resolved_at' => now(),
            ]);
            return $issue->fresh();
        });
    }

    // ========== 任务状态 ==========

    /**
     * 跳过任务
     */
    public function skipTask(int $taskId, ?string $reason = null): InspectionTask
    {
        return DB::transaction(function () use ($taskId, $reason) {
            $task = InspectionTask::lockForUpdate()->findOrFail($taskId);
            if (!in_array($task->status, [InspectionTask::STATUS_PENDING, InspectionTask::STATUS_OVERDUE])) {
                throw new RuntimeException("任务状态 [{$task->status}] 不能跳过");
            }
            $task->update([
                'status' => InspectionTask::STATUS_SKIPPED,
                'remark' => $reason,
            ]);
            return $task->fresh();
        });
    }

    /**
     * 标记逾期 (cron 调用)
     */
    public function markOverdueTasks(): int
    {
        $count = InspectionTask::whereIn('status', [InspectionTask::STATUS_PENDING])
            ->where('scheduled_at', '<', now()->subHours(24))
            ->whereNull('overdue_notified')
            ->update([
                'status'             => InspectionTask::STATUS_OVERDUE,
                'overdue_notified'   => true,
                'overdue_notified_at' => now(),
            ]);
        return $count;
    }

    // ========== 统计 ==========

    /**
     * 仪表盘统计
     */
    public function getStats(): array
    {
        $monthStart = now()->startOfMonth();
        return [
            'total_plans'        => InspectionPlan::count(),
            'active_plans'       => InspectionPlan::where('status', InspectionPlan::STATUS_ACTIVE)->count(),
            'monthly_tasks'      => InspectionTask::where('scheduled_date', '>=', $monthStart)->count(),
            'pending_tasks'      => InspectionTask::whereIn('status', [InspectionTask::STATUS_PENDING, InspectionTask::STATUS_IN_PROGRESS])->count(),
            'overdue_tasks'      => InspectionTask::where('status', InspectionTask::STATUS_OVERDUE)->count(),
            'completed_tasks'    => InspectionTask::where('status', InspectionTask::STATUS_COMPLETED)
                ->where('completed_at', '>=', $monthStart)->count(),
            'open_issues'        => InspectionIssue::whereIn('status', [InspectionIssue::STATUS_OPEN, InspectionIssue::STATUS_WORK_ORDER_CREATED])->count(),
            'monthly_issues'     => InspectionIssue::where('created_at', '>=', $monthStart)->count(),
            'auto_work_orders'   => InspectionIssue::where('status', InspectionIssue::STATUS_WORK_ORDER_CREATED)
                ->where('created_at', '>=', $monthStart)->count(),
        ];
    }
}
