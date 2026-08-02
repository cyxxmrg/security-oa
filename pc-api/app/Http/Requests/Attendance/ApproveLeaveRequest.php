<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseFormRequest;

class ApproveLeaveRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'action'  => ['required', 'string', 'in:approved,rejected'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
