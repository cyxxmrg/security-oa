<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\ConstructionTeam;
use App\Services\ConstructionTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.3 施工团队控制器
 *
 * 路由前缀 /api/construction/teams
 *  1. GET    /teams                            团队列表
 *  2. POST   /teams                            创建团队
 *  3. GET    /teams/{id}                       团队详情
 *  4. PUT    /teams/{id}                       更新团队
 *  5. DELETE /teams/{id}                       解散团队
 *  6. POST   /teams/{id}/members               批量添加成员
 *  7. DELETE /teams/{id}/members/{memberId}    移除成员
 */
class TeamController extends Controller
{
    public function __construct(protected ConstructionTeamService $service) {}

    // 1. 团队列表
    public function index(Request $request): JsonResponse
    {
        $query = ConstructionTeam::with(['project:id,name', 'creator:id:name']);

        if ($projectId = $request->input('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($teamType = $request->input('team_type')) {
            $query->where('team_type', $teamType);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($keyword = $request->input('keyword')) {
            $query->where('team_name', 'like', "%{$keyword}%");
        }

        $list = $query->orderByDesc('id')
            ->paginate($request->input('per_page', 20));

        return response()->json(['code' => 0, 'data' => $list]);
    }

    // 2. 创建团队
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'   => ['nullable', 'integer', 'exists:projects,id'],
            'team_name'    => ['required', 'string', 'max:100'],
            'team_type'    => ['required', Rule::in(['internal', 'outsource'])],
            'leader_name'  => ['required', 'string', 'max:50'],
            'leader_phone' => ['required', 'string', 'max:20'],
            'specialty'    => ['nullable', 'string', 'max:200'],
            'remark'       => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $projectId = !empty($validated['project_id']) ? (int) $validated['project_id'] : null;
            $team = $this->service->createTeam($projectId, $validated, $request->user()->id);
            return response()->json(['code' => 0, 'data' => $team, 'message' => '创建成功'], 201);
        } catch (\Throwable $e) {
            \Log::error('创建施工团队失败', ['err' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['code' => 1, 'message' => '创建失败: ' . $e->getMessage()], 422);
        }
    }

    // 3. 团队详情
    public function show(int $id): JsonResponse
    {
        $team = ConstructionTeam::with([
            'project:id,name',
            'creator:id,name',
            'members',
        ])->findOrFail($id);

        return response()->json(['code' => 0, 'data' => $team]);
    }

    // 4. 更新团队
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'project_id'   => ['nullable', 'integer', 'exists:projects,id'],
            'team_name'    => ['sometimes', 'string', 'max:100'],
            'leader_name'  => ['sometimes', 'string', 'max:50'],
            'leader_phone' => ['sometimes', 'string', 'max:20'],
            'specialty'    => ['nullable', 'string', 'max:200'],
            'status'       => ['sometimes', Rule::in(['active', 'inactive', 'disbanded'])],
            'remark'       => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $team = $this->service->updateTeam($id, $validated);
            return response()->json(['code' => 0, 'data' => $team, 'message' => '更新成功']);
        } catch (\Throwable $e) {
            \Log::error('更新施工团队失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '更新失败'], 422);
        }
    }

    // 5. 硬删团队（彻底删除，不是停用）
    public function destroy(int $id): JsonResponse
    {
        try {
            $team = \App\Models\ConstructionTeam::withTrashed()->findOrFail($id);
            $team->members()->delete();
            $team->forceDelete();
            return response()->json(['code' => 0, 'message' => '团队已删除']);
        } catch (\Throwable $e) {
            \Log::error('删除施工团队失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '删除失败'], 422);
        }
    }

    // 6. 添加成员（批量）
    public function addMembers(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'members'         => ['required', 'array', 'min:1'],
            'members.*.name'  => ['required', 'string', 'max:50'],
            'members.*.phone' => ['required', 'string', 'max:20'],
            'members.*.role'  => ['nullable', Rule::in(['foreman', 'worker', 'safety', 'leader', 'electrician', 'operator', 'temp'])],
            'members.*.id_card'=> ['nullable', 'string', 'max:18'],
        ]);

        try {
            $count = $this->service->addMembers($id, $validated['members']);
            return response()->json([
                'code'    => 0,
                'data'    => ['count' => $count],
                'message' => "成功添加 {$count} 名成员",
            ]);
        } catch (\Throwable $e) {
            \Log::error('添加团队成员失败', ['err' => $e->getMessage(), 'team_id' => $id]);
            return response()->json(['code' => 1, 'message' => '添加失败'], 422);
        }
    }

    // 7. 移除成员
    public function removeMember(int $teamId, int $memberId): JsonResponse
    {
        try {
            $this->service->removeMember($teamId, $memberId);
            return response()->json(['code' => 0, 'message' => '成员已移除']);
        } catch (\Throwable $e) {
            \Log::error('移除团队成员失败', ['err' => $e->getMessage(), 'team_id' => $teamId, 'member_id' => $memberId]);
            return response()->json(['code' => 1, 'message' => '移除失败'], 422);
        }
    }
}
