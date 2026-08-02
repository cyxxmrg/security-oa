<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesApproval;
use App\Models\ApprovalRecord;
use App\Models\PurchaseRequirement;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 运营审批（请假 / 加班 / 用车 / 报销以外的运营事项）
 *
 * GET    /api/approvals/operation                       列表
 * POST   /api/approvals/operation                       新建
 * GET    /api/approvals/operation/{approval}            详情
 * POST   /api/approvals/operation/{approval}/approve    通过
 * POST   /api/approvals/operation/{approval}/reject     拒绝
 * POST   /api/approvals/operation/{approval}/forward    转交
 */
class OperationApprovalController extends Controller
{
    use HandlesApproval;

    public function index(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request, 'operation')->paginate($this->perPage($request));
        return response()->json(['code' => 0, 'data' => $this->transformPaginated($rows)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sub_type'   => 'required|string|max:50',
            'title'      => 'required|string|max:255',
            'priority'   => 'nullable|in:urgent,high,normal,low',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'payload'    => 'nullable|array',
            'cc'         => 'nullable|array',
        ]);

        $userId = $request->user()?->id;
        $record = ApprovalRecord::create([
            'code'         => $this->nextCode('OPS'),
            'type'         => 'operation',
            'sub_type'     => $data['sub_type'],
            'title'        => $data['title'],
            'priority'     => $data['priority'] ?? 'normal',
            'status'       => ApprovalRecord::STATUS_PENDING,
            'start_date'   => $data['start_date'] ?? null,
            'end_date'     => $data['end_date'] ?? null,
            'applicant_id' => $userId,
            'current_approver_id' => 1,
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
            'message' => '运营审批已提交',
            'data'    => ['id' => $record->id, 'code' => $record->code],
        ]);
    }

    public function show(ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'operation', 404, '资源不存在或参数错误');
        abort_unless($this->canCurrentUserView($approval), 403, '无权查看该审批单');
        return response()->json(['code' => 0, 'data' => $this->transform($approval)]);
    }

    public function approve(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'operation', 404, '资源不存在或参数错误');
        if ($approval->status !== ApprovalRecord::STATUS_PENDING) {
            return response()->json(['code' => 1, 'message' => '该审批已结束，无法操作'], 422);
        }
        if (!$this->canCurrentUserApprove($approval)) {
            return response()->json(['code' => 1, 'message' => '当前用户无权审批该单 (申请人不能审批自己的单)'], 403);
        }

        // 物料申领审批通过后自动扣减库存
        if ($approval->sub_type === 'material-request') {
            $payload = $approval->payload;
            $items = $payload['items'] ?? [];
            $projectId = $payload['project_id'] ?? null;
            if (!empty($items)) {
                try {
                    \DB::transaction(function () use ($items, $projectId, $approval) {
                        foreach ($items as $item) {
                            $invItem = \App\Models\InventoryItem::lockForUpdate()->findOrFail($item['inventory_item_id']);
                            $qty = (int)($item['quantity'] ?? 1);
                            if ($invItem->current_stock < $qty) {
                                throw new \RuntimeException("物料 {$invItem->name} 库存不足（当前 {$invItem->current_stock}，需要 {$qty}）");
                            }
                            $newStock = $invItem->current_stock - $qty;
                            $invItem->current_stock = $newStock;
                            $invItem->save();
                            $today = date('Ymd');
                            $cnt = \App\Models\StockRecord::where('record_no', 'like', "MR-{$today}-%")->count();
                            $seq = str_pad((string)($cnt + 1), 4, '0', STR_PAD_LEFT);
                            \App\Models\StockRecord::create([
                                'record_no'         => "MR-{$today}-{$seq}",
                                'inventory_item_id' => $item['inventory_item_id'],
                                'warehouse_id'      => $item['warehouse_id'] ?? 1,
                                'type'              => 'out',
                                'quantity'          => $qty,
                                'remaining_stock'   => $newStock,
                                'out_method'        => 'pickup',
                                'project_id'        => $projectId,
                                'operator_id'       => $approval->applicant_id,
                                'remark'            => '物料申领 #' . $approval->code,
                            ]);
                        }
                    });
                } catch (\Throwable $e) {
                    \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
                    return response()->json(['code' => 1002, 'message' => '出库失败：' . $e->getMessage()], 422);
                }
            }
        }

        $comment = $request->input('comment', '同意');
        $user = $request->user();

        // 按模板推进审批流程
        $flowService = app(ApprovalFlowService::class);
        $result = $flowService->advanceFlow($approval, $user, $comment);

        $approval->flow = $result['flow'];
        $approval->status = $result['status'];
        $approval->current_approver_id = $result['current_approver_id'];
        $approval->comment = $comment;
        $approval->save();

        // 只有最终 approved 时才同步采购需求状态
        if ($result['status'] === ApprovalRecord::STATUS_APPROVED) {
            $this->syncPurchaseRequirementStatus($approval, 'approved', $comment);
        }

        $msg = $result['status'] === ApprovalRecord::STATUS_APPROVED ? '已通过（全部审批节点已完成）' : '已通过，已转交下一节点';
        return response()->json(['code' => 0, 'message' => $msg, 'data' => ['status' => $approval->status, 'remark' => $approval->sub_type === 'material-request' ? '物料已出库' : null]]);
    }

    public function reject(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'operation', 404, '资源不存在或参数错误');
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
        $this->syncPurchaseRequirementStatus($approval, 'rejected', $comment);

        return response()->json(['code' => 0, 'message' => '已驳回', 'data' => ['status' => $approval->status]]);
    }

    public function forward(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'operation', 404, '资源不存在或参数错误');
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

    private function syncPurchaseRequirementStatus(ApprovalRecord $approval, string $status, ?string $comment = null): void
    {
        if ($approval->sub_type !== 'purchase_requirement') {
            return;
        }

        $requirementId = $approval->payload['requirement_id'] ?? null;
        if (!$requirementId) {
            return;
        }

        PurchaseRequirement::whereKey($requirementId)->update([
            'status' => $status,
            'review_remark' => $comment,
            'reviewed_by' => request()->user()?->id,
            'reviewed_at' => now(),
        ]);
    }
}
