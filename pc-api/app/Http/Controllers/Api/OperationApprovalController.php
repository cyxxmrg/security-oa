<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\HandlesApproval;
use App\Models\ApprovalRecord;
use App\Models\EmployeeResignation;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
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
            'payload.project_id' => 'nullable|integer|exists:projects,id',
            'payload.requirement_id' => 'required_if:sub_type,purchase_requirement|integer|exists:purchase_requirements,id',
            'payload.plan_id' => 'required_if:sub_type,purchase_plan|integer|exists:purchase_plans,id',
            'payload.purchase_order_id' => 'required_if:sub_type,purchase_order|integer|exists:purchase_orders,id',
            'payload.leave_id' => 'required_if:sub_type,leave|integer|exists:leave_requests,id',
            'payload.overtime_id' => 'required_if:sub_type,overtime|integer|exists:overtime_requests,id',
            'payload.resignation_id' => 'required_if:sub_type,resignation|integer|exists:employee_resignations,id',
            'payload.items' => 'required_if:sub_type,material-request|array|min:1',
            'payload.items.*.inventory_item_id' => 'required_if:sub_type,material-request|integer|exists:inventory_items,id',
            'payload.items.*.quantity' => 'required_if:sub_type,material-request|integer|min:1',
            'payload.items.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'cc'         => 'nullable|array',
        ]);

            $applicant = $request->user();
            abort_unless($applicant, 401, '登录状态已失效');

        try {
            $record = \DB::transaction(function () use ($data, $applicant) {
            $this->assertBusinessObjectBelongsToApplicant($data['sub_type'], $data['payload'] ?? [], $applicant->id);
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate($data['sub_type'], 'operation');
            if (!$template) {
                throw new \DomainException('未找到该审批类型的启用流程模板，请先在审批流程引擎中配置');
            }
            $flowData = $flowService->initFlow($template, $applicant, '提交运营审批');

            return ApprovalRecord::create([
            'code'         => $this->nextCode('OPS'),
            'type'         => 'operation',
            'sub_type'     => $data['sub_type'],
            'title'        => $data['title'],
            'priority'     => $data['priority'] ?? 'normal',
            'status'       => ApprovalRecord::STATUS_PENDING,
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

            if ($result['status'] === ApprovalRecord::STATUS_APPROVED && $approval->sub_type === 'material-request') {
                $payload = $approval->payload;
                $items = $payload['items'] ?? [];
                $projectId = $payload['project_id'] ?? null;
                foreach ($items as $item) {
                    $invItem = \App\Models\InventoryItem::lockForUpdate()->findOrFail($item['inventory_item_id']);
                    $qty = (int) ($item['quantity'] ?? 1);
                    if ($qty < 1) {
                        throw new \RuntimeException('物料申领数量必须大于 0');
                    }
                    $warehouseId = $item['warehouse_id'] ?? $invItem->warehouse_id;
                    if ($warehouseId === null) {
                        throw new \RuntimeException("物料「{$invItem->name}」未配置仓库");
                    }
                    if ($invItem->warehouse_id !== null && (int) $invItem->warehouse_id !== (int) $warehouseId) {
                        throw new \RuntimeException("物料「{$invItem->name}」不属于申领仓库");
                    }
                    if ($invItem->current_stock < $qty) {
                        throw new \RuntimeException("物料 {$invItem->name} 库存不足（当前 {$invItem->current_stock}，需要 {$qty}）");
                    }
                    $newStock = $invItem->current_stock - $qty;
                    $invItem->current_stock = $newStock;
                    if ($invItem->warehouse_id === null) {
                        $invItem->warehouse_id = (int) $warehouseId;
                    }
                    $invItem->save();

                    $today = date('Ymd');
                    $sequence = \App\Services\NumberSequenceService::next("material-request-stock:{$today}", static function () use ($today): int {
                        return \App\Models\StockRecord::where('record_no', 'like', "MR-{$today}-%")
                            ->pluck('record_no')
                            ->map(static function (string $recordNo): int {
                                $parts = explode('-', $recordNo);
                                return (int) end($parts);
                            })
                            ->max() ?? 0;
                    });
                    \App\Models\StockRecord::create([
                        'record_no'         => sprintf('MR-%s-%04d', $today, $sequence),
                        'inventory_item_id' => $invItem->id,
                        'warehouse_id'      => $warehouseId,
                        'type'              => 'out',
                        'quantity'          => $qty,
                        'remaining_stock'   => $newStock,
                        'out_method'        => 'pickup',
                        'project_id'        => $projectId,
                        'operator_id'       => $approval->applicant_id,
                        'remark'            => '物料申领 #' . $approval->code,
                    ]);
                }
            }

            $approval->flow = $result['flow'];
            $approval->status = $result['status'];
            $approval->current_approver_id = $result['current_approver_id'];
            $approval->comment = $comment;
            $approval->save();

            if ($result['status'] === ApprovalRecord::STATUS_APPROVED) {
                if (in_array($approval->sub_type, ['purchase_requirement', 'purchase_plan', 'purchase_order'], true)) {
                    app(\App\Services\PurchaseFlowService::class)
                        ->syncApprovalBusinessState($approval, $user, 'approved', $comment);
                }
                $this->syncResignationStatus($approval, 'approved');
                $this->syncLeaveStatus($approval, 'approved');
                $this->syncOvertimeStatus($approval, 'approved');
            }

            $msg = $result['status'] === ApprovalRecord::STATUS_APPROVED ? '已通过（全部审批节点已完成）' : '已通过，已转交下一节点';
            return response()->json(['code' => 0, 'message' => $msg, 'data' => ['status' => $approval->status, 'remark' => $result['status'] === ApprovalRecord::STATUS_APPROVED && $approval->sub_type === 'material-request' ? '物料已出库' : null]]);
            });
        } catch (\Throwable $e) {
            \Log::error(__METHOD__ . ': catch', ['msg' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            $message = $approval->sub_type === 'material-request'
                ? '物料出库失败：' . $e->getMessage()
                : '审批处理失败：' . $e->getMessage();
            return response()->json(['code' => 1002, 'message' => $message], 422);
        }
    }

    public function reject(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'operation', 404, '资源不存在或参数错误');
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
            if (in_array($approval->sub_type, ['purchase_requirement', 'purchase_plan', 'purchase_order'], true)) {
                app(\App\Services\PurchaseFlowService::class)
                    ->syncApprovalBusinessState($approval, $request->user(), 'rejected', $comment);
            }
            $this->syncResignationStatus($approval, 'rejected');
            $this->syncLeaveStatus($approval, 'rejected');
            $this->syncOvertimeStatus($approval, 'rejected');

            return response()->json(['code' => 0, 'message' => '已驳回', 'data' => ['status' => $approval->status]]);
        });
    }

    public function forward(Request $request, ApprovalRecord $approval): JsonResponse
    {
        abort_unless($approval->type === 'operation', 404, '资源不存在或参数错误');
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

    private function syncOvertimeStatus(ApprovalRecord $approval, string $status): void
    {
        if ($approval->sub_type !== 'overtime') {
            return;
        }

        $overtimeId = $approval->payload['overtime_id'] ?? null;
        if (!$overtimeId) {
            return;
        }

        OvertimeRequest::whereKey($overtimeId)->update([
            'status' => $status,
            'approver_id' => request()->user()?->id,
            'approved_at' => now(),
        ]);
    }

    private function assertBusinessObjectBelongsToApplicant(string $subType, array $payload, int $applicantId): void
    {
        $checks = [
            'leave' => [
                'key' => 'leave_id',
                'model' => LeaveRequest::class,
                'owner' => 'user_id',
                'status' => ['pending'],
                'label' => '请假申请',
            ],
            'overtime' => [
                'key' => 'overtime_id',
                'model' => OvertimeRequest::class,
                'owner' => 'user_id',
                'status' => ['pending'],
                'label' => '加班申请',
            ],
            'resignation' => [
                'key' => 'resignation_id',
                'model' => EmployeeResignation::class,
                'owner' => 'created_by',
                'status' => ['pending'],
                'label' => '离职申请',
            ],
            'purchase_requirement' => [
                'key' => 'requirement_id',
                'model' => \App\Models\PurchaseRequirement::class,
                'owner' => 'created_by',
                'status' => ['pending'],
                'label' => '采购需求',
            ],
            'purchase_plan' => [
                'key' => 'plan_id',
                'model' => \App\Models\PurchasePlan::class,
                'owner' => null,
                'status' => ['submitted'],
                'label' => '采购计划',
            ],
            'purchase_order' => [
                'key' => 'purchase_order_id',
                'model' => \App\Models\PurchaseOrder::class,
                'owner' => 'created_by',
                'status' => ['pending'],
                'label' => '采购订单',
            ],
        ];

        if (!isset($checks[$subType])) {
            return;
        }

        $check = $checks[$subType];
        $id = (int) ($payload[$check['key']] ?? 0);
        if ($id < 1) {
            throw new \DomainException("{$check['label']}标识不能为空");
        }

        $query = $check['model']::query()->whereKey($id);
        if ($check['owner'] !== null) {
            $query->where($check['owner'], $applicantId);
        } else {
            $query->where(function ($ownerQuery) use ($applicantId) {
                $ownerQuery->where('created_by', $applicantId)
                    ->orWhere('submitter_id', $applicantId);
            });
        }
        $query->whereIn('status', $check['status']);

        if (!$query->exists()) {
            throw new \DomainException("无权为他人的{$check['label']}创建审批，或该单据当前状态不可提交审批");
        }

        if (ApprovalRecord::query()
            ->where('type', 'operation')
            ->where('sub_type', $subType)
            ->whereIn('status', [ApprovalRecord::STATUS_PENDING, ApprovalRecord::STATUS_APPROVED])
            ->where("payload->{$check['key']}", $id)
            ->exists()) {
            throw new \DomainException("该{$check['label']}已有进行中或已完成的审批记录");
        }
    }

    private function syncLeaveStatus(ApprovalRecord $approval, string $status): void
    {
        if ($approval->sub_type !== 'leave') {
            return;
        }

        $leaveId = $approval->payload['leave_id'] ?? null;
        if (!$leaveId) {
            return;
        }

        LeaveRequest::whereKey($leaveId)->update([
            'status' => $status,
            'approver_id' => request()->user()?->id,
            'approved_at' => now(),
            'reject_reason' => $status === 'rejected' ? $approval->comment : null,
        ]);
    }

    private function syncResignationStatus(ApprovalRecord $approval, string $status): void
    {
        if ($approval->sub_type !== 'resignation') {
            return;
        }

        $resignationId = $approval->payload['resignation_id'] ?? null;
        if (!$resignationId) {
            return;
        }

        EmployeeResignation::whereKey($resignationId)->update([
            'status' => $status === 'approved' ? 'approved' : 'cancelled',
            'approved_by' => $status === 'approved' ? request()->user()?->id : null,
            'approved_at' => now(),
            'remark' => $status === 'rejected' ? $approval->comment : null,
        ]);
    }
}
