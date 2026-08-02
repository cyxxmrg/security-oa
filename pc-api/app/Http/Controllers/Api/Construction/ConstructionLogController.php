<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\ConstructionLog;
use App\Services\ConstructionLogService;
use App\Services\ProjectStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.3 施工日志（每日日志）控制器
 *
 * 路由前缀 /api/construction/logs
 *  1. GET    /logs                日志列表
 *  2. POST   /logs                提交日志
 *  3. GET    /logs/overdue        逾期未提交日志
 *  4. GET    /logs/{id}           日志详情
 *  5. PUT    /logs/{id}           更新日志
 *  6. POST   /logs/{id}/submit    提交（草稿→已提交）
 *  7. POST   /logs/{id}/progress  更新施工进度
 */
class ConstructionLogController extends Controller
{
    public function __construct(protected ConstructionLogService $service) {}

    // 1. 日志列表
    public function index(Request $request): JsonResponse
    {
        $query = ConstructionLog::with([
            'project:id,name',
            'team:id,team_name',
            'commencementOrder:id,code',
            'user:id,name',
        ]);

        if ($projectId = $request->input('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($orderId = $request->input('commencement_order_id')) {
            $query->where('commencement_order_id', $orderId);
        }
        if ($date = $request->input('work_date')) {
            $query->where('work_date', $date);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $list = $query->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 20));

        return response()->json(['code' => 0, 'data' => $list]);
    }

    // 2. 提交日志
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commencement_order_id' => ['nullable', 'integer'],
            'project_id'            => ['required', 'integer', 'exists:projects,id'],
            'work_date'             => ['required', 'date'],
            'weather'               => ['nullable', 'string', 'max:50'],
            'content'               => ['required', 'string', 'max:5000'],
            'progress_percentage'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'problems'              => ['nullable', 'string', 'max:2000'],
            'solutions'             => ['nullable', 'string', 'max:2000'],
            'photos'                => ['nullable', 'array'],
            'work_hours'            => ['nullable', 'numeric', 'min:0', 'max:24'],
            'worker_count'          => ['nullable', 'integer', 'min:0'],
            'team_id'               => ['nullable', 'integer'],
            'process_id'            => ['nullable', 'integer'],
        ]);

        try {
            $log = $this->service->submitLog($validated, $request->user()->id);
            // V1.2.12m: 首条施工日志 → 推进项目阶段
            (new ProjectStageService())->onFirstConstructionLog((int) $validated['project_id'], $request->user()->id);
            return response()->json(['code' => 0, 'data' => $log, 'message' => '日志已提交'], 201);
        } catch (\Throwable $e) {
            \Log::error('提交施工日志失败', ['err' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['code' => 1, 'message' => '提交失败: ' . $e->getMessage()], 422);
        }
    }

    // 3. 逾期日志（应提交但未提交）
    public function overdue(Request $request): JsonResponse
    {
        try {
            $list = $this->service->listOverdue($request->only(['project_id', 'days']));
            return response()->json(['code' => 0, 'data' => $list]);
        } catch (\Throwable $e) {
            \Log::error('获取逾期日志失败', ['err' => $e->getMessage()]);
            return response()->json(['code' => 1, 'message' => '查询失败'], 422);
        }
    }

    // 4. 日志详情
    public function show(int $id): JsonResponse
    {
        $log = ConstructionLog::with([
            'project:id,name',
            'team:id,team_name',
            'commencementOrder:id,code,team_id',
            'user:id,name',
        ])->findOrFail($id);

        return response()->json(['code' => 0, 'data' => $log]);
    }

    // 5. 更新日志（仅草稿）
    public function update(Request $request, int $id): JsonResponse
    {
        $log = ConstructionLog::findOrFail($id);
        if ($log->status !== 'draft') {
            return response()->json(['code' => 1, 'message' => '已提交的日志不可修改'], 422);
        }

        $validated = $request->validate([
            'weather'              => ['nullable', 'string', 'max:50'],
            'content'              => ['sometimes', 'string', 'max:5000'],
            'progress_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'problems'             => ['nullable', 'string', 'max:2000'],
            'solutions'            => ['nullable', 'string', 'max:2000'],
            'photos'               => ['nullable', 'array'],
            'work_hours'           => ['nullable', 'numeric', 'min:0', 'max:24'],
            'worker_count'         => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $log = $this->service->updateLog($id, $validated);
            return response()->json(['code' => 0, 'data' => $log, 'message' => '更新成功']);
        } catch (\Throwable $e) {
            \Log::error('更新施工日志失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '更新失败'], 422);
        }
    }

    // 6. 提交（草稿 → 已提交）
    public function submit(int $id): JsonResponse
    {
        try {
            $log = $this->service->submitLog(['work_date' => request()->input('work_date', now()->toDateString())], request()->user()->id, $id);
            return response()->json(['code' => 0, 'data' => $log, 'message' => '日志已提交']);
        } catch (\Throwable $e) {
            \Log::error('提交施工日志失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '提交失败: ' . $e->getMessage()], 422);
        }
    }

    // 7. 更新施工进度（轻量动作，区别于 update）
    public function updateProgress(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'process_id'      => ['required', 'integer'],
            'completed_qty'   => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $log = $this->service->updateProgress($id, (int) $validated['process_id'], (float) $validated['completed_qty']);
            return response()->json(['code' => 0, 'data' => $log, 'message' => '进度已更新']);
        } catch (\Throwable $e) {
            \Log::error('更新施工进度失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '更新失败'], 422);
        }
    }
}
