<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesApproval;
use App\Models\ApprovalRecord;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 项目审批（项目立项 / 阶段推进 / 关闭 / 合同 / 设计变更 / 结算 / 质保金 / 验收）
 *
 * GET    /api/approvals/project                    列表
 * POST   /api/approvals/project                    新建
 * GET    /api/approvals/project/{approval}         详情
 * POST   /api/approvals/project/{approval}/approve 通过
 * POST   /api/approvals/project/{approval}/reject  拒绝
 * POST   /api/approvals/project/{approval}/forward 转交
 */
class ProjectApprovalController extends Controller
{
    use HandlesApproval;

    public function index(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request, 'project')->paginate($this->perPage($request));
        return response()->json(['code' => 0, 'data' => $this->transformPaginated($rows)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sub_type'   => 'required|string|max:50',
            'title'      => 'required|string|max:255',
            'priority'   => 'nullable|in:urgent,high,normal,low',
            'amount'     => 'nullable|numeric|min:0',
            'to_stage'   => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'payload'    => 'nullable|array',
            'cc'         => 'nullable|array',
        ]);

        $applicant = $request->user();
        abort_unless($applicant, 401, '登录状态已失效');

        try {
            $record = \DB::transaction(function () use ($data, $applicant) {
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate($data['sub_type'], 'project');
            if (!$template) {
                throw new \DomainException('未找到该审批类型的启用流程模板，请先在审批流程引擎中配置');
            }
            $flowData = $flowService->initFlow($template, $applicant, '提交项目审批');

            return ApprovalRecord::create([
            'code'         => $this->nextCode('PRJ'),
            'type'         => 'project',
            'sub_type'     => $data['sub_type'],
            'title'        => $data['title'],
            'priority'     => $data['priority'] ?? 'normal',
            'status'       => ApprovalRecord::STATUS_PENDING,
            'amount'       => $data['amount'] ?? 0,
            'to_stage'     => $data['to_stage'] ?? null,
            'start_date'   => $data['start_date'] ?? null,
            'end_date'     => $data['end_date'] ?? null,
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
            'message' => '项目审批已提交',
            'data'    => ['id' => $record->id, 'code' => $record->code],
        ]);
    }

    public function show(ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'project', 404, '资源不存在或参数错误');
        abort_unless($this->canCurrentUserView($approval), 403, '无权查看该审批单');
        return response()->json(['code' => 0, 'data' => $this->transform($approval)]);
    }

    public function approve(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'project', 404, '资源不存在或参数错误');
        $comment = $request->input('comment', '同意');
        try {
            return \DB::transaction(function () use ($request, $approval, $comment) {
            $approval = ApprovalRecord::lockForUpdate()->findOrFail($approval->id);
            if ($approval->status !== ApprovalRecord::STATUS_PENDING) {
                return response()->json(['code' => 1, 'message' => '该审批已结束，无法操作'], 422);
            }
            if (!$this->canCurrentUserApprove($approval)) {
                return response()->json(['code' => 1, 'message' => '当前用户无权审批该单 (申请人不能审批自己的单)'], 403);
            }

            $result = app(ApprovalFlowService::class)->advanceFlow($approval, $request->user(), $comment);
            $approval->flow = $result['flow'];
            $approval->status = $result['status'];
            $approval->current_approver_id = $result['current_approver_id'];
            $approval->comment = $comment;
            $approval->save();

            $msg = $result['status'] === ApprovalRecord::STATUS_APPROVED ? '已通过（全部节点已完成）' : '已通过，已转交下一节点';
            return response()->json(['code' => 0, 'message' => $msg, 'data' => ['status' => $approval->status]]);
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'project', 404, '资源不存在或参数错误');
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

            return response()->json(['code' => 0, 'message' => '已驳回', 'data' => ['status' => $approval->status]]);
        });
    }

    public function forward(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'project', 404, '资源不存在或参数错误');
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
