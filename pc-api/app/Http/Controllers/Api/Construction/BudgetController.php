<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\ProjectBudget;
use App\Services\ProjectBudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    public function __construct(private ProjectBudgetService $service) {}

    // 1. 列表
    public function index(Request $request): JsonResponse
    {
        $query = ProjectBudget::with(['project:id,name', 'creator:id,name', 'approver:id,name']);
        if ($projectId = $request->input('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($keyword = $request->input('keyword')) {
            $query->where('code', 'like', "%{$keyword}%");
        }
        $list = $query->orderByDesc('id')->paginate($request->input('page_size', 20));

        return response()->json(['code' => 0, 'data' => $list]);
    }

    // 2. 项目预算汇总
    public function summary(int $projectId): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->service->getSummary($projectId),
        ]);
    }

    // 3. 创建
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', Rule::in(['material', 'labor', 'outsource', 'other'])],
            'items.*.item_name' => ['required', 'string', 'max:128'],
            'items.*.specification' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:16'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.planned_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'items.*.item_type' => ['nullable', 'string', 'max:32'],
            'items.*.sort_order' => ['nullable', 'integer'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->ensureMaterialItemsFromInventory($validated['items']);

        $budget = $this->service->createBudget(
            projectId: $validated['project_id'],
            data: $validated,
            userId: $request->user()->id,
        );

        return response()->json(['code' => 0, 'data' => $budget->load('items')]);
    }

    // 4. 详情
    public function show(int $id): JsonResponse
    {
        $budget = ProjectBudget::with(['items', 'project:id,name', 'creator:id,name', 'approver:id,name'])
            ->findOrFail($id);

        // 加载实际成本流水（前 20 条）
        $actualFlows = \App\Models\ProjectActualCost::where('project_id', $budget->project_id)
            ->orderByDesc('cost_date')
            ->limit(20)
            ->get();

        return response()->json([
            'code' => 0,
            'data' => [
                'budget' => $budget,
                'actual_flows' => $actualFlows,
            ],
        ]);
    }

    // 5. 更新（仅 draft）
    public function update(Request $request, int $id): JsonResponse
    {
        $budget = ProjectBudget::findOrFail($id);
        if ($budget->status !== 'draft') {
            return response()->json(['code' => 1, 'msg' => '只有草稿状态可编辑'], 422);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', Rule::in(['material', 'labor', 'outsource', 'other'])],
            'items.*.item_name' => ['required', 'string', 'max:128'],
            'items.*.specification' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:16'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.planned_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'items.*.item_type' => ['nullable', 'string', 'max:32'],
            'items.*.sort_order' => ['nullable', 'integer'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->ensureMaterialItemsFromInventory($validated['items']);

        $this->service->updateBudgetItems($budget, $validated['items']);
        $budget->update(['remark' => $validated['remark'] ?? null]);

        return response()->json(['code' => 0, 'data' => $budget->fresh()->load('items')]);
    }

    // 6. 审批
    public function approve(Request $request, int $id): JsonResponse
    {
        $budget = ProjectBudget::findOrFail($id);
        if ($budget->status !== 'draft') {
            return response()->json(['code' => 1, 'msg' => '只有草稿状态可审批'], 422);
        }
        if ((int) $budget->created_by === (int) $request->user()->id) {
            return response()->json(['code' => 1, 'msg' => '预算创建人不能审批自己的预算'], 403);
        }

        $budget = $this->service->approveBudget($budget, $request->user()->id);

        return response()->json(['code' => 0, 'data' => $budget]);
    }

    // 7. 修订
    public function revise(Request $request, int $id): JsonResponse
    {
        $old = ProjectBudget::findOrFail($id);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.category' => ['required', Rule::in(['material', 'labor', 'outsource', 'other'])],
            'items.*.item_name' => ['required', 'string', 'max:128'],
            'items.*.specification' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:16'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.planned_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'items.*.item_type' => ['nullable', 'string', 'max:32'],
            'items.*.sort_order' => ['nullable', 'integer'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->ensureMaterialItemsFromInventory($validated['items']);

        $new = $this->service->reviseBudget($old, $validated['items'], $request->user()->id);
        $new->update(['remark' => $validated['remark'] ?? null]);

        return response()->json(['code' => 0, 'data' => $new->load('items')]);
    }

    // 8. 删除（仅 draft）
    public function destroy(int $id): JsonResponse
    {
        $budget = ProjectBudget::findOrFail($id);
        if ($budget->status !== 'draft') {
            return response()->json(['code' => 1, 'msg' => '只有草稿状态可删除'], 422);
        }
        $budget->items()->delete();
        $budget->delete();

        return response()->json(['code' => 0]);
    }

    private function ensureMaterialItemsFromInventory(array $items): void
    {
        foreach ($items as $index => $item) {
            if (($item['category'] ?? null) === 'material' && empty($item['item_id'])) {
                abort(response()->json([
                    'code' => 1,
                    'msg' => '材料费明细必须从库存信息中选择',
                    'errors' => ["items.{$index}.item_id" => ['材料费明细必须从库存信息中选择']],
                ], 422));
            }
        }
    }
}
