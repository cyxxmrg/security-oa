<?php

namespace App\Http\Requests\Project;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-9: 新建/更新项目
 *
 * 详见 ProjectController::store() / update()
 */
class StoreProjectRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name'             => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:200'],
            'customer_id'      => ['required', 'integer', 'exists:customers,id'],  // DB NOT NULL
            'type'             => ['nullable', 'string', 'max:50'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'budget_device'    => ['nullable', 'numeric', 'min:0'],
            'budget_material'  => ['nullable', 'numeric', 'min:0'],
            'budget_labor'     => ['nullable', 'numeric', 'min:0'],
            'budget_outsource' => ['nullable', 'numeric', 'min:0'],
            'budget_other'     => ['nullable', 'numeric', 'min:0'],
            'manager_id'       => ['nullable', 'integer', 'exists:users,id'],
            'start_date'       => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date'         => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'priority'         => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'member_ids'       => ['nullable', 'array'],
            'member_ids.*'     => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => '项目名称不能为空',
            'customer_id.required'    => '请选择客户',
            'customer_id.exists'      => '客户不存在',
            'manager_id.exists'       => '项目经理不存在',
            'end_date.after_or_equal' => '结束日期必须 >= 开始日期',
            'priority.in'             => '优先级值无效（接受: low/normal/high/urgent）',
            'member_ids.*.exists'     => '项目成员不存在',
        ];
    }
}
