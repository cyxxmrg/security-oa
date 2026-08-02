<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * V1.2.7 P1-5: 创建角色
 *
 * 角色名 web guard 下唯一 (spatie 复合主键 = name + guard_name)
 */
class StoreRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name'          => [
                'required', 'string', 'max:64',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'description'   => ['nullable', 'string', 'max:255'],
            'color'         => ['nullable', 'string', 'max:16', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'           => '角色名已存在',
            'color.regex'           => '颜色必须是 #RRGGBB 格式',
            'permissions.*.exists'  => '权限点不存在',
        ];
    }
}
