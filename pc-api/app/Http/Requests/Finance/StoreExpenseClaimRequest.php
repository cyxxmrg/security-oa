<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-20: 报销申请
 *
 * 详见 ExpenseController::store
 */
class StoreExpenseClaimRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'category'              => ['required', 'string', 'max:50'],
            'description'           => ['nullable', 'string', 'max:2000'],
            'project_id'            => ['nullable', 'integer', 'exists:projects,id'],
            'items'                 => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_date'     => ['nullable', 'date', 'date_format:Y-m-d'],
            'items.*.description'   => ['nullable', 'string', 'max:500'],
            'items.*.amount'        => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.category'      => ['nullable', 'string', 'max:50'],
            'items.*.remark'        => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required'    => '报销类型不能为空',
            'items.required'       => '至少 1 条报销明细',
            'items.min'            => '至少 1 条报销明细',
            'items.max'            => '单次最多 100 条明细',
            'items.*.amount.required' => '明细金额不能为空',
            'items.*.amount.min'   => '明细金额不能为负',
            'items.*.amount.max'   => '单条金额不能超过 999999.99',
        ];
    }
}
