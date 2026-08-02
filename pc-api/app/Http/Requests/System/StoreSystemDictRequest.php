<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-15: 系统字典
 *
 * 复用于 store / update, controller 内部 has('kind') 判断
 */
class StoreSystemDictRequest extends BaseFormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'kind'        => ['required', 'string', 'max:50'],
                'code'        => ['required', 'string', 'max:50'],
                'label'       => ['required', 'string', 'max:100'],
                'color'       => ['nullable', 'string', 'max:20'],
                'icon'        => ['nullable', 'string', 'max:50'],
                'sort_order'  => ['nullable', 'integer', 'min:0'],
                'is_active'   => ['nullable', 'boolean'],
                'is_default'  => ['nullable', 'boolean'],
                'description' => ['nullable', 'string', 'max:500'],
            ];
        }
        // PATCH
        return [
            'label'       => ['sometimes', 'required', 'string', 'max:100'],
            'color'       => ['sometimes', 'nullable', 'string', 'max:20'],
            'icon'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
            'is_default'  => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.required'  => '字典分类不能为空',
            'code.required'  => '字典编码不能为空',
            'label.required' => '字典名称不能为空',
        ];
    }
}
