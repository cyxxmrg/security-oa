<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\StoreApprovalTemplateRequest;
use App\Models\ApprovalTemplate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * 审批流程模板 CRUD
 *
 * GET    /api/approval-templates         列表（可按 module 过滤）
 * POST   /api/approval-templates         新建
 * GET    /api/approval-templates/{id}    详情
 * PUT    /api/approval-templates/{id}    更新
 * DELETE /api/approval-templates/{id}    删除
 * POST   /api/approval-templates/{id}/toggle  启停切换
 */
class ApprovalTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // V0.9.3: 审批模板读多写少, 5min 缓存
        $module = $request->query('module');
        $cacheKey = 'approval-templates:' . ($module ?? 'all');
        $rows = \Cache::remember($cacheKey, 300, function () use ($module) {
            $query = ApprovalTemplate::with(['creator', 'updater'])->orderBy('id');
            if ($module) {
                $query->where('module', $module);
            }
            return $query->get()->map(function (ApprovalTemplate $t) {
                $steps = is_array($t->steps) ? $t->steps : [];
                return [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'module'     => $t->module,
                    'description'=> $t->description ?? '',
                    'nodes'      => $steps,
                    'nodeCount'  => count($steps),
                    'status'     => $t->enabled ? '启用' : '停用',
                    'updatedBy'  => $t->updater?->name ?? $t->creator?->name ?? '—',
                    'updatedAt'  => $t->updated_at?->format('Y-m-d H:i:s'),
                    'createdAt'  => $t->created_at?->format('Y-m-d H:i:s'),
                ];
            });
        });
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    public function show(ApprovalTemplate $approvalTemplate): JsonResponse
    {
        $approvalTemplate->load(['creator', 'updater']);
        $t = $approvalTemplate;
        $steps = is_array($t->steps) ? $t->steps : [];
        return response()->json(['code' => 0, 'data' => [
            'id'         => $t->id,
            'name'       => $t->name,
            'module'     => $t->module,
            'description'=> $t->description ?? '',
            'nodes'      => $steps,
            'nodeCount'  => count($steps),
            'status'     => $t->enabled ? '启用' : '停用',
            'updatedBy'  => $t->updater?->name ?? $t->creator?->name ?? '—',
            'updatedAt'  => $t->updated_at?->format('Y-m-d H:i:s'),
        ]]);
    }

    public function store(StoreApprovalTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        // 兼容前端传 nodes (第一步表单用 nodes 字段)
        $data['steps'] = $data['steps'] ?? ($request->input('nodes', []));
        $data['enabled']    = $data['enabled'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['created_by'] = $request->user()?->id;

        $t = ApprovalTemplate::create($data);
        Cache::forget('approval-templates:all');
        return response()->json(['code' => 0, 'message' => '流程模板已创建', 'data' => ['id' => $t->id]]);
    }

    public function update(StoreApprovalTemplateRequest $request, ApprovalTemplate $approvalTemplate): JsonResponse
    {
        $data = $request->validated();
        $data['steps'] = $data['steps'] ?? ($request->input('nodes', $approvalTemplate->steps));
        $approvalTemplate->fill($data)->save();
        Cache::forget('approval-templates:all');
        return response()->json(['code' => 0, 'message' => '流程模板已更新']);
    }

    public function destroy(ApprovalTemplate $approvalTemplate): JsonResponse
    {
        $approvalTemplate->delete();
        Cache::forget('approval-templates:all');
        return response()->json(['code' => 0, 'message' => '流程模板已删除']);
    }

    public function toggle(ApprovalTemplate $approvalTemplate): JsonResponse
    {
        $newEnabled = ! $approvalTemplate->enabled;
        $approvalTemplate->enabled = $newEnabled;
        $approvalTemplate->save();
        Cache::forget('approval-templates:all');
        $label = $newEnabled ? '启用' : '停用';
        return response()->json(['code' => 0, 'message' => "已切换为{$label}", 'data' => ['status' => $label, 'enabled' => $newEnabled]]);
    }
}
