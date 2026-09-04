<?php

namespace App\Services;

use App\Models\ProjectCommencementOrder;
use App\Models\RectificationDailyRequired;
use App\Models\ConstructionTeam;
use App\Models\WorkProcess;
use App\Models\WorkProcessProgress;
use App\Models\ApprovalRecord;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * V0.4.3 开工单服务
 *
 * 关键流程:
 *  - createOrder: 创建开工单 (生成 code) + 按起止日期批量生成 rectification_daily_required
 *  - approve:  审批 (状态机)
 *  - startWork: 开工 → 触发 work_process_progress 创建 (在 Observer 里)
 *  - complete:  完工
 */
class CommencementOrderService
{
    /**
     * 生成开工单编号 COMM-yyyy-NNNN
     */
    public function generateCode(): string
    {
        $year   = now()->format('Y');
        $prefix = "COMM-{$year}-";
        $next = NumberSequenceService::next(
            "commencement-order:{$year}",
            fn () => (int) ProjectCommencementOrder::where('code', 'like', $prefix . '%')
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(code FROM 'COMM-[0-9]{4}-([0-9]+)') AS INTEGER)), 0) as seq")
                ->value('seq')
        );

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 创建开工单 + 日报需求单 + 工序进度
     */
    public function createOrder(int $projectId, array $data, int $userId): ProjectCommencementOrder
    {
        return DB::transaction(function () use ($projectId, $data, $userId) {
            $this->validateTeam($projectId, $data['team_id'] ?? null);

            $order = ProjectCommencementOrder::create([
                'project_id'           => $projectId,
                'team_id'              => $data['team_id']              ?? null,
                'code'                 => $this->generateCode(),
                'commencement_date'    => $data['commencement_date']    ?? $data['planned_start_date'] ?? now()->toDateString(),
                'planned_end_date'     => $data['planned_end_date']     ?? null,
                'work_content'         => $data['work_content']         ?? $data['work_scope']         ?? '',
                'work_location'        => $data['work_location']        ?? null,
                'quality_requirements' => $data['quality_requirements'] ?? $data['work_standard'] ?? null,
                'safety_requirements'  => $data['safety_requirements']  ?? null,
                'on_site_contacts'     => $data['on_site_contacts']     ?? null,
                'attachments'          => $data['attachments']          ?? null,
                'remark'               => $data['remark']               ?? null,
                'status'               => ProjectCommencementOrder::STATUS_DRAFT,
                'created_by'           => $userId,
            ]);

            // 1) 工序 (可选)
            if (!empty($data['processes']) && is_array($data['processes'])) {
                $this->syncProcesses($order, $data['processes']);
            }

            // 2) 强制日报需求 (开工单创建即生成 — 大哥拍板)
            $this->generateDailyRequired($order);

            $this->syncApproval($order, $userId);
            $order->update(['status' => ProjectCommencementOrder::STATUS_PENDING_APPROVAL]);

            return $order->fresh(['team', 'project']);
        });
    }

    /**
     * 更新草稿开工单
     */
    public function updateOrder(int $orderId, array $data): ProjectCommencementOrder
    {
        return DB::transaction(function () use ($orderId, $data) {
            $order = ProjectCommencementOrder::lockForUpdate()->findOrFail($orderId);
            if ($order->status !== ProjectCommencementOrder::STATUS_DRAFT) {
                throw new \RuntimeException('只有草稿状态可编辑');
            }

            $this->validateTeam($order->project_id, $data['team_id'] ?? $order->team_id);
            $updateData = [];
            foreach ([
                'team_id', 'planned_end_date', 'work_location',
                'quality_requirements', 'safety_requirements',
                'on_site_contacts', 'attachments', 'remark',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }
            if (array_key_exists('commencement_date', $data) || array_key_exists('planned_start_date', $data)) {
                $updateData['commencement_date'] = $data['commencement_date'] ?? $data['planned_start_date'];
            }
            if (array_key_exists('work_content', $data) || array_key_exists('work_scope', $data)) {
                $updateData['work_content'] = $data['work_content'] ?? $data['work_scope'];
            }
            if (!array_key_exists('quality_requirements', $data) && array_key_exists('work_standard', $data)) {
                $updateData['quality_requirements'] = $data['work_standard'];
            }
            $order->update($updateData);

            if (array_key_exists('processes', $data)) {
                $this->syncProcesses($order, $data['processes'] ?? []);
            }
            if (array_key_exists('commencement_date', $data)
                || array_key_exists('planned_start_date', $data)
                || array_key_exists('planned_end_date', $data)) {
                RectificationDailyRequired::where('commencement_order_id', $order->id)
                    ->where('status', RectificationDailyRequired::STATUS_PENDING)
                    ->delete();
                $this->generateDailyRequired($order->fresh());
            }

            return $order->fresh(['team', 'project']);
        });
    }

    /**
     * 同步工序 + 同步工序进度
     */
    public function syncProcesses(ProjectCommencementOrder $order, array $processes): void
    {
        DB::transaction(function () use ($order, $processes) {
            if ($order->status !== ProjectCommencementOrder::STATUS_DRAFT) {
                throw new \RuntimeException('只有草稿状态可同步工序');
            }
            if (!$order->team_id && $processes !== []) {
                throw new \RuntimeException('配置工序前必须选择施工团队');
            }

            $sort = 0;
            foreach ($processes as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    throw new \InvalidArgumentException('工序名称不能为空');
                }
                $process = WorkProcess::updateOrCreate([
                    'project_id' => $order->project_id,
                    'name' => $name,
                ], [
                    'sequence' => $row['sequence'] ?? $sort++,
                    'description' => $row['description'] ?? null,
                    'estimated_hours' => $row['estimated_hours'] ?? null,
                    'status' => $row['status'] ?? 'active',
                ]);

                $progress = WorkProcessProgress::firstOrNew([
                    'process_id' => $process->id,
                    'project_id' => $order->project_id,
                    'team_id' => $order->team_id,
                ]);
                $progress->planned_quantity = $row['planned_quantity'] ?? $progress->planned_quantity;
                $progress->unit = $row['unit'] ?? $progress->unit;
                if (!$progress->exists) {
                    $progress->completed_quantity = 0;
                    $progress->progress_percentage = 0;
                    $progress->status = WorkProcessProgress::STATUS_PENDING;
                }
                $progress->save();
            }
        });
    }

    /**
     * 按起止日期每天生成 rectification_daily_required
     */
    private function generateDailyRequired(ProjectCommencementOrder $order): void
    {
        $start = \Carbon\Carbon::parse($order->commencement_date)->startOfDay();
        $end   = \Carbon\Carbon::parse($order->planned_end_date)->startOfDay();

        if ($end->lt($start)) {
            throw new \RuntimeException('计划结束日期不能早于开始日期');
        }

        $rows = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $rows[] = [
                'project_id'            => $order->project_id,
                'commencement_order_id' => $order->id,
                'work_date'             => $d->toDateString(),
                'status'                => RectificationDailyRequired::STATUS_PENDING,
                'is_required'           => true,
                'created_at'            => now(),
                'updated_at'            => now(),
            ];
        }

        // 防止重复生成 (幂等) — 唯一键 (project_id, work_date) 表上存在
        RectificationDailyRequired::upsert(
            $rows,
            ['project_id', 'work_date'],          // 唯一键
            ['updated_at']                         // 仅更新时间
        );
    }

    /**
     * 提交审批 (draft → pending_approval)
     */
    public function submitForApproval(int $orderId): ProjectCommencementOrder
    {
        return DB::transaction(function () use ($orderId) {
            $order = ProjectCommencementOrder::lockForUpdate()->findOrFail($orderId);
            if ($order->status !== ProjectCommencementOrder::STATUS_DRAFT) {
                throw new \RuntimeException('只有草稿状态可提交审批');
            }
            $order->update(['status' => ProjectCommencementOrder::STATUS_PENDING_APPROVAL]);

            $applicantId = (int) Auth::id();
            if ($applicantId < 1) {
                throw new \RuntimeException('无法识别当前申请人');
            }
            $this->syncApproval($order, $applicantId);

            return $order->fresh();
        });
    }

    /**
     * 审批通过
     */
    public function approve(int $orderId, int $approverId, ?string $comment = null): ProjectCommencementOrder
    {
        return DB::transaction(function () use ($orderId, $approverId, $comment) {
            $approval = ApprovalRecord::where('type', 'project')
                ->where('sub_type', 'commencement')
                ->whereJsonContains('payload->order_id', $orderId)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->lockForUpdate()
                ->first();
            if (!$approval) {
                throw new \RuntimeException('未找到有效的开工审批中心记录');
            }
            $order = ProjectCommencementOrder::lockForUpdate()->findOrFail($orderId);
            if ($order->status !== ProjectCommencementOrder::STATUS_PENDING_APPROVAL) {
                throw new \RuntimeException('只有待审批状态可审批');
            }
            $operator = User::findOrFail($approverId);
            $result = app(ApprovalFlowService::class)->advanceFlow($approval, $operator, $comment ?? '审批通过');
            $approval->forceFill([
                'status' => $result['status'],
                'current_approver_id' => $result['current_approver_id'],
                'flow' => $result['flow'],
                'comment' => $comment ?? '审批通过',
            ])->save();
            if ($result['status'] === ApprovalRecord::STATUS_APPROVED) {
                $order->update([
                    'status' => ProjectCommencementOrder::STATUS_APPROVED,
                    'approved_by' => $approverId,
                    'approved_at' => now(),
                ]);
            }

            return $order->fresh();
        });
    }

    /**
     * 审批驳回
     */
    public function reject(int $orderId, int $approverId, string $reason): ProjectCommencementOrder
    {
        return DB::transaction(function () use ($orderId, $approverId, $reason) {
            if (trim($reason) === '') {
                throw new \InvalidArgumentException('驳回原因不能为空');
            }
            $approval = ApprovalRecord::where('type', 'project')
                ->where('sub_type', 'commencement')
                ->whereJsonContains('payload->order_id', $orderId)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->lockForUpdate()
                ->first();
            if (!$approval) {
                throw new \RuntimeException('未找到有效的开工审批中心记录');
            }
            $order = ProjectCommencementOrder::lockForUpdate()->findOrFail($orderId);
            if ($order->status !== ProjectCommencementOrder::STATUS_PENDING_APPROVAL) {
                throw new \RuntimeException('只有待审批状态可驳回');
            }
            $operator = User::findOrFail($approverId);
            $result = app(ApprovalFlowService::class)->rejectFlow($approval, $operator, $reason);
            $approval->forceFill([
                'status' => $result['status'],
                'current_approver_id' => $result['current_approver_id'],
                'flow' => $result['flow'],
                'comment' => $reason,
            ])->save();
            $order->update([
                'status'          => ProjectCommencementOrder::STATUS_REJECTED,
            ]);

            return $order->fresh();
        });
    }

    /**
     * 开工 (approved → in_progress)
     * 触发工序进度激活 (Observer 同步)
     *
     * @param array $data 实际开工日期等可选数据
     */
    public function startWork(int $orderId, array $data = []): ProjectCommencementOrder
    {
        return DB::transaction(function () use ($orderId, $data) {
            $order = ProjectCommencementOrder::lockForUpdate()->findOrFail($orderId);
            if ($order->status !== ProjectCommencementOrder::STATUS_APPROVED) {
                throw new \RuntimeException('只有已批准状态可开工');
            }
            $order->update([
                'status' => ProjectCommencementOrder::STATUS_IN_PROGRESS,
            ]);
            WorkProcessProgress::where('project_id', $order->project_id)
                ->where('team_id', $order->team_id)
                ->where('status', WorkProcessProgress::STATUS_PENDING)
                ->update(['status' => WorkProcessProgress::STATUS_IN_PROGRESS]);
            return $order->fresh();
        });
    }

    /**
     * 完工 (in_progress → completed)
     */
    public function complete(int $orderId, array $data = []): ProjectCommencementOrder
    {
        return DB::transaction(function () use ($orderId, $data) {
            $order = ProjectCommencementOrder::lockForUpdate()->findOrFail($orderId);
            if ($order->status !== ProjectCommencementOrder::STATUS_IN_PROGRESS) {
                throw new \RuntimeException('只有施工中状态可完工');
            }
            $order->update([
                'status' => ProjectCommencementOrder::STATUS_COMPLETED,
                'actual_end_date' => $data['actual_end_date'] ?? now()->toDateString(),
            ]);
            return $order->fresh();
        });
    }

    /**
     * 取消开工单
     */
    public function cancel(int $orderId, ?string $reason = null): ProjectCommencementOrder
    {
        return DB::transaction(function () use ($orderId, $reason) {
            $order = ProjectCommencementOrder::findOrFail($orderId);
            if (in_array($order->status, [
                ProjectCommencementOrder::STATUS_COMPLETED,
                ProjectCommencementOrder::STATUS_CANCELLED,
            ], true)) {
                throw new \RuntimeException('已完成/已取消的开工单不可再取消');
            }
            $order->update([
                'status' => ProjectCommencementOrder::STATUS_CANCELLED,
            ]);
            // 关闭日报需求
            RectificationDailyRequired::where('commencement_order_id', $order->id)
                ->where('status', RectificationDailyRequired::STATUS_PENDING)
                ->update([
                    'status'     => RectificationDailyRequired::STATUS_EXCUSED,
                    'updated_at' => now(),
                ]);
            return $order->fresh();
        });
    }

    public function syncApprovalBusinessState(
        ApprovalRecord $approval,
        User $operator,
        string $status
    ): ProjectCommencementOrder {
        if ($approval->type !== 'project' || $approval->sub_type !== 'commencement') {
            throw new \InvalidArgumentException('审批记录不是开工审批');
        }
        $orderId = (int) (($approval->payload ?? [])['order_id'] ?? 0);
        if ($orderId < 1) {
            throw new \RuntimeException('开工审批缺少业务单编号');
        }

        $order = ProjectCommencementOrder::lockForUpdate()->findOrFail($orderId);
        if ($order->status !== ProjectCommencementOrder::STATUS_PENDING_APPROVAL) {
            throw new \RuntimeException('开工单状态已变更，审批结果未同步');
        }
        if ($status === ApprovalRecord::STATUS_APPROVED) {
            $order->update([
                'status' => ProjectCommencementOrder::STATUS_APPROVED,
                'approved_by' => $operator->id,
                'approved_at' => now(),
            ]);
        } elseif ($status === ApprovalRecord::STATUS_REJECTED) {
            $order->update(['status' => ProjectCommencementOrder::STATUS_REJECTED]);
        } else {
            throw new \InvalidArgumentException('不支持的开工审批结果');
        }

        return $order->fresh();
    }

    /**
     * V1.2.5: 同步开工单审批到审批中心 (project/commencement)
     */
    private function syncApproval(ProjectCommencementOrder $order, int $applicantId): void
    {
        try {
            $applicant = User::find($applicantId);
            if (!$applicant) {
                throw new \RuntimeException('当前申请人不存在');
            }
            $existing = ApprovalRecord::where('type', 'project')
                ->where('sub_type', 'commencement')
                ->whereJsonContains('payload->order_id', $order->id)
                ->whereIn('status', [ApprovalRecord::STATUS_PENDING, ApprovalRecord::STATUS_APPROVED])
                ->first();
            if ($existing?->status === ApprovalRecord::STATUS_PENDING) {
                return;
            }
            if ($existing) {
                throw new \RuntimeException('该开工单已经完成审批，不能重复提交');
            }
            $code = \App\Services\ApprovalNumberService::next('PRJ');
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate('commencement', 'project');
            if (!$template) {
                throw new \RuntimeException('未找到开工对应的启用项目审批流程');
            }
            $flowData = $flowService->initFlow($template, $applicant, '提交开工令审批');
            ApprovalRecord::create([
                'code' => $code,
                'type' => 'project',
                'sub_type' => 'commencement',
                'title' => '[开工令] ' . ($order->code ?? '#' . $order->id) . ' 开工审批',
                'priority' => 'high',
                'status' => ApprovalRecord::STATUS_PENDING,
                'start_date' => $order->commencement_date,
                'end_date' => $order->planned_end_date,
                'applicant_id' => $applicant->id,
                'current_approver_id' => $flowData['current_approver_id'],
                'payload' => [
                    'order_id' => $order->id,
                    'code' => $order->code,
                    'project_id' => $order->project_id,
                    'commencement_date' => $order->commencement_date,
                    'planned_end_date' => $order->planned_end_date,
                    'work_content' => $order->work_content,
                    '_approval_flow' => $flowData['definition'],
                ],
                'flow' => $flowData['flow'],
            ]);
        } catch (\Throwable $e) {
            Log::error('CommencementOrderService::syncApproval failed', ['msg' => $e->getMessage(), 'order_id' => $order->id]);
            throw $e;
        }
    }

    private function validateTeam(int $projectId, mixed $teamId): void
    {
        if ($teamId === null || $teamId === '') {
            return;
        }
        ConstructionTeam::where('project_id', $projectId)->findOrFail((int) $teamId);
    }
}
