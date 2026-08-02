<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\ExternalConstructionWork;
use App\Services\ExternalConstructionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.3 施工发包（对外承包）控制器
 *
 * 路由前缀 /api/construction/external-works
 *  1. GET    /external-works                  发包列表
 *  2. POST   /external-works                  发布发包
 *  3. GET    /external-works/{id}             详情
 *  4. PUT    /external-works/{id}             更新（仅 draft）
 *  5. POST   /external-works/{id}/close       关闭发包
 *  6. POST   /external-works/{id}/bids        供应商投标
 *  7. GET    /external-works/{id}/bids        投标列表
 *  8. POST   /external-works/{id}/award       中标
 */
class ExternalConstructionController extends Controller
{
    public function __construct(protected ExternalConstructionService $service) {}

    // 1. 发包列表
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['keyword', 'status', 'project_id', 'type', 'page', 'per_page']);
        // V1.2.9u fix: 不传 project_id 时应该列出所有, 不应该 where('project_id', 0) 过滤掉全部
        $projectId = (int) ($request->input('project_id') ?? 0);
        if (!$request->filled('project_id')) {
            $filters['_all_projects'] = true;  // 标记给 service, 不要加 project_id 过滤
        }
        $result = $this->service->listWorks($projectId, $filters);
        $items = $result['items'] ?? $result ?? collect();
        if ($items instanceof \Illuminate\Database\Eloquent\Collection) {
            $items = $items->all();
        }
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $offset = ($page - 1) * $perPage;
        $paged  = array_slice((array) $items, $offset, $perPage);

        return response()->json([
            'code' => 0,
            'data' => [
                'items'     => $paged,
                'total'     => is_array($items) ? count($items) : 0,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) ceil(count((array) $items) / max(1, $perPage)),
            ],
        ]);
    }

    // 2. 发布发包
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'      => ['required', 'integer', 'exists:projects,id'],
            'title'           => ['required', 'string', 'max:200'],
            'work_scope'      => ['nullable', 'string', 'max:2000'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'bid_type'        => ['nullable', 'string', Rule::in(['public', 'internal'])],
            'estimated_budget'=> ['nullable', 'numeric', 'min:0'],
            'budget'          => ['nullable', 'numeric', 'min:0'],
            'budget_amount'   => ['nullable', 'numeric', 'min:0'],
            'bid_deadline'    => ['nullable', 'date'],
            'deadline'        => ['nullable', 'date'],
            'start_date'      => ['nullable', 'date'],
            'end_date'        => ['nullable', 'date'],
            'requirements'    => ['nullable'],
            'required_skills' => ['nullable'],
            'attachments'     => ['nullable', 'array'],
        ]);

        try {
            $projectId = (int) $validated['project_id'];
            $work = $this->service->publishWork($projectId, $validated, $request->user()->id);
            return response()->json(['code' => 0, 'data' => $work, 'message' => '发包已发布'], 201);
        } catch (\Throwable $e) {
            \Log::error('创建施工发包失败', ['err' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['code' => 1, 'message' => '发布失败: ' . $e->getMessage()], 422);
        }
    }

    // 3. 详情
    public function show(int $id): JsonResponse
    {
        $work = ExternalConstructionWork::with([
            'project:id,name,project_no',
            'creator:id,name',
            'awardedSupplier:id,name,code',
        ])->findOrFail($id);

        return response()->json(['code' => 0, 'data' => $work]);
    }

    // 4. 更新（仅 draft / open 状态）
    public function update(Request $request, int $id): JsonResponse
    {
        $work = ExternalConstructionWork::findOrFail($id);
        if (!in_array($work->status, ['draft', 'open'], true)) {
            return response()->json(['code' => 1, 'message' => '该状态不可编辑'], 422);
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:200'],
            'type'        => ['sometimes', Rule::in(['outsource', 'subcontract', 'labor'])],
            'budget'      => ['sometimes', 'numeric', 'min:0'],
            'deadline'    => ['sometimes', 'date'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'requirements'=> ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $work = $this->service->updateWork($id, $validated);
            return response()->json(['code' => 0, 'data' => $work, 'message' => '更新成功']);
        } catch (\Throwable $e) {
            \Log::error('更新施工发包失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '更新失败'], 422);
        }
    }

    // 5. 关闭发包
    public function close(int $id): JsonResponse
    {
        try {
            $work = $this->service->cancelWork($id, request()->input('reason'));
            return response()->json(['code' => 0, 'data' => $work, 'message' => '发包已关闭']);
        } catch (\Throwable $e) {
            \Log::error('关闭施工发包失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '关闭失败: ' . $e->getMessage()], 422);
        }
    }

    // 6. 供应商投标
    public function submitBid(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'        => ['required', 'integer'],
            'bid_amount'         => ['required', 'numeric', 'min:0'],
            'bid_days'           => ['nullable', 'integer', 'min:1'],
            'duration_days'      => ['nullable', 'integer', 'min:1'],
            'proposal'           => ['nullable', 'string', 'max:3000'],
            'technical_proposal' => ['nullable', 'string', 'max:3000'],
            'construction_plan'  => ['nullable', 'string', 'max:3000'],
            'attachments'        => ['nullable', 'array'],
        ]);

        try {
            $bid = $this->service->submitBid(
                $id,
                (int) $validated['supplier_id'],
                $request->user()->id,
                $validated
            );
            return response()->json(['code' => 0, 'data' => $bid, 'message' => '投标成功'], 201);
        } catch (\Throwable $e) {
            \Log::error('施工发包投标失败', ['err' => $e->getMessage(), 'work_id' => $id]);
            return response()->json(['code' => 1, 'message' => '投标失败: ' . $e->getMessage()], 422);
        }
    }

    // 7. 投标列表
    public function listBids(int $id, Request $request): JsonResponse
    {
        try {
            $bids = $this->service->listBids($id, $request->only(['status', 'sort']));
            return response()->json(['code' => 0, 'data' => $bids]);
        } catch (\Throwable $e) {
            \Log::error('获取施工发包投标列表失败', ['err' => $e->getMessage(), 'work_id' => $id]);
            return response()->json(['code' => 1, 'message' => '查询失败'], 422);
        }
    }

    // 8. 中标
    public function award(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'bid_id'       => ['required', 'integer'],
            'award_remark' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $work = $this->service->awardWork($id, (int) $validated['bid_id'], $request->user()->id);
            return response()->json(['code' => 0, 'data' => $work, 'message' => '已定标']);
        } catch (\Throwable $e) {
            \Log::error('施工发包定标失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '定标失败: ' . $e->getMessage()], 422);
        }
    }
}
