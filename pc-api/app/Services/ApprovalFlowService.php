<?php

namespace App\Services;

use App\Models\ApprovalRecord;
use App\Models\ApprovalTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 审批流程执行器 — 连接设计器模板与实际审批执行
 *
 * 职责:
 *  1. 根据 sub_type 找到匹配的模板
 *  2. 初始化 ApprovalRecord 时设置首个审批节点
 *  3. 审批节点推进（当前节点通过后自动进入下一节点）
 */
class ApprovalFlowService
{
    /**
     * sub_type → 模板 module 映射
     * 前端设计器用中文 module 名（请假/报销/采购等），
     * 后端 approval_records_v2.sub_type 用英文
     */
    const MODULE_MAP = [
        'leave'                => '请假',
        'overtime'             => '请假',
        'resignation'          => '离职',
        'expense'              => '报销',
        'purchase_requirement' => '采购',
        'purchase_plan'        => '采购',
        'purchase_payment'     => '采购',
        'commencement'         => '开工',
        'material-request'     => '采购',
        'referral_settlement'  => '报销',
    ];

    /**
     * 根据 sub_type 查找启用的流程模板
     */
    public function resolveTemplate(string $subType): ?ApprovalTemplate
    {
        $module = self::MODULE_MAP[$subType] ?? null;
        if (!$module) return null;

        return ApprovalTemplate::where('module', $module)
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * 从模板 steps 中提取审批节点列表（排除 start/end）
     */
    public function getApprovalSteps(ApprovalTemplate $template): array
    {
        $steps = is_array($template->steps) ? $template->steps : [];
        return array_values(array_filter($steps, fn($s) => ($s['type'] ?? '') === 'approval'));
    }

    /**
     * 初始化流程数据：设置 current_approver_id + flow 初始记录
     *
     * @param ApprovalTemplate $template  匹配的模板
     * @param User $applicant  申请人
     * @param string $comment  提交说明
     * @return array ['current_approver_id' => int|null, 'flow' => array]
     */
    public function initFlow(ApprovalTemplate $template, User $applicant, string $comment = '提交申请'): array
    {
        $approvalSteps = $this->getApprovalSteps($template);
        $firstApprover = null;
        $flow = [];

        // 记录提交动作
        $flow[] = [
            'operator' => $applicant->name,
            'action'   => 'submit',
            'time'     => now()->toDateTimeString(),
            'comment'  => $comment,
        ];

        // 设置第一个审批节点
        if (!empty($approvalSteps)) {
            $firstStep = $approvalSteps[0];
            $firstApprover = null;
            if (!empty($firstStep['approver'])) {
                $firstApprover = (int) $firstStep['approver'];
            }
            // 记录待审批节点
            $flow[] = [
                'operator'   => $firstStep['name'] ?? '审批节点',
                'action'     => 'pending',
                'time'       => now()->toDateTimeString(),
                'comment'    => '等待审批: ' . ($firstStep['desc'] ?? ''),
                'step_index' => 0,
                'step_name'  => $firstStep['name'] ?? '',
            ];
        }

        return [
            'current_approver_id' => $firstApprover,
            'flow'                => $flow,
        ];
    }

    /**
     * 审批通过后推进到下一节点
     *
     * @param ApprovalRecord $record  当前审批记录
     * @param User $operator  当前审批人
     * @param string $comment  审批意见
     * @return array ['status' => string, 'current_approver_id' => int|null, 'flow' => array]
     */
    public function advanceFlow(ApprovalRecord $record, User $operator, string $comment = ''): array
    {
        $flow = is_array($record->flow) ? $record->flow : [];
        $currentStepIndex = $this->getCurrentStepIndex($flow);

        // 标记当前待审批节点为已通过
        foreach ($flow as $i => $step) {
            if (($step['action'] ?? '') === 'pending' && ($step['step_index'] ?? -1) === $currentStepIndex) {
                $flow[$i]['action'] = 'approved';
                $flow[$i]['operator'] = $operator->name;
                $flow[$i]['time'] = now()->toDateTimeString();
                $flow[$i]['comment'] = $comment ?: '已通过';
                break;
            }
        }

        // 查找模板，确定下一节点
        $template = $this->resolveTemplate($record->sub_type ?? '');
        $approvalSteps = $template ? $this->getApprovalSteps($template) : [];
        $nextStepIndex = $currentStepIndex + 1;

        if ($nextStepIndex < count($approvalSteps)) {
            // 有下一审批节点
            $nextStep = $approvalSteps[$nextStepIndex];
            $nextApprover = !empty($nextStep['approver']) ? (int) $nextStep['approver'] : null;
            $flow[] = [
                'operator'   => $nextStep['name'] ?? '审批节点',
                'action'     => 'pending',
                'time'       => now()->toDateTimeString(),
                'comment'    => '等待审批: ' . ($nextStep['desc'] ?? ''),
                'step_index' => $nextStepIndex,
                'step_name'  => $nextStep['name'] ?? '',
            ];
            return [
                'status'              => ApprovalRecord::STATUS_PENDING,
                'current_approver_id' => $nextApprover,
                'flow'                => $flow,
            ];
        } else {
            // 所有节点通过，流程结束
            $flow[] = [
                'operator' => $operator->name,
                'action'   => 'complete',
                'time'     => now()->toDateTimeString(),
                'comment'  => '审批流程完成',
            ];
            return [
                'status'              => ApprovalRecord::STATUS_APPROVED,
                'current_approver_id' => null,
                'flow'                => $flow,
            ];
        }
    }

    /**
     * 获取当前待审批节点的 step_index
     */
    private function getCurrentStepIndex(array $flow): int
    {
        foreach ($flow as $step) {
            if (($step['action'] ?? '') === 'pending') {
                return (int) ($step['step_index'] ?? 0);
            }
        }
        return 0;
    }

    /**
     * 驳回时重置审批流
     */
    public function rejectFlow(ApprovalRecord $record, User $operator, string $reason = ''): array
    {
        $flow = is_array($record->flow) ? $record->flow : [];

        // 标记当前待审批节点为已驳回
        foreach ($flow as $i => $step) {
            if (($step['action'] ?? '') === 'pending') {
                $flow[$i]['action'] = 'rejected';
                $flow[$i]['operator'] = $operator->name;
                $flow[$i]['time'] = now()->toDateTimeString();
                $flow[$i]['comment'] = $reason ?: '已驳回';
                break;
            }
        }

        $flow[] = [
            'operator' => $operator->name,
            'action'   => 'reject',
            'time'     => now()->toDateTimeString(),
            'comment'  => $reason ?: '已驳回',
        ];

        return [
            'status'              => ApprovalRecord::STATUS_REJECTED,
            'current_approver_id' => null,
            'flow'                => $flow,
        ];
    }
}
