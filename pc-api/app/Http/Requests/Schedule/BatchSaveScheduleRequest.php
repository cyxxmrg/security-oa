<?php

namespace App\Http\Requests\Schedule;

use App\Http\Requests\BaseFormRequest;

class BatchSaveScheduleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'assignments'             => ['required', 'array', 'min:1', 'max:500'],
            'assignments.*.user_id'   => ['required', 'integer', 'exists:users,id'],
            'assignments.*.date'      => ['required', 'date_format:Y-m-d'],
            'assignments.*.shift_id'  => ['required', 'integer', 'exists:shifts,id'],
            'assignments.*.note'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'assignments.required'            => '排班数据不能为空',
            'assignments.array'               => '排班数据必须为数组',
            'assignments.min'                 => '至少要有 1 条排班',
            'assignments.max'                 => '单次最多保存 500 条',
            'assignments.*.user_id.required'  => '员工ID不能为空',
            'assignments.*.user_id.exists'    => '员工不存在',
            'assignments.*.date.required'     => '日期不能为空',
            'assignments.*.shift_id.required' => '班次ID不能为空',
            'assignments.*.shift_id.exists'   => '班次不存在',
        ];
    }
}
