<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-11: 新建/更新物料
 *
 * 详见 InventoryService::createItem / updateItem
 */
class StoreInventoryItemRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $itemId = $this->route('inventoryItem')?->id;
        $codeUnique = $itemId
            ? "unique:inventory_items,code,{$itemId}"
            : 'unique:inventory_items,code';

        return [
            'name'          => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:200'],
            'code'          => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:64', $codeUnique],
            'category'      => ['nullable', 'string', 'max:50'],
            'category_id'   => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'specification' => ['nullable', 'string', 'max:255'],
            'unit'          => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:20'],
            'safety_stock'  => ['nullable', 'integer', 'min:0'],
            'current_stock' => ['nullable', 'integer', 'min:0'],
            'cost_price'    => ['nullable', 'numeric', 'min:0'],
            'sell_price'    => ['nullable', 'numeric', 'min:0'],
            'warehouse_id'  => ['nullable', 'integer', 'exists:warehouses,id'],
            'location'      => ['nullable', 'string', 'max:100'],
            'has_serial'    => ['nullable', 'boolean'],
            'status'        => ['nullable', 'string', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '物料名称不能为空',
            'code.required' => '物料编码不能为空',
            'code.unique'   => '物料编码已存在',
            'unit.required' => '计量单位不能为空',
        ];
    }
}
