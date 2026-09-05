<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\PurchaseRequirement;
use App\Models\PurchasePlan;
use App\Models\PurchaseOrder;
use App\Http\Requests\Purchase\StorePurchasePlanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 采购计划 (Plan) — 7 端点
 *
 *  GET    /api/purchase/plans             列表
 *  POST   /api/purchase/plans             新建
 *  GET    /api/purchase/plans/stats       统计
 *  PUT    /api/purchase/plans/{plan}      更新
 *  DELETE /api/purchase/plans/{plan}      删除
 *  POST   /api/purchase/plans/{plan}/submit  提交审批
 *  POST   /api/purchase/plans/{plan}/approve 审批通过/拒绝
 */
class PurchasePlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchasePlan::query();
        if ($request->filled('project_id'))    $query->where('project_id', $request->project_id);
        if ($request->filled('requirement_id'))$query->where('requirement_id', $request->requirement_id);
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('priority'))      $query->where('priority', $request->priority);
        if ($request->filled('keyword'))       $query->where(function ($q) use ($request) {
            $kw = '%' . $request->keyword . '%';
            $q->where('code', 'like', $kw)->orWhere('title', 'like', $kw);
        });

        $perPage = (int) ($request->per_page ?? 15);
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate(max(1, min($perPage, 200)))]);
    }

    public function stats(): JsonResponse
    {
        $rows = PurchasePlan::query()
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total_amount),0) as amount')
            ->groupBy('status')
            ->get();

        $by = $rows->pluck('count', 'status')->toArray();
        $amountBy = $rows->pluck('amount', 'status')->toArray();
        return response()->json([
            'code' => 0,
            'data' => [
                'draft'     => $by['draft']     ?? 0,
                'submitted' => $by['submitted'] ?? 0,
                'approved'  => $by['approved']  ?? 0,
                'rejected'  => $by['rejected']  ?? 0,
                'cancelled' => $by['cancelled'] ?? 0,
                'total'     => array_sum($by),
                'total_amount' => array_sum($amountBy),
            ],
        ]);
    }

    public function store(StorePurchasePlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['project_id']) && !empty($data['requirement_id'])) {
            $data['project_id'] = PurchaseRequirement::findOrFail((int) $data['requirement_id'])->project_id;
        }
        $this->assertPlanLinks($data);

        $data['priority']     = $data['priority'] ?? 'medium';
        $data['total_amount'] = $data['total_amount'] ?? 0;
        $data['status']       = 'draft';
        $data['created_by']   = $request->user()->id;

        $plan = PurchasePlan::create($data);
        return response()->json(['code' => 0, 'data' => $plan]);
    }

    public function update(StorePurchasePlanRequest $request, PurchasePlan $plan): JsonResponse
    {
        if (in_array($plan->status, ['approved', 'submitted'])) {
            return response()->json(['code' => 1, 'message' => '草稿状态可编辑，提交后请走审批流'], 409);
        }

        $data = $request->validated();
        $this->assertPlanLinks($data, $plan);
        $updated = DB::transaction(function () use ($plan, $data) {
            $locked = PurchasePlan::lockForUpdate()->findOrFail($plan->id);
            if (in_array($locked->status, ['approved', 'submitted'])) {
                throw new \RuntimeException('草稿状态可编辑，提交后请走审批流');
            }
            if ($locked->status === 'rejected') {
                $locked->forceFill([
                    'status'         => 'draft',
                    'submitter_id'   => null,
                    'submitted_at'   => null,
                    'approver_id'    => null,
                    'approved_at'    => null,
                    'approve_remark' => null,
                ]);
            }
            $locked->fill($data)->save();
            return $locked->fresh();
        });
        return response()->json(['code' => 0, 'data' => $updated]);
    }

    public function destroy(PurchasePlan $plan): JsonResponse
    {
        if (!in_array($plan->status, ['draft', 'rejected', 'cancelled'], true)) {
            return response()->json(['code' => 1, 'message' => '只有草稿、驳回或已取消的计划可以删除'], 409);
        }
        $result = DB::transaction(function () use ($plan) {
            $locked = PurchasePlan::lockForUpdate()->findOrFail($plan->id);
            if (!in_array($locked->status, ['draft', 'rejected', 'cancelled'], true)) {
                return '只有草稿、驳回或已取消的计划可以删除';
            }
            if (PurchaseOrder::allData()->where('plan_id', $locked->id)->exists()
                || $locked->contracts()->exists()) {
                return '计划已生成采购单或合同，不可删除';
            }
            PurchaseRequirement::allData()
                ->where('merged_plan_id', $locked->id)
                ->where('status', 'merged')
                ->lockForUpdate()
                ->update([
                    'status' => 'approved',
                    'merged_plan_id' => null,
                    'merged_at' => null,
                ]);
            $locked->delete();
            return null;
        });
        if ($result) {
            return response()->json(['code' => 1, 'message' => $result], 409);
        }
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function submit(Request $request, PurchasePlan $plan): JsonResponse
    {
        try {
            $updated = app(\App\Services\PurchaseFlowService::class)->submitPlan($plan->id, $request->user());
            return response()->json(['code' => 0, 'data' => $updated]);
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, int $plan): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|string|in:approve,reject',
            'remark'   => 'nullable|string|max:500',
        ]);

        try {
            if ($data['decision'] === 'reject') {
                $service = app(\App\Services\ApprovalFlowService::class);
                $approval = \App\Models\ApprovalRecord::where('type', 'operation')
                    ->where('sub_type', 'purchase_plan')
                    ->whereJsonContains('payload->plan_id', $plan)
                    ->where('status', \App\Models\ApprovalRecord::STATUS_PENDING)
                    ->firstOrFail();
                $service->assertCurrentApprover($approval, $request->user());
                $result = \DB::transaction(function () use ($plan, $request, $data, $approval, $service) {
                    $locked = \App\Models\ApprovalRecord::lockForUpdate()->findOrFail($approval->id);
                    $flowResult = $service->rejectFlow($locked, $request->user(), $data['remark'] ?? '驳回');
                    $locked->forceFill([
                        'flow' => $flowResult['flow'],
                        'status' => $flowResult['status'],
                        'current_approver_id' => null,
                        'comment' => $data['remark'] ?? '驳回',
                    ])->save();
                    app(\App\Services\PurchaseFlowService::class)->syncApprovalBusinessState(
                        $locked,
                        $request->user(),
                        'rejected',
                        $data['remark'] ?? '驳回'
                    );
                    return PurchasePlan::allData()->findOrFail($plan);
                });
            } else {
                $result = app(\App\Services\PurchaseFlowService::class)->approvePlan($plan, $request->user(), $data['remark'] ?? '');
            }
            return response()->json(['code' => 0, 'data' => $result]);
        } catch (\DomainException|\RuntimeException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
    }

    private function assertPlanLinks(array $data, ?PurchasePlan $current = null): void
    {
        $projectId = array_key_exists('project_id', $data)
            ? $data['project_id']
            : $current?->project_id;
        if ($projectId) {
            Project::findOrFail((int) $projectId);
        }

        if (!array_key_exists('requirement_id', $data) || !$data['requirement_id']) {
            return;
        }
        $requirement = PurchaseRequirement::findOrFail((int) $data['requirement_id']);
        if ($requirement->status !== 'approved') {
            throw ValidationException::withMessages(['requirement_id' => '只有已审批的采购需求可以关联计划']);
        }
        if ($projectId && $requirement->project_id && (int) $projectId !== (int) $requirement->project_id) {
            throw ValidationException::withMessages(['project_id' => '采购计划项目与需求项目不匹配']);
        }
    }
}
