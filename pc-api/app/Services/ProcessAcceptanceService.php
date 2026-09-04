<?php

namespace App\Services;

use App\Models\ApprovalRecord;
use App\Models\ProcessInspection;
use App\Models\ProcessInstance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProcessAcceptanceService
{
    public function submit(ProcessInstance $process, User $operator, ?int $inspectionId, ?string $comment = null): ApprovalRecord
    {
        return DB::transaction(function () use ($process, $operator, $inspectionId, $comment) {
            $process = ProcessInstance::lockForUpdate()->findOrFail($process->id);
            $approval = $this->pendingApproval($process->id, true);
            if ($approval) {
                return $approval;
            }

            return $this->createApproval($process, $operator, $inspectionId, $comment);
        });
    }

    public function approve(ProcessInstance $process, User $operator, ?int $inspectionId, string $comment): ProcessInstance
    {
        return DB::transaction(function () use ($process, $operator, $inspectionId, $comment) {
            $approval = $this->pendingApproval($process->id, true);
            if (!$approval) {
                throw new \RuntimeException('未找到待处理的工序验收审批，请先提交验收申请');
            }
            $process = ProcessInstance::lockForUpdate()->findOrFail($process->id);
            $this->assertApprovable($process, $approval, $operator);

            $result = app(ApprovalFlowService::class)->advanceFlow($approval, $operator, $comment ?: '验收通过');
            $approval->forceFill([
                'status' => $result['status'],
                'current_approver_id' => $result['current_approver_id'],
                'flow' => $result['flow'],
                'comment' => $comment ?: '验收通过',
            ])->save();

            if ($result['status'] === ApprovalRecord::STATUS_APPROVED) {
                $this->syncBusinessState($approval, $operator, ApprovalRecord::STATUS_APPROVED);
            }

            return $process->fresh();
        });
    }

    public function reject(ProcessInstance $process, User $operator, ?int $inspectionId, string $reason): ProcessInstance
    {
        return DB::transaction(function () use ($process, $operator, $inspectionId, $reason) {
            $approval = $this->pendingApproval($process->id, true);
            if (!$approval) {
                throw new \RuntimeException('未找到待处理的工序验收审批，请先提交验收申请');
            }
            $process = ProcessInstance::lockForUpdate()->findOrFail($process->id);
            $this->assertApprovable($process, $approval, $operator);

            $result = app(ApprovalFlowService::class)->rejectFlow($approval, $operator, $reason);
            $approval->forceFill([
                'status' => $result['status'],
                'current_approver_id' => $result['current_approver_id'],
                'flow' => $result['flow'],
                'comment' => $reason,
            ])->save();
            $this->syncBusinessState($approval, $operator, ApprovalRecord::STATUS_REJECTED);

            return $process->fresh();
        });
    }

    public function syncBusinessState(ApprovalRecord $approval, User $operator, string $status): ProcessInstance
    {
        if ($approval->type !== 'project' || $approval->sub_type !== 'process_acceptance') {
            throw new \InvalidArgumentException('审批记录不是工序验收审批');
        }
        $processId = (int) (($approval->payload ?? [])['process_id'] ?? 0);
        if ($processId < 1) {
            throw new \RuntimeException('工序验收审批缺少工序编号');
        }

        $process = ProcessInstance::lockForUpdate()->findOrFail($processId);
        if ($status === ApprovalRecord::STATUS_APPROVED) {
            if ($process->status !== ProcessInstance::STATUS_COMPLETED) {
                throw new \RuntimeException('工序状态已变更，验收结果未同步');
            }
            $process->forceFill([
                'status' => ProcessInstance::STATUS_ACCEPTED,
                'progress' => 100,
                'accepted_at' => now(),
                'accepted_by' => $operator->id,
                'actual_end_date' => $process->actual_end_date ?? today(),
            ])->save();
            app(ProjectStageService::class)->onAllProcessInspectionsPassed($process->project_id, $operator->id);
        } elseif ($status === ApprovalRecord::STATUS_REJECTED) {
            if ($process->status === ProcessInstance::STATUS_ACCEPTED) {
                throw new \RuntimeException('已验收工序不能驳回');
            }
            $process->update(['status' => ProcessInstance::STATUS_REJECTED]);
        } else {
            throw new \InvalidArgumentException('不支持的工序验收结果');
        }

        return $process->fresh();
    }

    private function createApproval(
        ProcessInstance $process,
        User $operator,
        ?int $inspectionId,
        ?string $comment
    ): ApprovalRecord
    {
        $process = ProcessInstance::lockForUpdate()->with('project')->findOrFail($process->id);
        $existing = $this->pendingApproval($process->id);
        if ($existing) {
            return $existing;
        }
        if ($process->status !== ProcessInstance::STATUS_COMPLETED) {
            throw new \RuntimeException('只有已完工工序可以验收');
        }
        $inspection = $inspectionId
            ? ProcessInspection::where('process_instance_id', $process->id)->findOrFail($inspectionId)
            : ProcessInspection::where('process_instance_id', $process->id)
                ->latest('inspection_date')->latest('id')->first();
        if (!$inspection) {
            throw new \RuntimeException('处理验收前必须先创建验收记录');
        }
        if ($inspection->result !== ProcessInspection::RESULT_PASS) {
            throw new \RuntimeException('验收通过前，最新验收记录必须为合格');
        }
        $flowService = app(ApprovalFlowService::class);
        $template = $flowService->resolveTemplate('process_acceptance', 'project');
        if (!$template) {
            throw new \RuntimeException('未找到工序验收对应的启用项目审批流程');
        }
        $flowData = $flowService->initFlow($template, $operator, $comment ?: '提交工序验收审批');

        return ApprovalRecord::create([
            'code' => ApprovalNumberService::next('PRJ'),
            'type' => 'project',
            'sub_type' => 'process_acceptance',
            'title' => "工序验收 - {$process->name} (" . ($process->project?->name ?? '未知项目') . ')',
            'priority' => 'normal',
            'status' => ApprovalRecord::STATUS_PENDING,
            'applicant_id' => $operator->id,
            'current_approver_id' => $flowData['current_approver_id'],
            'payload' => [
                'process_id' => $process->id,
                'project_id' => $process->project_id,
                'inspection_id' => $inspection->id,
                'foreman_id' => $process->foreman_id,
                '_approval_flow' => $flowData['definition'],
            ],
            'flow' => $flowData['flow'],
        ]);
    }

    private function pendingApproval(int $processId, bool $lock = false): ?ApprovalRecord
    {
        $query = ApprovalRecord::where('type', 'project')
            ->where('sub_type', 'process_acceptance')
            ->whereJsonContains('payload->process_id', $processId)
            ->where('status', ApprovalRecord::STATUS_PENDING);
        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function assertApprovable(ProcessInstance $process, ApprovalRecord $approval, User $operator): void
    {
        if ($process->status !== ProcessInstance::STATUS_COMPLETED) {
            throw new \RuntimeException('只有已完工工序可以验收');
        }
        if ((int) $approval->applicant_id === (int) $operator->id) {
            throw new \RuntimeException('申请人不能审批自己的验收申请');
        }
    }
}
