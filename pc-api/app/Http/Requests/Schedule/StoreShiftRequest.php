<?php

namespace App\Http\Requests\Schedule;

use App\Http\Requests\BaseFormRequest;

class StoreShiftRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:50'],
            'code'             => ['required', 'string', 'max:20', 'unique:shifts,code'],
            'start_time'       => ['required', 'date_format:H:i:s'],
            'end_time'         => ['required', 'date_format:H:i:s'],
            'late_threshold_minutes'       => ['nullable', 'integer', 'min:0', 'max:120'],
            'early_leave_threshold_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'work_hours'       => ['nullable', 'numeric', 'min:0', 'max:24'],
            'color'            => ['nullable', 'string', 'max:20'],
            'is_overnight'     => ['boolean'],
            'is_active'        => ['boolean'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'remark'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => '班次名称不能为空',
            'name.max'            => '班次名称不能超过 50 字',
            'code.required'       => '班次代码不能为空',
            'code.unique'         => '班次代码已存在',
            'start_time.required' => '开始时间不能为空',
            'end_time.required'   => '结束时间不能为空',
        ];
    }
}
