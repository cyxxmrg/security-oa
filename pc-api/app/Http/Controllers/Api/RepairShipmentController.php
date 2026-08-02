<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RepairOrder;
use App\Models\RepairShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V0.5.5 物流轨迹端点 (独立端点, 用于更新实际到达/异常等)
 */
class RepairShipmentController extends Controller
{
    /**
     * 列出返修单的所有物流 (按 direction 分组)
     */
    public function index(int $repairOrderId): JsonResponse
    {
        $ro = RepairOrder::findOrFail($repairOrderId);
        $list = $ro->shipments()->get()->map(fn ($s) => $this->present($s));
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /**
     * 新增物流 (供 shipOut/shipBack 之外的手动添加)
     */
    public function store(Request $request, int $repairOrderId): JsonResponse
    {
        $data = $request->validate([
            'direction'          => 'required|in:outbound,inbound',
            'carrier'            => 'required|string|max:32',
            'tracking_no'        => 'required|string|max:64',
            'cost'               => 'nullable|numeric|min:0',
            'shipped_at'         => 'nullable|date',
            'estimated_arrival'  => 'nullable|date',
            'actual_arrival'     => 'nullable|date',
            'delivery_status'    => 'nullable|in:pending,in_transit,delivered,exception',
            'sender_name'        => 'required|string|max:64',
            'sender_phone'       => 'nullable|string|max:32',
            'sender_address'     => 'nullable|string|max:255',
            'receiver_name'      => 'required|string|max:64',
            'receiver_phone'     => 'nullable|string|max:32',
            'receiver_address'   => 'required|string|max:255',
            'remarks'            => 'nullable|string|max:500',
        ]);
        $data['repair_order_id'] = $repairOrderId;
        $data['created_by'] = $request->user()?->id;
        $s = RepairShipment::create($data);
        return response()->json(['code' => 0, 'data' => $this->present($s), 'message' => '已添加']);
    }

    /**
     * 更新物流 (主要用于 actual_arrival / delivery_status)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $s = RepairShipment::findOrFail($id);
        $data = $request->validate([
            'delivery_status'    => 'sometimes|in:pending,in_transit,delivered,exception',
            'actual_arrival'     => 'nullable|date',
            'estimated_arrival'  => 'nullable|date',
            'cost'               => 'nullable|numeric|min:0',
            'remarks'            => 'nullable|string|max:500',
        ]);
        $s->fill($data);
        $s->save();
        return response()->json(['code' => 0, 'data' => $this->present($s), 'message' => '已更新']);
    }

    public function destroy(int $id): JsonResponse
    {
        $s = RepairShipment::findOrFail($id);
        $s->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    private function present(RepairShipment $s): array
    {
        return [
            'id' => $s->id,
            'repair_order_id' => $s->repair_order_id,
            'direction' => $s->direction->value,
            'direction_label' => $s->direction->label(),
            'carrier' => $s->carrier,
            'tracking_no' => $s->tracking_no,
            'cost' => (float) $s->cost,
            'shipped_at' => $s->shipped_at?->toDateTimeString(),
            'estimated_arrival' => $s->estimated_arrival?->toDateTimeString(),
            'actual_arrival' => $s->actual_arrival?->toDateTimeString(),
            'delivery_status' => $s->delivery_status,
            'sender_name' => $s->sender_name,
            'sender_phone' => $s->sender_phone,
            'sender_address' => $s->sender_address,
            'receiver_name' => $s->receiver_name,
            'receiver_phone' => $s->receiver_phone,
            'receiver_address' => $s->receiver_address,
            'remarks' => $s->remarks,
            'created_at' => $s->created_at?->toDateTimeString(),
        ];
    }
}
