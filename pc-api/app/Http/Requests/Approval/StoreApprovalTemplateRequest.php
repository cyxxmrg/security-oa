<?php

namespace App\Http\Requests\Approval;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-18: 审批模板配置
 *
 * 字段与 ApprovalTemplateController::store / update 对齐
 *  - name: 模板名称
 *  - type: 类型 (可选)
 *  - module: 模块 (leave/expense/purchase/project/...)
 *  - steps: 审批节点数组 (e.g. [{step_no, approver_role, approver_user_id}])
 *  - description, enabled, sort_order
 */
class StoreApprovalTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        return [
            'name'                       => [$required, 'string', 'max:100'],
            'type'                       => ['nullable', 'string', 'max:50'],
            'module'                     => [$required, 'string', 'max:50'],
            'description'                => ['nullable', 'string', 'max:500'],
            'steps'                      => ['nullable', 'array'],
            'nodes'                      => ['nullable', 'array'],
            'steps.*.step_no'            => ['nullable', 'integer', 'min:1'],
            'steps.*.approver_role'      => ['nullable', 'string', 'max:50'],
            'steps.*.approver_user_id'   => ['nullable', 'integer', 'exists:users,id'],
            'steps.*.name'               => ['nullable', 'string', 'max:100'],
            'steps.*.desc'               => ['nullable', 'string', 'max:200'],
            'steps.*.type'               => ['nullable', 'string', 'max:30'],
            'steps.*.approver'           => ['nullable', 'integer', 'exists:users,id'],
            'steps.*.position'           => ['nullable', 'array'],
            'steps.*.position.x'         => ['nullable', 'numeric'],
            'steps.*.position.y'         => ['nullable', 'numeric'],
            'nodes.*.id'                 => ['nullable', 'string', 'max:100'],
            'nodes.*.name'               => ['nullable', 'string', 'max:100'],
            'nodes.*.desc'               => ['nullable', 'string', 'max:200'],
            'nodes.*.type'               => ['nullable', 'string', 'max:30'],
            'nodes.*.approver'           => ['nullable', 'integer', 'exists:users,id'],
            'nodes.*.position'           => ['nullable', 'array'],
            'nodes.*.position.x'         => ['nullable', 'numeric'],
            'nodes.*.position.y'         => ['nullable', 'numeric'],
            'enabled'                    => ['nullable', 'boolean'],
            'sort_order'                 => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                  => '模板名称不能为空',
            'module.required'                => '模板模块不能为空',
            'steps.array'                    => '审批节点必须是数组',
        ];
    }
}
