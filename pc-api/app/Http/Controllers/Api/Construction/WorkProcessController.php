<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\WorkProcess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.3 工序字典控制器
 *
 * 路由前缀 /api/construction/work-processes
 *  - GET    /work-processes          字典列表
 *  - POST   /work-processes          新增
 *  - PUT    /work-processes/{id}     更新
 *  - DELETE /work-processes/{id}     删除
 *
 * 字段：project_id (nullable 通用), name, sequence, description, estimated_hours, status
 */
class WorkProcessController extends Controller
{
    // 1. 字典列表
    public function index(Request $request): JsonResponse
    {
        $query = WorkProcess::query();

        if ($projectId = $request->input('project_id')) {
            // 列出该项目 + 通用 (NULL)
            $query->where(function ($q) use ($projectId) {
                $q->where('project_id', $projectId)
                  ->orWhereNull('project_id');
            });
        } elseif ($request->has('project_id') && $request->input('project_id') === '0') {
            // 只看通用
            $query->whereNull('project_id');
        }

        if ($keyword = $request->input('keyword')) {
            $query->where('name', 'like', "%{$keyword}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $list = $query->with(['project:id,name,project_no'])
            ->orderBy('project_id')   // NULL 排前（通用先）
            ->orderBy('sequence')
            ->orderBy('id')
            ->paginate($request->input('per_page', 50));

        return response()->json(['code' => 0, 'data' => $list]);
    }

    // 2. 新增
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'       => ['nullable', 'integer', 'exists:projects,id'],
            'name'             => ['required', 'string', 'max:50'],
            'sequence'         => ['nullable', 'integer'],
            'description'      => ['nullable', 'string', 'max:500'],
            'estimated_hours'  => ['nullable', 'numeric', 'min:0'],
            'status'           => ['nullable', Rule::in(['active', 'disabled'])],
        ]);

        try {
            $validated['status'] = $validated['status'] ?? 'active';
            $proc = WorkProcess::create($validated);
            return response()->json(['code' => 0, 'data' => $proc, 'message' => '创建成功'], 201);
        } catch (\Throwable $e) {
            \Log::error('创建工序字典失败', ['err' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['code' => 1, 'message' => '创建失败: ' . $e->getMessage()], 422);
        }
    }

    // 3. 更新
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name'             => ['sometimes', 'string', 'max:50'],
            'sequence'         => ['nullable', 'integer'],
            'description'      => ['nullable', 'string', 'max:500'],
            'estimated_hours'  => ['nullable', 'numeric', 'min:0'],
            'status'           => ['sometimes', Rule::in(['active', 'disabled'])],
        ]);

        try {
            $proc = WorkProcess::findOrFail($id);
            $proc->update($validated);
            return response()->json(['code' => 0, 'data' => $proc, 'message' => '更新成功']);
        } catch (\Throwable $e) {
            \Log::error('更新工序字典失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '更新失败'], 422);
        }
    }

    // 4. 删除
    public function destroy(int $id): JsonResponse
    {
        try {
            $proc = WorkProcess::findOrFail($id);
            $proc->delete();
            return response()->json(['code' => 0, 'message' => '已删除']);
        } catch (\Throwable $e) {
            \Log::error('删除工序字典失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '删除失败: ' . $e->getMessage()], 422);
        }
    }
}
