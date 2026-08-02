<?php

namespace App\Http\Requests\Schedule;

use App\Http\Requests\BaseFormRequest;

class BatchByGroupRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'group_id'        => ['required', 'integer', 'exists:shift_groups,id'],
            'shift_id'        => ['required', 'integer', 'exists:shifts,id'],
            'start_date'      => ['required', 'date_format:Y-m-d'],
            'end_date'        => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'skip_weekends'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'group_id.required'  => '班组ID不能为空',
            'group_id.exists'    => '班组不存在',
            'shift_id.required'  => '班次ID不能为空',
            'shift_id.exists'    => '班次不存在',
            'start_date.required'=> '开始日期不能为空',
            'end_date.required'  => '结束日期不能为空',
            'end_date.after_or_equal' => '结束日期不能早于开始日期',
        ];
    }
}
