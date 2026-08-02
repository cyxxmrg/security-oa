<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesDataScope;
use App\Models\Warranty;
use App\Services\ProjectStageService;
use App\Services\WarrantyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.5 质保期控制器
 *
 * 路由前缀 /api/warranties
 *  1. GET    /warranties              质保期列表
 *  2. POST   /warranties              创建质保期
 *  3. GET    /warranties/{id}         质保期详情
 *  4. PUT    /warranties/{id}         更新质保期
 *  5. DELETE /warranties/{id}         软删质保期
 *  6. POST   /warranties/{id}/renew   续期
 *  7. POST   /warranties/{id}/terminate  终止
 *  8. GET    /warranties/expiring     即将到期列表
 */
class WarrantyController extends Controller
{
    use HandlesDataScope;

    public function __construct(protected WarrantyService $service) {}

    // 1. 质保期列表（分页 + 过滤）
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'project_id'  => $request->input('project_id'),
            'customer_id' => $request->input('customer_id'),
            'status'      => $request->input('status'),
            'warranty_type' => $request->input('warranty_type'),
            'keyword'     => $request->input('keyword'),
            'expired_within_days' => $request->input('expired_within_days'),
        ];

        $result = $this->service->listWarranties(
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

    // 2. 创建质保期
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'     => ['required', 'integer', 'exists:projects,id'],
            'customer_id'    => ['required', 'integer', 'exists:customers,id'],
            'device_id'      => ['nullable', 'integer', 'exists:customer_devices,id'],
            'start_date'     => ['required', 'date'],
            'period_months'  => ['nullable', 'integer', 'min:1', 'max:600'],
            'end_date'       => ['nullable', 'date', 'after:start_date'],
            'warranty_type'  => ['nullable', Rule::in(['basic', 'extended'])],
            'coverage_scope' => ['nullable', 'string', 'max:1000'],
            'terms'          => ['nullable', 'string', 'max:2000'],
            'remarks'        => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $warranty = $this->service->createWarranty($validated, $request->user()->id);
            // V1.2.12m: 创建质保期 → 推进项目阶段 settlement → warranty
            (new ProjectStageService())->onWarrantyCreated((int) $validated['project_id'], $request->user()->id);
            return response()->json(['code' => 0, 'data' => $warranty, 'message' => '质保期创建成功'], 201);
        } catch (\Throwable $e) {
            \Log::error('创建质保期失败', ['err' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['code' => 1, 'message' => '创建失败: ' . $e->getMessage()], 422);
        }
    }

    // 3. 质保期详情
    public function show(Request $request, int $id): JsonResponse
    {
        $warranty = $this->findScoped($request, Warranty::class, $id);
        return $warranty
            ? response()->json(['code' => 0, 'data' => $this->service->getWarranty($id)])
            : $this->respondNotFound($request, '质保期', $id);
    }

    // 4. 更新质保期
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'start_date'     => ['sometimes', 'date'],
            'period_months'  => ['sometimes', 'integer', 'min:1', 'max:600'],
            'end_date'       => ['sometimes', 'date'],
            'warranty_type'  => ['sometimes', Rule::in(['basic', 'extended'])],
            'coverage_scope' => ['nullable', 'string', 'max:1000'],
            'terms'          => ['nullable', 'string', 'max:2000'],
            'remarks'        => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $warranty = $this->service->updateWarranty($id, $validated, $request->user()->id);
            return response()->json(['code' => 0, 'data' => $warranty, 'message' => '更新成功']);
        } catch (\Throwable $e) {
            \Log::error('更新质保期失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '更新失败: ' . $e->getMessage()], 422);
        }
    }

    // 5. 软删质保期
    public function destroy(int $id): JsonResponse
    {
        try {
            Warranty::whereNull('deleted_at')->findOrFail($id)->delete();
            return response()->json(['code' => 0, 'message' => '质保期已删除']);
        } catch (\Throwable $e) {
            \Log::error('删除质保期失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '删除失败: ' . $e->getMessage()], 422);
        }
    }

    // 6. 续期
    public function renew(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'extend_months' => ['required', 'integer', 'min:1', 'max:600'],
        ]);

        try {
            $warranty = $this->service->renewWarranty($id, (int) $validated['extend_months'], $request->user()->id);
            return response()->json(['code' => 0, 'data' => $warranty, 'message' => '质保期已续期']);
        } catch (\Throwable $e) {
            \Log::error('质保期续期失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '续期失败: ' . $e->getMessage()], 422);
        }
    }

    // 7. 终止质保期
    public function terminate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $warranty = $this->service->terminateWarranty($id, $validated['reason'], $request->user()->id);
            // V1.2.12m: 质保期关闭 → 检查项目是否所有质保期都关闭 → closed
            (new ProjectStageService())->onAllWarrantyClosed((int) $warranty->project_id, $request->user()->id);
            return response()->json(['code' => 0, 'data' => $warranty, 'message' => '质保期已终止']);
        } catch (\Throwable $e) {
            \Log::error('质保期终止失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '终止失败: ' . $e->getMessage()], 422);
        }
    }

    // 8. 即将到期列表
    public function expiring(Request $request): JsonResponse
    {
        $withinDays = (int) $request->input('within_days', 30);
        $ids = $this->service->scanExpiringWarranties($withinDays);

        $list = \App\Models\Warranty::whereNull('deleted_at')
            ->whereIn('id', $ids ?: [0])
            ->with(['project:id,name', 'customer:id,name', 'device:id,device_name,serial_number'])
            ->withCount(['serviceOrders', 'renewals'])
            ->orderBy('end_date')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'code' => 0,
            'data' => $list,
            'meta' => [
                'within_days' => $withinDays,
                'total_expiring' => count($ids),
            ],
        ]);
    }
}
