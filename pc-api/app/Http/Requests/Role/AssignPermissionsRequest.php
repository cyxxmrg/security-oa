<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * V1.2.7 P1-6: 同步角色权限
 *
 * 用于 POST /api/roles/{role}/permissions
 * 传 { permissions: ['perm1', 'perm2'] } — 全量替换
 */
class AssignPermissionsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'permissions'   => ['required', 'array', 'min:0'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.required'  => '权限列表不能为空 (传空数组表示清空)',
            'permissions.*.exists'  => '权限点不存在',
        ];
    }
}
