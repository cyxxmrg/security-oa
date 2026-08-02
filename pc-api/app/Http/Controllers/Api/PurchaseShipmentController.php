<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 采购发货 (Shipment) — 3 端点
 *
 *  GET  /api/purchase/shipments             列表
 *  GET  /api/purchase/shipments/stats       统计
 *  GET  /api/purchase/shipments/{shipment}  详情（含 items + logistics）
 *
 * 注意：物流更新 / 轨迹查询归 PurchaseLogisticsController
 */
class PurchaseShipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseShipment::query();
        if ($request->filled('contract_id')) $query->where('contract_id', $request->contract_id);
        if ($request->filled('supplier_id')) $query->where('supplier_id', $request->supplier_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('carrier'))     $query->where('carrier', $request->carrier);
        if ($request->filled('date_from'))   $query->whereDate('shipped_at', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('shipped_at', '<=', $request->date_to);

        $perPage = (int) ($request->per_page ?? 15);
        return response()->json(['code' => 0, 'data' => $query->orderBy('shipped_at', 'desc')->paginate(max(1, min($perPage, 200)))]);
    }

    public function show(PurchaseShipment $shipment): JsonResponse
    {
        $shipment->load(['contract', 'supplier', 'items', 'logistics']);
        return response()->json(['code' => 0, 'data' => $shipment]);
    }

    public function stats(): JsonResponse
    {
        $rows = PurchaseShipment::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'code' => 0,
            'data' => [
                'shipped'    => $rows['shipped']    ?? 0,
                'in_transit' => $rows['in_transit'] ?? 0,
                'arrived'    => $rows['arrived']    ?? 0,
                'closed'     => $rows['closed']     ?? 0,
                'total'      => array_sum($rows),
            ],
        ]);
    }
}
