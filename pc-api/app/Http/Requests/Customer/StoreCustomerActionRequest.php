<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-8: 客户联系人 / 跟进 — 通用写操作
 *
 * 复用 kind 字段区分 (controller 内部 has('customer_id') 判断)
 */
class StoreCustomerActionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        if ($this->has('gender') || $this->has('wechat')) {
            // 联系人 contact
            return [
                'name'       => ['required', 'string', 'max:50'],
                'phone'      => ['required', 'string', 'max:20'],
                'email'      => ['nullable', 'email', 'max:200'],
                'title'      => ['nullable', 'string', 'max:50'],
                'gender'     => ['nullable', 'in:male,female,other'],
                'birthday'   => ['nullable', 'date', 'date_format:Y-m-d'],
                'is_primary' => ['nullable', 'boolean'],
                'wechat'     => ['nullable', 'string', 'max:100'],
                'remark'     => ['nullable', 'string', 'max:2000'],
            ];
        }
        // 跟进 follow_up
        return [
            'content'    => ['required', 'string', 'max:2000'],
            'method'     => ['nullable', 'string', 'in:phone,visit,wechat,email,other'],
            'result'     => ['nullable', 'string', 'in:connected,intent,no_intent,callback,other'],
            'follow_at'  => ['nullable', 'date'],
            'next_date'  => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'remark'     => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => '联系人姓名不能为空',
            'phone.required'   => '联系人电话不能为空',
            'content.required' => '跟进内容不能为空',
        ];
    }
}
