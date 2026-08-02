<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * V1.2.7 P1: 业务 FormRequest 基类
 *
 * 默认：未通过验证时直接返回结构化 JSON 错误（统一格式），
 * 而不是 Laravel 默认的 422 HTML 错误页。
 */
abstract class BaseFormRequest extends FormRequest
{
    /**
     * 鉴权判断：所有受保护的接口默认需要登录。
     * 子类不重写则一律放行（由路由 middleware auth:sanctum 控制）。
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * 重写失败响应：返回 {code:422, message, errors} 结构
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' => '请确认信息是否完成',
            'errors'  => $validator->errors()->toArray(),
        ], 422));
    }

    /**
     * 子类必填：定义 rules()
     */
    abstract public function rules(): array;
}
