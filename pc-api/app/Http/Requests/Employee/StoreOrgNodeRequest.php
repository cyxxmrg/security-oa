<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-4: 部门 / 岗位 (复用一个 FormRequest, 走 kind 区分)
 *
 * 用于:
 *   - EmployeeController::storeDepartment()
 *   - EmployeeController::storePosition()
 */
class StoreOrgNodeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        // 通过 has() 判断是部门还是岗位
        if ($this->has('name') && $this->has('parent_id')) {
            // 部门
            return [
                'name'        => ['required', 'string', 'max:64'],
                'parent_id'   => ['nullable', 'integer', 'exists:departments,id'],
                'manager_id'  => ['nullable', 'integer', 'exists:users,id'],
                'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
                'description' => ['nullable', 'string', 'max:255'],
            ];
        }
        // 岗位
        return [
            'name'        => ['required', 'string', 'max:64'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'level'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => '名称不能为空',
            'parent_id.exists'    => '父部门不存在',
            'manager_id.exists'   => '部门负责人不存在',
            'department_id.exists' => '所属部门不存在',
        ];
    }
}
