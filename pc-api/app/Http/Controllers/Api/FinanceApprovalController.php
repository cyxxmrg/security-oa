<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesApproval;
use App\Models\ApprovalRecord;
use App\Models\ReferralSettlement;
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

        $applicant = $request->user();
        abort_unless($applicant, 401, '登录状态已失效');

        try {
            $record = \DB::transaction(function () use ($data, $applicant) {
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate($data['sub_type'], 'finance');
            if (!$template) {
                throw new \DomainException('未找到该审批类型的启用流程模板，请先在审批流程引擎中配置');
            }
            $flowData = $flowService->initFlow($template, $applicant, '提交财务审批');

            return ApprovalRecord::create([
            'code'         => $this->nextCode('FIN'),
            'type'         => 'finance',
            'sub_type'     => $data['sub_type'],
            'title'        => $data['title'],
            'priority'     => $data['priority'] ?? 'normal',
            'status'       => ApprovalRecord::STATUS_PENDING,
            'amount'       => $data['amount'] ?? 0,
            'bank_account' => $data['bank_account'] ?? null,
            'applicant_id' => $applicant->id,
            'current_approver_id' => $flowData['current_approver_id'],
            'payload'      => array_merge($data['payload'] ?? [], ['_approval_flow' => $flowData['definition']]),
            'flow'         => $flowData['flow'],
            'cc'           => $data['cc'] ?? [],
            ]);
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }

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
        try {
            return \DB::transaction(function () use ($request, $approval) {
                $approval = ApprovalRecord::lockForUpdate()->findOrFail($approval->id);
                if ($approval->status !== ApprovalRecord::STATUS_PENDING) {
                    return response()->json(['code' => 1, 'message' => '该审批已结束，无法操作'], 422);
                }
                if (!$this->canCurrentUserApprove($approval)) {
                    return response()->json(['code' => 1, 'message' => '当前用户无权审批该单 (申请人不能审批自己的单)'], 403);
                }

                $comment = $request->input('comment', '同意');
                $user = $request->user();
                $flowService = app(ApprovalFlowService::class);
                $result = $flowService->advanceFlow($approval, $user, $comment);

                $approval->flow = $result['flow'];
                $approval->status = $result['status'];
                $approval->current_approver_id = $result['current_approver_id'];
                $approval->comment = $comment;
                $approval->save();

                if ($result['status'] === ApprovalRecord::STATUS_APPROVED) {
                    $payload = $approval->payload ?? [];
                    if ($approval->sub_type === 'purchase_payment') {
                        app(\App\Services\PurchaseFlowService::class)
                            ->syncApprovalBusinessState($approval, $user, 'approved', $comment);
                    }
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
                    if ($approval->sub_type === 'referral_settlement' && !empty($payload['settlement_id'])) {
                        $updated = ReferralSettlement::whereKey($payload['settlement_id'])
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'approved',
                                'approved_by' => $user->id,
                                'approved_at' => now(),
                            ]);
                        if ($updated !== 1) {
                            throw new \RuntimeException('居间费结算单已变更，审批未同步');
                        }
                    }
                }

                $msg = $result['status'] === ApprovalRecord::STATUS_APPROVED ? '已通过（全部审批节点已完成）' : '已通过，已转交下一节点';
                return response()->json(['code' => 0, 'message' => $msg, 'data' => ['status' => $approval->status]]);
            });
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': approve failed', ['msg' => $e->getMessage()]);
            return response()->json(['code' => 1, 'message' => '审批失败: ' . $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'finance', 404, '资源不存在或参数错误');
        $request->validate(['comment' => 'required|string|max:500']);
        $comment = $request->input('comment');
        return \DB::transaction(function () use ($request, $approval, $comment) {
            $approval = ApprovalRecord::lockForUpdate()->findOrFail($approval->id);
            if ($approval->status !== ApprovalRecord::STATUS_PENDING) {
                return response()->json(['code' => 1, 'message' => '该审批已结束，无法操作'], 422);
            }
            if (!$this->canCurrentUserApprove($approval)) {
                return response()->json(['code' => 1, 'message' => '当前用户无权审批该单 (申请人不能审批自己的单)'], 403);
            }

            $result = app(ApprovalFlowService::class)->rejectFlow($approval, $request->user(), $comment);
            $approval->flow = $result['flow'];
            $approval->status = $result['status'];
            $approval->current_approver_id = $result['current_approver_id'];
            $approval->comment = $comment;
            $approval->save();

            if ($approval->sub_type === 'purchase_payment') {
                app(\App\Services\PurchaseFlowService::class)
                    ->syncApprovalBusinessState($approval, $request->user(), 'rejected', $comment);
            }
            if ($approval->sub_type === 'referral_settlement') {
                $payload = $approval->payload ?? [];
                if (!empty($payload['settlement_id'])) {
                    ReferralSettlement::whereKey($payload['settlement_id'])
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'cancelled',
                            'approved_by' => null,
                            'approved_at' => null,
                            'notes' => $comment,
                        ]);
                }
            }

            return response()->json(['code' => 0, 'message' => '已驳回', 'data' => ['status' => $approval->status]]);
        });
    }

    public function forward(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'finance', 404, '资源不存在或参数错误');
        $request->validate(['target' => 'required|string|max:100']);
        $target = $request->input('target');
        return \DB::transaction(function () use ($approval, $target) {
            $approval = ApprovalRecord::lockForUpdate()->findOrFail($approval->id);
            if ($approval->status !== ApprovalRecord::STATUS_PENDING) {
                return response()->json(['code' => 1, 'message' => '该审批已结束，无法操作'], 422);
            }
            if (!$this->canCurrentUserApprove($approval)) {
                return response()->json(['code' => 1, 'message' => '当前用户无权转交该单'], 403);
            }

            $targetUser = $this->resolveTransferTarget($approval, $target);
            $this->appendFlow($approval, 'transfer', "转交给 {$targetUser->name}");
            $approval->current_approver_id = $targetUser->id;
            $approval->status  = ApprovalRecord::STATUS_PENDING;
            $approval->comment = "已转交：{$targetUser->name}";
            $approval->save();

            return response()->json(['code' => 0, 'message' => "已转交 {$targetUser->name}"]);
        });
    }
}
