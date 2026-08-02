<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\BaseFormRequest;
use App\Models\User;

/**
 * V1.2.7 P1-2: 更新员工
 *
 * unique 校验要排除自己
 */
class UpdateEmployeeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $userId = $this->route('user') instanceof User
            ? $this->route('user')->id
            : (int) $this->route('user');

        return [
            'name'          => ['sometimes', 'required', 'string', 'max:50'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:20', "unique:users,phone,{$userId}"],
            'email'         => ['sometimes', 'nullable', 'email', 'max:100', "unique:users,email,{$userId}"],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'position_id'   => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'gender'        => ['sometimes', 'nullable', 'string', 'in:male,female,other'],
            'status'        => ['sometimes', 'nullable', 'string', 'in:active,inactive'],
            'is_active'     => ['sometimes', 'boolean'],
            'hire_date'     => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            'role_id'       => ['sometimes', 'nullable', 'integer', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => '手机号已被其他员工占用',
            'email.unique' => '邮箱已被其他员工占用',
        ];
    }
}
