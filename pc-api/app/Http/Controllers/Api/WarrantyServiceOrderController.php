<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarrantyServiceOrder;
use App\Services\WarrantyServiceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.5 质保服务工单控制器
 *
 * 路由前缀 /api/warranty-service-orders
 *  1. GET    /warranty-service-orders              列表
 *  2. POST   /warranty-service-orders              创建
 *  3. GET    /warranty-service-orders/{id}         详情
 *  4. POST   /warranty-service-orders/{id}/assign  派单
 *  5. POST   /warranty-service-orders/{id}/start   开始
 *  6. POST   /warranty-service-orders/{id}/complete 完工
 *  7. POST   /warranty-service-orders/{id}/cancel  取消
 *  8. GET    /warranty-service-orders/technician-stats  技工统计
 */
class WarrantyServiceOrderController extends Controller
{
    public function __construct(protected WarrantyServiceOrderService $service) {}

    // 1. 列表
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'warranty_id'    => $request->input('warranty_id'),
            'technician_id'  => $request->input('technician_id'),
            'status'         => $request->input('status'),
            'service_type'   => $request->input('service_type'),
            'priority'       => $request->input('priority'),
            'keyword'        => $request->input('keyword'),
        ];

        $result = $this->service->listOrders(
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 20),
            $filters
        );

        return response()->json([
            'code' => 0,
            'data' => [
                'items' => $result['items'],
                'total' => $result['total'],
                'current_page' => (int) $request->input('page', 1),
                'per_page' => (int) $request->input('per_page', 20),
            ],
        ]);
    }

    // 2. 创建
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warranty_id'   => ['nullable', 'integer', 'exists:warranties,id'],
            'customer_id'   => ['nullable', 'integer', 'exists:customers,id'],
            'device_id'     => ['nullable', 'integer', 'exists:customer_devices,id'],
            'service_type'  => ['required', Rule::in(['inspect', 'repair', 'clean', 'calibrate', 'replace'])],
            'priority'      => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'title'         => ['required', 'string', 'max:200'],
            'description'   => ['required', 'string', 'max:2000'],
            'scheduled_date'=> ['required', 'date'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $order = $this->service->createOrder($validated, $request->user()->id);
            return response()->json(['code' => 0, 'data' => $order, 'message' => '服务工单创建成功'], 201);
        } catch (\Throwable $e) {
            \Log::error('创建服务工单失败', ['err' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['code' => 1, 'message' => '创建失败: ' . $e->getMessage()], 422);
        }
    }

    // 3. 详情
    public function show(int $id): JsonResponse
    {
        $order = $this->service->getOrder($id);
        return response()->json(['code' => 0, 'data' => $order]);
    }

    // 4. 派单
    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $order = $this->service->assignTechnician($id, (int) $validated['technician_id'], $request->user()->id);
            return response()->json(['code' => 0, 'data' => $order, 'message' => '派单成功']);
        } catch (\Throwable $e) {
            \Log::error('派单失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '派单失败: ' . $e->getMessage()], 422);
        }
    }

    // 5. 开始
    public function start(int $id): JsonResponse
    {
        try {
            $order = $this->service->startOrder($id, request()->user()->id);
            return response()->json(['code' => 0, 'data' => $order, 'message' => '服务已开始']);
        } catch (\Throwable $e) {
            \Log::error('开始服务失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '开始失败: ' . $e->getMessage()], 422);
        }
    }

    // 6. 完工
    public function complete(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'result_notes'       => ['required', 'string', 'max:2000'],
            'customer_signature' => ['nullable', 'string'],
            'fee'                => ['nullable', 'numeric', 'min:0'],
            'parts_cost'         => ['nullable', 'numeric', 'min:0'],
            'charge_type'        => ['nullable', Rule::in(['warranty_free', 'contract_free', 'cash', 'credit'])],
            'project_id'         => ['nullable', 'integer', 'exists:projects,id'],
            'completed_date'     => ['nullable', 'date'],
        ]);

        // V1.2.7j: 保内免费 / 维保免费 / 记账收费 必须关联项目 (用于业务和资金统计)
        if (!empty($validated['charge_type']) && in_array($validated['charge_type'], ['warranty_free', 'contract_free', 'credit'], true)) {
            if (empty($validated['project_id'])) {
                return response()->json([
                    'code'    => 1001,
                    'message' => '收费类型「' . (WarrantyServiceOrder::CHARGE_TYPE_LABELS[$validated['charge_type']] ?? $validated['charge_type']) . '」必须关联项目',
                ], 422);
            }
        }

        try {
            $order = $this->service->completeOrder($id, $validated, $request->user()->id);
            return response()->json(['code' => 0, 'data' => $order, 'message' => '服务工单已完工']);
        } catch (\Throwable $e) {
            \Log::error('完工失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '完工失败: ' . $e->getMessage()], 422);
        }
    }

    // 7. 取消
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $order = $this->service->cancelOrder($id, $validated['reason'], $request->user()->id);
            return response()->json(['code' => 0, 'data' => $order, 'message' => '服务工单已取消']);
        } catch (\Throwable $e) {
            \Log::error('取消失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '取消失败: ' . $e->getMessage()], 422);
        }
    }

    // 8. 技工统计
    public function technicianStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'integer'],
        ]);

        try {
            $stats = $this->service->getTechnicianStats((int) $validated['technician_id']);
            return response()->json(['code' => 0, 'data' => $stats]);
        } catch (\Throwable $e) {
            \Log::error('技工统计失败', ['err' => $e->getMessage()]);
            return response()->json(['code' => 1, 'message' => '统计失败: ' . $e->getMessage()], 422);
        }
    }
}
