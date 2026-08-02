<?php

namespace App\Http\Requests\Project;

use App\Http\Requests\BaseFormRequest;
use App\Enums\ProjectStage;

/**
 * V1.2.7 P1-10: 项目阶段变更 + 施工日志
 *
 * 复用 kind 字段 (has('stage') vs has('work_date') 区分)
 */
class UpdateProjectStageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        if ($this->has('work_date')) {
            // 施工日志
            return [
                'work_date'   => ['required', 'date', 'date_format:Y-m-d'],
                'content'     => ['required', 'string', 'max:2000'],
                'weather'     => ['nullable', 'string', 'max:50'],
                'problems'    => ['nullable', 'string', 'max:2000'],
                'solutions'   => ['nullable', 'string', 'max:2000'],
                'photos'      => ['nullable', 'array'],
                'photos.*'    => ['string', 'max:500'],
                'work_hours'  => ['nullable', 'numeric', 'min:0', 'max:24'],
                'location'    => ['nullable', 'string', 'max:255'],
            ];
        }
        // 阶段变更
        $validStages = implode(',', array_map(fn($c) => $c->value, ProjectStage::cases()));
        return [
            'stage' => ['required', 'string', "in:{$validStages}"],
        ];
    }

    public function messages(): array
    {
        return [
            'stage.required'     => '项目阶段不能为空',
            'stage.in'           => '项目阶段非法',
            'work_date.required' => '施工日期不能为空',
            'content.required'   => '施工内容不能为空',
        ];
    }
}
