<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseFormRequest;

class StoreOvertimeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'overtime_date'      => ['nullable', 'date_format:Y-m-d'],  // V1.2.10 改可选, Controller 用 date 别名兜底
            'date'               => ['nullable', 'date_format:Y-m-d'],  // 别名
            'overtime_date_required' => ['sometimes'],  // 占位
            'start_time'         => ['required', 'date_format:H:i'],
            'end_time'           => ['required', 'date_format:H:i', 'after:start_time'],
            'hours'              => ['required', 'numeric', 'min:0.5', 'max:24'],
            'reason'             => ['required', 'string', 'min:2', 'max:500'],
            'compensation_type'  => ['required', 'string', 'in:overtime_pay,pay,time_off,leave'],
        ];
    }

    public function messages(): array
    {
        return [
            'overtime_date.required'      => '加班日期不能为空',
            'start_time.required'         => '开始时间不能为空',
            'end_time.after'              => '结束时间必须晚于开始时间',
            'hours.required'              => '加班小时数不能为空',
            'hours.min'                   => '加班小时数至少 0.5 小时',
            'hours.max'                   => '加班小时数不能超过 24 小时',
            'compensation_type.required'  => '请选择补偿方式',
            'compensation_type.in'        => '补偿方式只能为 加班费 或 调休',
        ];
    }
}
