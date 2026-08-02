<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesApproval;
use App\Models\ApprovalRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 审批中心（统一聚合）— 前端工作台用
 *
 * GET /api/approvals/center        全部审批 (finance + operation + project) 合并分页
 * GET /api/approvals/center/stats  各类审批统计
 */
class ApprovalCenterController extends Controller
{
    use HandlesApproval;

    public function index(Request $request): JsonResponse
    {
        $q = ApprovalRecord::query()->orderByDesc('id');
        $this->applyApprovalScope($q, $request);

        if ($request->filled('type'))      $q->where('type', $request->type);
        if ($request->filled('sub_type'))  $q->where('sub_type', $request->sub_type);
        if ($request->filled('priority'))  $q->where('priority', $request->priority);
        if ($request->filled('status'))    $q->where('status', $request->status);
        if ($request->filled('applicant_id')) $q->where('applicant_id', $request->applicant_id);
        if ($request->filled('current_approver_id')) $q->where('current_approver_id', $request->current_approver_id);
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $q->where(function ($w) use ($kw) {
                $w->where('code', 'like', "%{$kw}%")->orWhere('title', 'like', "%{$kw}%");
            });
        }

        $rows = $q->paginate($this->perPage($request));
        return response()->json(['code' => 0, 'data' => $this->transformPaginated($rows)]);
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        // 各类总数
        $scoped = ApprovalRecord::query();
        $this->applyApprovalScope($scoped, $request);

        $byType = (clone $scoped)
            ->selectRaw('type, status, COUNT(*) as cnt')
            ->groupBy('type', 'status')
            ->get();

        $matrix = [
            'finance'   => ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'transferred' => 0],
            'operation' => ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'transferred' => 0],
            'project'   => ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'transferred' => 0],
        ];
        foreach ($byType as $row) {
            $type = $row->type;
            if (!isset($matrix[$type])) continue;
            $matrix[$type]['total'] += (int) $row->cnt;
            $status = $row->status;
            if (isset($matrix[$type][$status])) {
                $matrix[$type][$status] = (int) $row->cnt;
            }
        }

        // 待我审批（current_approver_id = me + status = pending）
        $myPending = 0;
        if ($userId) {
            $myPending = ApprovalRecord::where('current_approver_id', $userId)
                ->where('status', ApprovalRecord::STATUS_PENDING)
                ->count();
        }

        // 我发起的
        $myInitiated = 0;
        if ($userId) {
            $myInitiated = ApprovalRecord::where('applicant_id', $userId)->count();
        }

        // 我已审批（当前用户出现在 flow 节点里的 approve/reject 动作）
        $myHandled = 0;
        if ($userId) {
            $myHandled = ApprovalRecord::whereIn('status', [
                ApprovalRecord::STATUS_APPROVED,
                ApprovalRecord::STATUS_REJECTED,
                ApprovalRecord::STATUS_TRANSFERRED,
            ])->whereJsonContains('flow', ['operator' => $request->user()?->name])->count();
        }

        // 本月金额（财务类已通过的金额合计）
        $monthAmountQuery = ApprovalRecord::where('type', 'finance');
        $this->applyApprovalScope($monthAmountQuery, $request);
        $monthAmount = (float) $monthAmountQuery
            ->where('status', ApprovalRecord::STATUS_APPROVED)
            ->whereYear('updated_at', now()->format('Y'))
            ->whereMonth('updated_at', now()->format('n'))
            ->sum('amount');

        return response()->json([
            'code' => 0,
            'data' => [
                'byType'        => $matrix,
                'myPending'     => $myPending,
                'myInitiated'   => $myInitiated,
                'myHandled'     => $myHandled,
                'monthAmount'   => $monthAmount,
                'grandTotal'    => tap(ApprovalRecord::query(), fn ($q) => $this->applyApprovalScope($q, $request))->count(),
                'grandPending'  => tap(ApprovalRecord::where('status', ApprovalRecord::STATUS_PENDING), fn ($q) => $this->applyApprovalScope($q, $request))->count(),
                'generatedAt'   => now()->toDateTimeString(),
            ],
        ]);
    }
}
