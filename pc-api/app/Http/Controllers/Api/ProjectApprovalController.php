<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesApproval;
use App\Models\ApprovalRecord;
use App\Models\User;
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

        $userId = $request->user()?->id;
        $record = ApprovalRecord::create([
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

        $msg = $result['status'] === ApprovalRecord::STATUS_APPROVED ? '已通过（全部节点已完成）' : '已通过，已转交下一节点';
        return response()->json(['code' => 0, 'message' => $msg, 'data' => ['status' => $approval->status]]);
    }

    public function reject(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'project', 404, '资源不存在或参数错误');
        if ($approval->status !== ApprovalRecord::STATUS_PENDING) {
            return response()->json(['code' => 1, 'message' => '该审批已结束，无法操作'], 422);
        }
        if (!$this->canCurrentUserApprove($approval)) {
            return response()->json(['code' => 1, 'message' => '当前用户无权审批该单 (申请人不能审批自己的单)'], 403);
        }

        $request->validate(['comment' => 'required|string|max:500']);
        $user = $request->user();
        $comment = $request->input('comment');

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
        abort_unless($approval->type === 'project', 404, '资源不存在或参数错误');
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
