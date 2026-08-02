<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchasePlan;
use App\Models\ApprovalRecord;
use App\Models\User;
use App\Http\Requests\Purchase\StorePurchasePlanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $plan->update($data);
        return response()->json(['code' => 0, 'data' => $plan->fresh()]);
    }

    public function destroy(PurchasePlan $plan): JsonResponse
    {
        if ($plan->status === 'approved') {
            return response()->json(['code' => 1, 'message' => '已审批的计划不可删除'], 409);
        }
        $plan->delete();
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function submit(Request $request, PurchasePlan $plan): JsonResponse
    {
        if ($plan->status !== 'draft') {
            return response()->json(['code' => 1, 'message' => '只有草稿状态可提交'], 409);
        }

        $plan->update([
            'status'       => 'submitted',
            'submitter_id' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        // V1.2.5: 同步创建审批中心记录 (operation/purchase_plan) - 按模板
        try {
            $year = now()->format('Y');
            $seq = ApprovalRecord::where('code', 'like', "OPS-{$year}-%")->count() + 1;
            $code = sprintf('OPS-%s-%04d', $year, $seq);
            $applicant = User::find($request->user()->id);
            $exists = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_plan')
                ->where('payload->plan_id', $plan->id)
                ->exists();
            if (!$exists) {
                $flowService = app(\App\Services\ApprovalFlowService::class);
                $template = $flowService->resolveTemplate('purchase_plan');
                $flowData = $template
                    ? $flowService->initFlow($template, $applicant, '提交采购计划审批')
                    : ['current_approver_id' => 1, 'flow' => [[
                        'operator' => $applicant?->name ?? '—',
                        'action'   => 'submit',
                        'time'     => now()->toDateTimeString(),
                        'comment'  => '提交采购计划审批',
                    ]]];

                ApprovalRecord::create([
                    'code'                => $code,
                    'type'                => 'operation',
                    'sub_type'            => 'purchase_plan',
                    'title'               => '[采购计划] ' . ($plan->plan_no ?? '#' . $plan->id) . ' 审批 (¥' . number_format($plan->total_amount ?? 0, 2) . ')',
                    'priority'            => 'normal',
                    'status'              => ApprovalRecord::STATUS_PENDING,
                    'amount'              => $plan->total_amount ?? 0,
                    'applicant_id'        => $request->user()->id,
                    'current_approver_id' => $flowData['current_approver_id'],
                    'payload'             => [
                        'plan_id'     => $plan->id,
                        'plan_no'     => $plan->plan_no,
                        'title'       => $plan->title ?? null,
                        'total_amount'=> $plan->total_amount,
                    ],
                    'flow'                => $flowData['flow'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('PurchasePlan::submit sync approval failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'data' => $plan->fresh()]);
    }

    public function approve(Request $request, PurchasePlan $plan): JsonResponse
    {
        if ($plan->status !== 'submitted') {
            return response()->json(['code' => 1, 'message' => '只有已提交状态可审批'], 409);
        }

        $data = $request->validate([
            'decision' => 'required|string|in:approve,reject',
            'remark'   => 'nullable|string|max:500',
        ]);

        $plan->update([
            'status'         => $data['decision'] === 'approve' ? 'approved' : 'rejected',
            'approver_id'    => $request->user()->id,
            'approved_at'    => now(),
            'approve_remark' => $data['remark'] ?? null,
        ]);

        // V1.2.5: 同步更新审批中心记录（按模板推进）
        try {
            $approval = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_plan')
                ->where('payload->plan_id', $plan->id)
                ->first();
            if ($approval) {
                $user = User::find($request->user()->id);
                $comment = $data['remark'] ?? ($data['decision'] === 'approve' ? '同意' : '驳回');
                $flowService = app(\App\Services\ApprovalFlowService::class);

                if ($data['decision'] === 'approve') {
                    $result = $flowService->advanceFlow($approval, $user, $comment);
                } else {
                    $result = $flowService->rejectFlow($approval, $user, $comment);
                }

                $approval->flow = $result['flow'];
                $approval->status = $result['status'];
                $approval->current_approver_id = $result['current_approver_id'];
                $approval->comment = $comment;
                $approval->save();
            }
        } catch (\Throwable $e) {
            Log::error('PurchasePlan::approve sync approval failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'data' => $plan->fresh()]);
    }
}
