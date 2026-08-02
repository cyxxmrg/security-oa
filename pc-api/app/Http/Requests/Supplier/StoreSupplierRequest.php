<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * V1.2.7 P1-13: 新建/更新供应商
 *
 * 详见 SupplierController::store() / update()
 * 复用于 store 和 update, 内部用 isMethod 区分 required vs sometimes
 */
class StoreSupplierRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        return [
            'name'              => [$required, 'string', 'max:200'],
            'type'              => [$required, Rule::in(['material', 'labor', 'outsource', 'service'])],
            'contact_person'    => ['nullable', 'string', 'max:50'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:100'],
            'address'           => ['nullable', 'string', 'max:255'],
            'category'          => ['nullable', 'string', 'max:100'],
            'business_license'  => ['nullable', 'string', 'max:50'],
            'legal_person'      => ['nullable', 'string', 'max:50'],
            'registered_capital' => ['nullable', 'numeric', 'min:0'],
            'website'           => ['nullable', 'string', 'max:200'],
            'bank_name'         => ['nullable', 'string', 'max:100'],
            'bank_account'      => ['nullable', 'string', 'max:50'],
            'account_name'      => ['nullable', 'string', 'max:100'],
            'tax_no'            => ['nullable', 'string', 'max:50'],
            'payment_terms'     => ['nullable', Rule::in(['cash', '30days', '60days', '90days'])],
            'rating'            => ['nullable', 'integer', 'min:1', 'max:5'],
            'status'            => ['nullable', Rule::in(['active', 'paused', 'blacklist'])],
            'remark'            => ['nullable', 'string', 'max:2000'],

            'contacts'                       => ['nullable', 'array'],
            'contacts.*.name'                => ['required_with:contacts', 'string', 'max:50'],
            'contacts.*.phone'               => ['nullable', 'string', 'max:20'],
            'contacts.*.position'            => ['nullable', 'string', 'max:50'],
            'contacts.*.email'               => ['nullable', 'email', 'max:100'],
            'contacts.*.is_primary'          => ['nullable', 'boolean'],

            'account'                        => ['nullable', 'array'],
            'account.enabled'                => ['nullable', 'boolean'],
            'account.username'               => ['nullable', 'string', 'max:50'],
            'account.password'               => ['nullable', 'string', 'min:6', 'max:50'],
            'account.allowed_modules'        => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => '供应商名称不能为空',
            'type.required'         => '供应商类型不能为空',
            'type.in'               => '类型必须是 material/labor/outsource/service',
            'payment_terms.in'      => '账期必须是 cash/30days/60days/90days',
            'rating.between'        => '评级 1-5 星',
        ];
    }
}
