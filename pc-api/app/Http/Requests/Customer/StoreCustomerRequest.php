<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-7: 新建/更新客户
 *
 * 复用于 store 和 update (controller 内部用 'sometimes' 处理)
 * 详见 CustomerService::createCustomer / updateCustomer
 */
class StoreCustomerRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name'         => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:200'],
            'short_name'   => ['nullable', 'string', 'max:100'],
            'category'     => ['nullable', 'string', 'in:普通,VIP,潜在,normal,vip,strategic,inactive'],
            'industry'     => ['nullable', 'string', 'max:100'],
            'scale'        => ['nullable', 'string', 'max:50'],
            'level'        => ['nullable', 'string', 'in:A,B,C,D'],
            'province'     => ['nullable', 'string', 'max:50'],
            'city'         => ['nullable', 'string', 'max:50'],
            'district'     => ['nullable', 'string', 'max:50'],
            'address'      => ['nullable', 'string', 'max:500'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'email'        => ['nullable', 'email', 'max:200'],
            'website'      => ['nullable', 'string', 'max:200'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['string', 'max:50'],
            'assigned_to'  => ['nullable', 'integer', 'exists:users,id'],
            'remark'       => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => '客户名称不能为空',
            'category.in'      => '客户类型必须是 普通/VIP/潜在',
            'level.in'         => '客户等级必须是 A/B/C/D',
            'assigned_to.exists' => '负责人不存在',
        ];
    }
}
