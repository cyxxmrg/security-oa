<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesApproval;
use App\Models\ApprovalRecord;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 财务审批（费用报销 / 付款单 / 应收应付 / 采购付款 / 居间费 / 薪资调整 / 差旅 / 借款 / 其他）
 *
 * GET    /api/approvals/finance                        列表
 * POST   /api/approvals/finance                        新建
 * GET    /api/approvals/finance/{approval}             详情
 * POST   /api/approvals/finance/{approval}/approve     通过
 * POST   /api/approvals/finance/{approval}/reject      拒绝
 * POST   /api/approvals/finance/{approval}/forward     转交
 */
class FinanceApprovalController extends Controller
{
    use HandlesApproval;

    public function index(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request, 'finance')->paginate($this->perPage($request));
        return response()->json(['code' => 0, 'data' => $this->transformPaginated($rows)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sub_type'     => 'required|string|max:50',
            'title'        => 'required|string|max:255',
            'priority'     => 'nullable|in:urgent,high,normal,low',
            'amount'       => 'nullable|numeric|min:0',
            'bank_account' => 'nullable|string|max:200',
            'payload'      => 'nullable|array',
            'cc'           => 'nullable|array',
        ]);

        $userId = $request->user()?->id;
        $record = ApprovalRecord::create([
            'code'         => $this->nextCode('FIN'),
            'type'         => 'finance',
            'sub_type'     => $data['sub_type'],
            'title'        => $data['title'],
            'priority'     => $data['priority'] ?? 'normal',
            'status'       => ApprovalRecord::STATUS_PENDING,
            'amount'       => $data['amount'] ?? 0,
            'bank_account' => $data['bank_account'] ?? null,
            'applicant_id' => $userId,
            'payload'      => $data['payload'] ?? [],
            'flow'         => [[
                'operator' => User::find($userId)?->name ?? '—',
                'action'   => 'submit',
                'time'     => now()->toDateTimeString(),
                'comment'  => '提交申请',
            ]],
            'cc'           => $data['cc'] ?? [],
        ]);

        return response()->json([
            'code'    => 0,
            'message' => '财务审批已提交',
            'data'    => ['id' => $record->id, 'code' => $record->code],
        ]);
    }

    public function show(ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'finance', 404, '资源不存在或参数错误');
        abort_unless($this->canCurrentUserView($approval), 403, '无权查看该审批单');
        return response()->json(['code' => 0, 'data' => $this->transform($approval)]);
    }

    public function approve(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'finance', 404, '资源不存在或参数错误');
        if ($approval->status !== ApprovalRecord::STATUS_PENDING) {
            return response()->json(['code' => 1, 'message' => '该审批已结束，无法操作'], 422);
        }
        if (!$this->canCurrentUserApprove($approval)) {
            return response()->json(['code' => 1, 'message' => '当前用户无权审批该单 (申请人不能审批自己的单)'], 403);
        }

        $comment = $request->input('comment', '同意');
        $user = $request->user();

        // 按模板推进审批流程（非模板/最后节点才标记 approved）
        $flowService = app(ApprovalFlowService::class);
        $result = $flowService->advanceFlow($approval, $user, $comment);

        $approval->flow = $result['flow'];
        $approval->status = $result['status'];
        $approval->current_approver_id = $result['current_approver_id'];
        $approval->comment = $comment;
        $approval->save();

        // 只有最终 approved 时才同步业务表
        if ($result['status'] === ApprovalRecord::STATUS_APPROVED) {
            try {
                $payload = $approval->payload ?? [];
                if ($approval->sub_type === 'expense' && !empty($payload['claim_id'])) {
                    \App\Models\ExpenseClaim::where('id', $payload['claim_id'])
                        ->where('status', 'submitted')
                        ->update([
                            'status'        => 'approved',
                            'approver_id'   => $user->id,
                            'approved_at'   => now(),
                            'reject_reason' => null,
                        ]);
                }
            } catch (\Throwable $e) {
                \Log::error('FinanceApprovalController::approve sync business status failed', ['msg' => $e->getMessage()]);
            }
        }

        $msg = $result['status'] === ApprovalRecord::STATUS_APPROVED ? '已通过（全部审批节点已完成）' : '已通过，已转交下一节点';
        return response()->json(['code' => 0, 'message' => $msg, 'data' => ['status' => $approval->status]]);
    }

    public function reject(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'finance', 404, '资源不存在或参数错误');
        if ($approval->status !== ApprovalRecord::STATUS_PENDING) {
            return response()->json(['code' => 1, 'message' => '该审批已结束，无法操作'], 422);
        }
        if (!$this->canCurrentUserApprove($approval)) {
            return response()->json(['code' => 1, 'message' => '当前用户无权审批该单 (申请人不能审批自己的单)'], 403);
        }

        $request->validate(['comment' => 'required|string|max:500']);
        $user = $request->user();
        $comment = $request->input('comment');

        // 按模板驳回
        $flowService = app(ApprovalFlowService::class);
        $result = $flowService->rejectFlow($approval, $user, $comment);

        $approval->flow = $result['flow'];
        $approval->status = $result['status'];
        $approval->current_approver_id = $result['current_approver_id'];
        $approval->save();

        return response()->json(['code' => 0, 'message' => '已驳回', 'data' => ['status' => $approval->status]]);
    }

    public function forward(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'finance', 404, '资源不存在或参数错误');
        if ($approval->status !== ApprovalRecord::STATUS_PENDING) {
            return response()->json(['code' => 1, 'message' => '该审批已结束，无法操作'], 422);
        }
        if (!$this->canCurrentUserApprove($approval)) {
            return response()->json(['code' => 1, 'message' => '当前用户无权转交该单'], 403);
        }

        $request->validate(['target' => 'required|string|max:100']);
        $target = $request->input('target');
        $this->appendFlow($approval, 'transfer', "转交给 {$target}");
        $approval->current_approver_id = null;
        $approval->status  = ApprovalRecord::STATUS_TRANSFERRED;
        $approval->comment = "已转交：{$target}";
        $approval->save();

        return response()->json(['code' => 0, 'message' => "已转交 {$target}"]);
    }
}
