<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockRecord;
use App\Models\ToolUsageOrder;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 工具使用单 — V1.3.3
 *
 * 单据头 CRUD + 领用(checkout)/退还(return) 流水（复用 InventoryService::toolMovement 库存引擎）
 */
class ToolUsageOrderController extends Controller
{
    public function __construct(private InventoryService $svc) {}

    public function index(Request $request): JsonResponse
    {
        $query = ToolUsageOrder::with(['warehouse:id,name', 'project:id,name', 'applicant:id,name'])
            ->withCount(['items as movement_count'])
            ->withSum(['items as checkout_qty' => fn ($q) => $q->where('type', 'tool_checkout')], 'quantity')
            ->withSum(['items as return_qty' => fn ($q) => $q->where('type', 'tool_return')], 'quantity');

        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', "%{$kw}%")
                  ->orWhereHas('applicant', fn ($x) => $x->where('name', 'like', "%{$kw}%"))
                  ->orWhereHas('warehouse', fn ($x) => $x->where('name', 'like', "%{$kw}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $list = $query->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json(['code' => 0, 'data' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'project_id'   => 'nullable|integer|exists:projects,id',
            'applicant_id' => 'nullable|integer|exists:users,id',
            'remark'       => 'nullable|string|max:500',
        ]);

        $order = ToolUsageOrder::create([
            'code'         => $this->nextCode(),
            'warehouse_id' => (int) $data['warehouse_id'],
            'project_id'   => $data['project_id'] ?? null,
            'applicant_id' => $data['applicant_id'] ?? $request->user()->id,
            'status'       => 'active',
            'remark'       => $data['remark'] ?? null,
            'created_by'   => $request->user()->id,
        ]);

        return response()->json(['code' => 0, 'data' => $order]);
    }

    public function show(ToolUsageOrder $toolUsageOrder): JsonResponse
    {
        $order = $toolUsageOrder->load([
            'warehouse:id,name',
            'project:id,name',
            'applicant:id,name',
            'creator:id,name',
        ]);

        $items = StockRecord::with(['inventoryItem:id,code,name,specification,unit', 'operator:id,name'])
            ->where('order_no', $order->code)
            ->orderByDesc('created_at')
            ->get();

        // 按工具汇总: 本单借出 = 累计领用 - 累计退还 (供右侧「在库信息」参考)
        $summary = $items->groupBy('inventory_item_id')->map(function ($rows) {
            $checkout = $rows->where('type', 'tool_checkout')->sum('quantity');
            $returned = $rows->where('type', 'tool_return')->sum('quantity');
            return [
                'inventory_item_id' => $rows->first()->inventory_item_id,
                'checkout_qty'      => (int) $checkout,
                'return_qty'        => (int) $returned,
                'borrowed'          => (int) $checkout - (int) $returned,
            ];
        })->values();

        return response()->json(['code' => 0, 'data' => [
            'order'         => $order,
            'items'         => $items,
            'summary'       => $summary,
            'total_checkout'=> (int) $items->where('type', 'tool_checkout')->sum('quantity'),
            'total_return'  => (int) $items->where('type', 'tool_return')->sum('quantity'),
        ]]);
    }

    public function checkout(Request $request, ToolUsageOrder $toolUsageOrder): JsonResponse
    {
        try {
            $result = $this->svc->toolMovement($request, $toolUsageOrder, 'checkout');
            return response()->json(['code' => 0, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function returnItem(Request $request, ToolUsageOrder $toolUsageOrder): JsonResponse
    {
        try {
            $result = $this->svc->toolMovement($request, $toolUsageOrder, 'return');
            return response()->json(['code' => 0, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function close(ToolUsageOrder $toolUsageOrder): JsonResponse
    {
        $toolUsageOrder->update(['status' => 'closed']);
        return response()->json(['code' => 0, 'data' => ['closed' => true]]);
    }

    public function destroy(ToolUsageOrder $toolUsageOrder): JsonResponse
    {
        if (StockRecord::where('order_no', $toolUsageOrder->code)->exists()) {
            return response()->json(['code' => 1, 'message' => '该使用单已有领用/退还流水, 不能删除, 只能关闭'], 409);
        }
        $toolUsageOrder->delete();
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    private function nextCode(): string
    {
        $prefix = 'TU-' . now()->format('Ymd') . '-';
        $last   = ToolUsageOrder::where('code', 'like', $prefix . '%')->orderByDesc('code')->value('code');
        $seq    = $last ? ((int) substr((string) $last, strrpos((string) $last, '-') + 1)) + 1 : 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
