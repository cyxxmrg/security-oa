<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseFormRequest;

class StoreLeaveRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type'       => ['required', 'string', 'in:personal,sick,annual,marriage,maternity,paternity,compassionate,funeral,other'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'days'       => ['nullable', 'numeric', 'min:0.5', 'max:365'],  // V1.2.10 可选, Controller 自动算
            'reason'     => ['required', 'string', 'min:2', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'       => '请选择请假类型',
            'type.in'             => '请假类型非法',
            'start_date.required' => '开始日期不能为空',
            'start_date.date_format' => '开始日期格式必须为 Y-m-d',
            'end_date.required'   => '结束日期不能为空',
            'end_date.after_or_equal' => '结束日期不能早于开始日期',
            'days.required'       => '请假天数不能为空',
            'days.min'            => '请假天数至少 0.5 天',
            'days.max'            => '请假天数不能超过 365 天',
            'reason.required'     => '请假原因不能为空',
            'reason.max'          => '请假原因不能超过 500 字',
        ];
    }
}
