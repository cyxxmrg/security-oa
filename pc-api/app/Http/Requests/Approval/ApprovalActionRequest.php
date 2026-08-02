<?php

namespace App\Http\Requests\Approval;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-17: 审批操作 (通过/驳回/转交) — 通用
 *
 * 复用于 OperationApproval/FinanceApproval/ProjectApproval 三个 controller
 * action 字段区分: approve / reject / forward
 */
class ApprovalActionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $action = $this->input('action');

        if ($action === 'reject') {
            return [
                'action'  => ['required', 'string', 'in:approve,reject,forward'],
                'comment' => ['required', 'string', 'min:1', 'max:500'],
            ];
        }
        if ($action === 'forward') {
            return [
                'action'  => ['required', 'string', 'in:approve,reject,forward'],
                'target'  => ['required', 'string', 'max:100'],
                'comment' => ['nullable', 'string', 'max:500'],
            ];
        }
        // approve (可附 comment, 可不附)
        return [
            'action'  => ['required', 'string', 'in:approve,reject,forward'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required'  => '审批动作不能为空',
            'action.in'        => '审批动作必须是 approve/reject/forward',
            'comment.required' => '驳回理由不能为空',
            'target.required'  => '转交对象不能为空',
        ];
    }
}
