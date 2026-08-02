<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\Rectification;
use App\Services\RectificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.3 整改工单控制器 — V0.4.4 占位
 *
 * ⚠ 完整功能（巡检→整改联动 / 整改验收 / 整改期限预警）在 V0.4.4 迭代实现
 *   V0.4.3 仅暴露基础 CRUD + complete 端点用于打通路由，不保证完整业务流
 *
 * 路由前缀 /api/construction/rectifications
 *  1. GET    /rectifications              整改单列表
 *  2. POST   /rectifications              创建整改单
 *  3. GET    /rectifications/{id}         详情
 *  4. POST   /rectifications/{id}/complete 完成整改
 */
class RectificationController extends Controller
{
    public function __construct(protected RectificationService $service) {}

    // 1. 整改单列表
    public function index(Request $request): JsonResponse
    {
        $query = Rectification::with([
            'project:id,name',
            'responsible:id,name',
            'creator:id,name',
        ]);

        if ($projectId = $request->input('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($severity = $request->input('severity')) {
            $query->where('severity', $severity);
        }
        if ($responsible = $request->input('responsible_id')) {
            $query->where('responsible_id', $responsible);
        }

        $list = $query->orderByDesc('id')
            ->paginate($request->input('per_page', 20));

        return response()->json(['code' => 0, 'data' => $list]);
    }

    // 2. 创建整改单
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'     => ['required', 'integer', 'exists:projects,id'],
            'source_type'    => ['required', Rule::in(['inspection', 'patrol', 'complaint', 'audit', 'other'])],
            'source_id'      => ['nullable', 'integer'],
            'title'          => ['required', 'string', 'max:200'],
            'description'    => ['required', 'string', 'max:2000'],
            'severity'       => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'deadline'       => ['nullable', 'date'],
            'responsible_id' => ['nullable', 'integer'],
            'images'         => ['nullable', 'array'],
        ]);

        try {
            $result = $this->service->createRectification(
                (int) $validated['project_id'],
                $validated,
                $request->user()->id
            );
            $rect = $result['rect'] ?? $result;
            return response()->json(['code' => 0, 'data' => $rect, 'message' => $result['message'] ?? '整改单已创建'], 201);
        } catch (\Throwable $e) {
            \Log::error('创建整改单失败', ['err' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['code' => 1, 'message' => '创建失败: ' . $e->getMessage()], 422);
        }
    }

    // 3. 详情
    public function show(int $id): JsonResponse
    {
        $rect = Rectification::with([
            'project:id,name',
            'responsible:id,name',
            'creator:id,name',
            'completer:id,name',
        ])->findOrFail($id);

        return response()->json(['code' => 0, 'data' => $rect]);
    }

    // 4. 完成整改（提交整改结果，等候 V0.4.4 验收）
    public function complete(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rectify_result'  => ['required', 'string', 'max:2000'],
            'complete_images' => ['nullable', 'array'],
        ]);

        try {
            $rect = Rectification::findOrFail($id);
            $log = $this->service->submitRectificationLog(
                $validated + [
                    'project_id'     => $rect->project_id,
                    'work_date'      => now()->toDateString(),
                    'content'        => $validated['rectify_result'],
                ],
                $request->user()->id,
                true,
                $id
            );
            // 同时更新 rectification 自身状态和完成图片
            $this->service->completeRectification($id, $validated, $request->user()->id);
            return response()->json([
                'code'    => 0,
                'data'    => $rect,
                'message' => '整改已提交，待 V0.4.4 验收',
            ]);
        } catch (\Throwable $e) {
            \Log::error('完成整改失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '操作失败: ' . $e->getMessage()], 422);
        }
    }
}
