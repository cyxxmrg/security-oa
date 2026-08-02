<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-12: 出入库流水
 *
 * 详见 InventoryService::createStockRecord
 */
class StoreStockRecordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'item_id'         => ['required', 'integer', 'exists:inventory_items,id'],
            'warehouse_id'    => ['required', 'integer', 'exists:warehouses,id'],
            'type'            => ['required', 'string', 'in:in,out,transfer,adjust,return'],
            'quantity'        => ['required', 'integer', 'not_in:0'],
            'unit_price'      => ['nullable', 'numeric', 'min:0'],
            'serial_no'       => ['nullable', 'string', 'max:100'],
            'reference_type'  => ['nullable', 'string', 'max:50'],
            'reference_id'    => ['nullable', 'integer'],
            'operator'        => ['nullable', 'string', 'max:50'],
            'remark'          => ['nullable', 'string', 'max:1000'],
            'occurred_at'     => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required'     => '物料不能为空',
            'item_id.exists'       => '物料不存在',
            'warehouse_id.required' => '仓库不能为空',
            'type.required'        => '出入库类型不能为空',
            'type.in'              => '类型必须是 in/out/transfer/adjust/return',
            'quantity.required'    => '数量不能为空',
            'quantity.not_in'      => '数量不能为 0',
        ];
    }
}
