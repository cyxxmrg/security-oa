<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\CommencementOrder;
use App\Services\CommencementOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.3 开工单控制器
 *
 * 路由前缀 /api/construction/commencement-orders
 *  1. GET    /commencement-orders              开工单列表
 *  2. POST   /commencement-orders              创建开工单
 *  3. GET    /commencement-orders/{id}         详情
 *  4. PUT    /commencement-orders/{id}         更新（仅草稿）
 *  5. POST   /commencement-orders/{id}/approve 审批
 *  6. POST   /commencement-orders/{id}/start   开工
 *  7. POST   /commencement-orders/{id}/complete 完工
 */
class CommencementOrderController extends Controller
{
    public function __construct(protected CommencementOrderService $service) {}

    // 1. 开工单列表
    public function index(Request $request): JsonResponse
    {
        $query = CommencementOrder::with([
            'project:id,name',
            'team:id,team_name',
            'creator:id,name',
            'approver:id,name',
        ]);

        if ($projectId = $request->input('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($teamId = $request->input('team_id')) {
            $query->where('team_id', $teamId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($keyword = $request->input('keyword')) {
            $query->where('code', 'like', "%{$keyword}%");
        }

        $list = $query->orderByDesc('id')
            ->paginate($request->input('per_page', 20));

        return response()->json(['code' => 0, 'data' => $list]);
    }

    // 2. 创建开工单
    public function store(Request $request): JsonResponse
    {
        // V0.5.8.3 修复: 表字段是 commencement_date + work_content,
        // 兼容老前端仍用 planned_start_date + work_scope
        $validated = $request->validate([
            'project_id'         => ['required', 'integer', 'exists:projects,id'],
            'team_id'            => ['nullable', 'integer'],
            'commencement_date'  => ['required_without:planned_start_date', 'date'],
            'planned_start_date' => ['required_without:commencement_date', 'date'],
            'planned_end_date'   => ['required', 'date'],
            'work_content'       => ['required_without:work_scope', 'string', 'max:2000'],
            'work_scope'         => ['required_without:work_content', 'string', 'max:2000'],
            'safety_requirements'=> ['nullable', 'string', 'max:2000'],
            'remark'             => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $order = $this->service->createOrder($projectId, $validated, $request->user()->id);
            return response()->json(['code' => 0, 'data' => $order, 'message' => '开工单已创建'], 201);
        } catch (\Throwable $e) {
            \Log::error('创建开工单失败', ['err' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['code' => 1, 'message' => '创建失败: ' . $e->getMessage()], 422);
        }
    }

    // 3. 详情
    public function show(int $id): JsonResponse
    {
        $order = CommencementOrder::with([
            'project:id,name',
            'team:id,team_name,leader_name',
            'creator:id,name',
            'approver:id,name',
        ])->findOrFail($id);

        return response()->json(['code' => 0, 'data' => $order]);
    }

    // 4. 更新（仅草稿状态可编辑）
    public function update(Request $request, int $id): JsonResponse
    {
        $order = CommencementOrder::findOrFail($id);
        if ($order->status !== 'draft') {
            return response()->json(['code' => 1, 'message' => '只有草稿状态可编辑'], 422);
        }

        $validated = $request->validate([
            'team_id'            => ['sometimes', 'integer'],
            'planned_start_date' => ['sometimes', 'date'],
            'planned_end_date'   => ['sometimes', 'date'],
            'work_scope'         => ['sometimes', 'string', 'max:2000'],
            'safety_requirements'=> ['nullable', 'string', 'max:2000'],
            'remark'             => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $order = $this->service->updateOrder($id, $validated);
            return response()->json(['code' => 0, 'data' => $order, 'message' => '更新成功']);
        } catch (\Throwable $e) {
            \Log::error('更新开工单失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '更新失败'], 422);
        }
    }

    // 5. 审批
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'approve_status' => ['nullable', Rule::in(['approved', 'rejected'])],
            'approve_remark' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $this->service->approve(
                $id,
                $request->user()->id,
                $validated['approve_remark'] ?? null
            );
            return response()->json(['code' => 0, 'data' => $order, 'message' => '审批完成']);
        } catch (\Throwable $e) {
            \Log::error('审批开工单失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '审批失败: ' . $e->getMessage()], 422);
        }
    }

    // 6. 开工
    public function startWork(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'actual_start_date' => ['nullable', 'date'],
            'remark'            => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $this->service->startWork($id, $validated);
            return response()->json(['code' => 0, 'data' => $order, 'message' => '已开工']);
        } catch (\Throwable $e) {
            \Log::error('开工失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '开工失败: ' . $e->getMessage()], 422);
        }
    }

    // 7. 完工
    public function complete(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'actual_end_date' => ['nullable', 'date'],
            'summary'         => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $order = $this->service->complete($id, $validated);
            return response()->json(['code' => 0, 'data' => $order, 'message' => '已完工']);
        } catch (\Throwable $e) {
            \Log::error('完工失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '完工失败: ' . $e->getMessage()], 422);
        }
    }
}
