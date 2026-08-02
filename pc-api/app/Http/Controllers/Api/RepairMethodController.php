<?php

namespace App\Http\Controllers\Api;

use App\Enums\RepairMethodType;
use App\Http\Controllers\Controller;
use App\Models\RepairMethod;
use App\Models\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * V0.5.5 维修方式端点
 */
class RepairMethodController extends Controller
{
    public function index(int $repairOrderId): JsonResponse
    {
        RepairOrder::findOrFail($repairOrderId);
        $list = RepairMethod::where('repair_order_id', $repairOrderId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($m) => $this->present($m));
        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function store(Request $request, int $repairOrderId): JsonResponse
    {
        $data = $request->validate([
            'method_type'    => 'required|in:free_warranty,free_contract,paid_repair,paid_replace,returned',
            'method_category'=> 'nullable|string|max:32',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost'    => 'nullable|numeric|min:0',
            'parts_replaced' => 'nullable|array',
            'parts_replaced.*.name' => 'required_with:parts_replaced|string|max:128',
            'parts_replaced.*.qty'  => 'required_with:parts_replaced|numeric|min:0',
            'parts_replaced.*.price' => 'required_with:parts_replaced|numeric|min:0',
            'hours_spent'    => 'nullable|numeric|min:0',
            'vendor_id'      => 'nullable|integer',
            'payment_method' => 'nullable|string|max:32',
            'payment_status' => 'nullable|in:unpaid,partial,paid,refunded',
            'paid_at'        => 'nullable|date',
            'invoice_no'     => 'nullable|string|max:64',
            'remarks'        => 'nullable|string|max:1000',
        ]);

        $ro = RepairOrder::findOrFail($repairOrderId);
        $data['repair_order_id'] = $repairOrderId;
        $data['created_by'] = $request->user()?->id;
        $data['payment_status'] = $data['payment_status'] ?? 'unpaid';

        return DB::transaction(function () use ($ro, $data) {
            $m = RepairMethod::create($data);

            // 同步更新主单 method_type + 成本
            $methodEnum = RepairMethodType::from($data['method_type']);
            $ro->method_type = $methodEnum;
            $actual = (float) ($data['actual_cost'] ?? 0);
            $ro->parts_cost = (float) ($ro->parts_cost ?? 0) + ($methodEnum === RepairMethodType::PAID_REPLACE ? $actual : 0);
            $ro->labor_cost = (float) ($ro->labor_cost ?? 0) + ($methodEnum === RepairMethodType::PAID_REPAIR ? $actual : 0);
            $ro->total_cost = (float) $ro->parts_cost + (float) $ro->labor_cost + (float) $ro->shipping_cost;
            $ro->save();

            return response()->json(['code' => 0, 'data' => $this->present($m), 'message' => '已添加维修方式']);
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $m = RepairMethod::findOrFail($id);
        $data = $request->validate([
            'method_category'=> 'nullable|string|max:32',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost'    => 'nullable|numeric|min:0',
            'parts_replaced' => 'nullable|array',
            'hours_spent'    => 'nullable|numeric|min:0',
            'vendor_id'      => 'nullable|integer',
            'payment_method' => 'nullable|string|max:32',
            'payment_status' => 'nullable|in:unpaid,partial,paid,refunded',
            'paid_at'        => 'nullable|date',
            'invoice_no'     => 'nullable|string|max:64',
            'remarks'        => 'nullable|string|max:1000',
        ]);
        $m->fill($data);
        $m->save();
        return response()->json(['code' => 0, 'data' => $this->present($m), 'message' => '已更新']);
    }

    public function destroy(int $id): JsonResponse
    {
        $m = RepairMethod::findOrFail($id);
        $m->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    private function present(RepairMethod $m): array
    {
        return [
            'id' => $m->id,
            'repair_order_id' => $m->repair_order_id,
            'method_type' => $m->method_type->value,
            'method_label' => $m->method_type->label(),
            'is_free' => $m->method_type->isFree(),
            'is_paid' => $m->method_type->isPaid(),
            'method_category' => $m->method_category,
            'estimated_cost' => (float) $m->estimated_cost,
            'actual_cost' => (float) $m->actual_cost,
            'parts_replaced' => $m->parts_replaced,
            'hours_spent' => (float) $m->hours_spent,
            'vendor_id' => $m->vendor_id,
            'payment_method' => $m->payment_method,
            'payment_status' => $m->payment_status,
            'paid_at' => $m->paid_at?->toDateTimeString(),
            'invoice_no' => $m->invoice_no,
            'remarks' => $m->remarks,
            'created_at' => $m->created_at?->toDateTimeString(),
        ];
    }
}
