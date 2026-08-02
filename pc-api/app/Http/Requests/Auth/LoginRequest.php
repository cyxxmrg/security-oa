<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class LoginRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // 登录接口不要求已登录
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:2', 'max:64'],
            'password' => ['required', 'string', 'min:4', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => '用户名不能为空',
            'password.required' => '密码不能为空',
        ];
    }
}
