<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseLogistics;
use App\Models\PurchaseShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * 采购物流 (Logistics) — 4 端点
 *
 *  POST /api/purchase/shipments/{shipment}/logistics-update  追加一条物流事件（自动推进 shipment 状态）
 *  GET  /api/purchase/shipments/{shipment}/track            获取该运单完整轨迹
 *  GET  /api/purchase/shipments/{shipment}/logistics        列出该运单所有物流事件（分页）
 *  PUT  /api/purchase/shipments/{shipment}/logistics/{log}  更新单条事件
 */
class PurchaseLogisticsController extends Controller
{
    public function store(Request $request, PurchaseShipment $shipment): JsonResponse
    {
        $data = $request->validate([
            'event_at'    => 'nullable|date',
            'location'    => 'nullable|string|max:200',
            'status'      => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'operator'    => 'nullable|string|max:50',
        ]);

        $data['shipment_id'] = $shipment->id;
        $data['tracking_no'] = $data['tracking_no'] ?? $shipment->tracking_no;
        $data['event_at']    = $data['event_at']    ?? now();

        $log = PurchaseLogistics::create($data);

        // 推进 shipment 状态
        $inferred = $this->inferShipmentStatus($log->status, $log->description, $shipment);
        if ($inferred && $inferred !== $shipment->status) {
            $update = ['status' => $inferred];
            if ($inferred === 'arrived') $update['arrived_at'] = $log->event_at;
            $shipment->update($update);
        }

        return response()->json(['code' => 0, 'data' => $log]);
    }

    public function track(PurchaseShipment $shipment): JsonResponse
    {
        $logs = PurchaseLogistics::where('shipment_id', $shipment->id)
            ->orderBy('event_at', 'asc')
            ->get();

        return response()->json([
            'code' => 0,
            'data' => [
                'shipment' => $shipment->only([
                    'id', 'code', 'status', 'carrier', 'tracking_no',
                    'shipped_at', 'expected_arrival_at', 'arrived_at',
                ]),
                'events'    => $logs,
                'is_completed' => $shipment->status === 'arrived' || $shipment->status === 'closed',
            ],
        ]);
    }

    public function index(Request $request, PurchaseShipment $shipment): JsonResponse
    {
        $query = PurchaseLogistics::where('shipment_id', $shipment->id);
        $perPage = (int) ($request->per_page ?? 20);
        return response()->json(['code' => 0, 'data' => $query->orderBy('event_at', 'desc')->paginate(max(1, min($perPage, 200)))]);
    }

    public function update(Request $request, PurchaseShipment $shipment, PurchaseLogistics $log): JsonResponse
    {
        if ((int) $log->shipment_id !== (int) $shipment->id) {
            return response()->json(['code' => 1, 'message' => '物流事件不属于该运单'], 404);
        }

        $data = $request->validate([
            'event_at'    => 'nullable|date',
            'location'    => 'nullable|string|max:200',
            'status'      => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'operator'    => 'nullable|string|max:50',
        ]);

        $log->update($data);
        return response()->json(['code' => 0, 'data' => $log->fresh()]);
    }

    /**
     * 简单状态推断：物流 status/description 含「到达/签收/妥投」→ arrived
     *  含「在途/运输中」→ in_transit
     */
    private function inferShipmentStatus(?string $status, ?string $description, PurchaseShipment $shipment): ?string
    {
        if ($shipment->status === 'closed') return null;
        $text = ($status ?? '') . ' ' . ($description ?? '');
        if (preg_match('/(到达|签收|妥投|已收|delivered|arrived)/iu', $text)) {
            return 'arrived';
        }
        if (preg_match('/(在途|运输中|已发出|转运|in[_\s-]?transit)/iu', $text)) {
            return 'in_transit';
        }
        return null;
    }

    /**
     * 物流总览 — 兼容前端 /purchase/logistics
     */
    public function overview(Request $request): JsonResponse
    {
        $shipments = PurchaseShipment::with('contract')
            ->whereIn('status', ['shipped', 'in_transit', 'arrived'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'contract_id', 'tracking_no', 'carrier', 'status', 'shipped_at', 'arrived_at', 'updated_at']);

        $stats = [
            'total_in_transit' => PurchaseShipment::where('status', 'in_transit')->count(),
            'total_shipped' => PurchaseShipment::where('status', 'shipped')->count(),
            'total_arrived' => PurchaseShipment::where('status', 'arrived')->count(),
            'total_pending' => PurchaseShipment::whereNull('shipped_at')->count(),
        ];

        return response()->json([
            'code' => 0,
            'data' => [
                'shipments' => $shipments,
                'stats' => $stats,
            ],
        ]);
    }
}
