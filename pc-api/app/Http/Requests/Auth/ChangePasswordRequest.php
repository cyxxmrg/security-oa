<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class ChangePasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'old_password.required' => '原密码不能为空',
            'new_password.required' => '新密码不能为空',
            'new_password.min'      => '新密码至少 8 位',
            'new_password.confirmed'=> '两次输入的新密码不一致',
        ];
    }
}
