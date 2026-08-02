<?php

namespace App\Http\Requests\Schedule;

use App\Http\Requests\BaseFormRequest;

class UpdateShiftRequest extends BaseFormRequest
{
    public function rules(): array
    {
        // 路由参数 {shift} 隐式绑定到 Shift 模型
        $shiftId = $this->route('shift')?->id;

        return [
            'name'             => ['sometimes', 'string', 'max:50'],
            'code'             => ['sometimes', 'string', 'max:20', 'unique:shifts,code,' . $shiftId],
            'start_time'       => ['sometimes', 'date_format:H:i:s'],
            'end_time'         => ['sometimes', 'date_format:H:i:s'],
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
}
