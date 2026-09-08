<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-1: 新建员工
 *
 * 字段对应 users + employee_profiles 两张表
 * 详见 EmployeeController::store()
 */
class StoreEmployeeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:50'],
            'username'      => ['required', 'string', 'min:2', 'max:64', 'unique:users,username'],
            'phone'         => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'email'         => ['nullable', 'email', 'max:100', 'unique:users,email'],
            'password'      => ['nullable', 'string', 'min:12', 'max:64', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id'   => ['nullable', 'integer', 'exists:positions,id'],
            'gender'        => ['nullable', 'string', 'in:male,female,other'],
            'role_id'       => ['nullable', 'integer', 'exists:roles,id'],
            'hire_date'     => ['nullable', 'date', 'date_format:Y-m-d'],
            'base_salary'   => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => '姓名不能为空',
            'username.required'     => '工号/用户名不能为空',
            'username.unique'       => '工号已存在',
            'phone.unique'          => '手机号已被占用',
            'email.unique'          => '邮箱已被占用',
            'password.min'          => '密码至少 12 位',
            'password.regex'        => '密码必须同时包含字母和数字',
            'department_id.exists'  => '部门不存在',
            'position_id.exists'    => '岗位不存在',
            'role_id.exists'        => '角色不存在',
        ];
    }
}
