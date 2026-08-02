<?php

namespace App\Http\Requests\Purchase;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-19: 采购计划
 *
 * 详见 PurchasePlanController::store / update
 */
class StorePurchasePlanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        return [
            'requirement_id' => ['nullable', 'integer', 'exists:purchase_requirements,id'],
            'project_id'     => ['nullable', 'integer', 'exists:projects,id'],
            'title'          => [$required, 'string', 'max:200'],
            'total_amount'   => ['nullable', 'numeric', 'min:0'],
            'plan_date'      => ['nullable', 'date', 'date_format:Y-m-d'],
            'priority'       => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'remark'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'  => '采购计划标题不能为空',
            'priority.in'     => '优先级必须是 low/medium/high/urgent',
            'total_amount.min' => '金额不能为负',
        ];
    }
}
