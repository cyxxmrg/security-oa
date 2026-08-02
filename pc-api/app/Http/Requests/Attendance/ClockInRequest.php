<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseFormRequest;

class ClockInRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'lat'      => ['nullable', 'numeric', 'between:-90,90'],
            'lng'      => ['nullable', 'numeric', 'between:-180,180'],
            'location' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ];
    }
}
