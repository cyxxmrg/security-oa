<?php

namespace App\Services;

use App\Models\ApprovalRecord;
use App\Models\Project;
use App\Models\User;
use DomainException;

class ProjectApprovalBusinessService
{
    public const CREATABLE_SUB_TYPES = [
        'project_create',
        'project_stage',
        'project_close',
    ];

    public static function supports(string $subType): bool
    {
        return in_array($subType, self::CREATABLE_SUB_TYPES, true);
    }

    public function createProjectCreateApproval(Project $project, User $applicant): ApprovalRecord
    {
        $flowService = app(ApprovalFlowService::class);
        $template = $flowService->resolveTemplate('project_create', 'project');
        if (!$template) {
            throw new DomainException('未找到项目立项对应的启用流程模板，请先在审批流程引擎中配置');
        }

        $flowData = $flowService->initFlow($template, $applicant, '提交项目立项审批');

        return ApprovalRecord::create([
            'code' => ApprovalNumberService::next('PRJ'),
            'type' => 'project',
            'sub_type' => 'project_create',
            'title' => "[项目立项] {$project->name} ({$project->project_no})",
            'priority' => $project->priority === 'urgent' ? 'urgent' : 'normal',
            'status' => ApprovalRecord::STATUS_PENDING,
            'amount' => $project->total_budget,
            'start_date' => $project->start_date,
            'end_date' => $project->end_date,
            'applicant_id' => $applicant->id,
            'current_approver_id' => $flowData['current_approver_id'],
            'payload' => [
                'project_id' => $project->id,
                'project_no' => $project->project_no,
                'project_name' => $project->name,
                '_approval_flow' => $flowData['definition'],
            ],
            'flow' => $flowData['flow'],
        ]);
    }

    public function preparePayload(string $subType, array $payload, ?string $toStage): array
    {
        if (!self::supports($subType)) {
            throw new DomainException('该项目审批类型必须从对应业务页面提交');
        }

        $projectId = (int) ($payload['project_id'] ?? 0);
        if ($projectId < 1) {
            throw new DomainException('项目审批缺少项目编号');
        }

        $project = Project::query()->findOrFail($projectId);
        $currentStage = $this->stageValue($project);
        $hasPendingApproval = ApprovalRecord::query()
            ->where('type', 'project')
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->whereJsonContains('payload->project_id', $project->id)
            ->exists();
        if ($hasPendingApproval) {
            throw new DomainException('该项目已有待处理审批，不能重复提交');
        }

        if ($subType === 'project_create') {
            if ($project->status !== 'pending') {
                throw new DomainException('只有待立项项目可以提交立项审批');
            }
        } elseif ($subType === 'project_stage') {
            if ($project->status !== 'in_progress') {
                throw new DomainException('只有进行中的项目可以提交阶段审批');
            }
            $targetStage = $this->assertNextStage($currentStage, $toStage);
            if ($targetStage === 'closed') {
                throw new DomainException('项目结项必须提交结项审批');
            }
            return array_merge($payload, [
                'project_id' => $project->id,
                'from_stage' => $currentStage,
                'to_stage' => $targetStage,
            ]);
        } elseif ($subType === 'project_close') {
            if ($project->status !== 'in_progress' || $currentStage !== 'warranty') {
                throw new DomainException('只有质保阶段的进行中项目可以提交结项审批');
            }
            return array_merge($payload, [
                'project_id' => $project->id,
                'from_stage' => $currentStage,
                'to_stage' => 'closed',
            ]);
        }

        return array_merge($payload, ['project_id' => $project->id]);
    }

    public function sync(ApprovalRecord $approval, User $operator, string $status): void
    {
        if (!self::supports($approval->sub_type)) {
            throw new DomainException('审批记录不是项目业务审批');
        }

        $payload = is_array($approval->payload) ? $approval->payload : [];
        $projectId = (int) ($payload['project_id'] ?? 0);
        if ($projectId < 1) {
            throw new DomainException('项目审批缺少项目编号');
        }

        $project = Project::allData()->lockForUpdate()->findOrFail($projectId);

        if ($status === ApprovalRecord::STATUS_REJECTED) {
            if ($approval->sub_type === 'project_create') {
                if ($project->status !== 'pending') {
                    throw new DomainException('项目状态已变更，立项审批结果未同步');
                }
                $project->update(['status' => 'cancelled']);
            }
            return;
        }

        if ($status !== ApprovalRecord::STATUS_APPROVED) {
            throw new DomainException('不支持的项目审批结果');
        }

        if ($approval->sub_type === 'project_create') {
            if ($project->status !== 'pending') {
                throw new DomainException('项目状态已变更，立项审批结果未同步');
            }
            $project->update(['status' => 'in_progress']);
            return;
        }

        $fromStage = (string) ($payload['from_stage'] ?? '');
        $currentStage = $this->stageValue($project);
        if ($currentStage !== $fromStage) {
            throw new DomainException('项目阶段已变化，审批结果未同步');
        }

        $targetStage = (string) ($payload['to_stage'] ?? $approval->to_stage ?? '');
        $this->assertNextStage($currentStage, $targetStage);
        if (!app(ProjectStageService::class)->advance(
            $project,
            $targetStage,
            '项目审批通过后自动推进阶段',
            $operator->id
        )) {
            throw new DomainException('项目阶段推进失败，请刷新后重试');
        }

        if ($approval->sub_type === 'project_close') {
            $project->update([
                'status' => 'completed',
                'actual_end_date' => $project->actual_end_date ?? today(),
            ]);
        }
    }

    private function assertNextStage(string $currentStage, ?string $targetStage): string
    {
        $targetStage = (string) $targetStage;
        $currentOrder = ProjectStageService::STAGE_ORDER[$currentStage] ?? 0;
        $targetOrder = ProjectStageService::STAGE_ORDER[$targetStage] ?? 0;
        if ($currentOrder === 0 || $targetOrder !== $currentOrder + 1) {
            throw new DomainException('项目阶段只能推进到紧邻的下一阶段');
        }

        return $targetStage;
    }

    private function stageValue(Project $project): string
    {
        return $project->stage instanceof \BackedEnum
            ? $project->stage->value
            : (string) $project->stage;
    }
}
