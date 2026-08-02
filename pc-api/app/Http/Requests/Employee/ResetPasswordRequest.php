<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-3: 重置员工密码
 */
class ResetPasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'min:6', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => '密码至少 6 位',
            'password.max' => '密码不能超过 64 字符',
        ];
    }
}
