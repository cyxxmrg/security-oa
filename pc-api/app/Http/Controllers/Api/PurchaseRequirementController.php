<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\PurchaseRequirement;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 采购需求 (Requirement) — 5 端点
 *
 *  GET    /api/purchase/requirements         列表 + 筛选
 *  POST   /api/purchase/requirements         新建
 *  GET    /api/purchase/requirements/stats   统计
 *  PUT    /api/purchase/requirements/{req}   更新
 *  DELETE /api/purchase/requirements/{req}   删除
 */
class PurchaseRequirementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseRequirement::query()->with(['inventoryItem:id,code,name,specification,unit']);
        if ($request->filled('project_id')) $query->where('project_id', $request->project_id);
        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('priority'))   $query->where('priority', $request->priority);
        if ($request->filled('keyword'))    $query->where(function ($q) use ($request) {
            $kw = '%' . $request->keyword . '%';
            $q->where('code', 'like', $kw)->orWhere('material', 'like', $kw);
        });

        $perPage = (int) ($request->per_page ?? 15);
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate(max(1, min($perPage, 200)))]);
    }

    public function stats(): JsonResponse
    {
        $rows = PurchaseRequirement::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'code' => 0,
            'data' => [
                'pending'   => $rows['pending']   ?? 0,
                'approved'  => $rows['approved']  ?? 0,
                'rejected'  => $rows['rejected']  ?? 0,
                'cancelled' => $rows['cancelled'] ?? 0,
                'total'     => array_sum($rows),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'material'   => 'required|string|max:200',
            'spec'       => 'nullable|string|max:200',
            'quantity'   => 'required|numeric|min:0',
            'unit'       => 'nullable|string|max:20',
            'need_date'  => 'nullable|date',
            'priority'   => 'nullable|string|in:low,medium,high,urgent',
            'creator'    => 'nullable|string|max:50',
            'remark'     => 'nullable|string',
        ]);

        $data['priority'] = $data['priority'] ?? 'medium';
        $data['unit']     = $data['unit'] ?? '件';
        $data['status']   = 'pending';

        $req = PurchaseRequirement::create($data);
        $this->syncApprovalRecord($req, $request);
        return response()->json(['code' => 0, 'data' => $req]);
    }

    public function update(Request $request, PurchaseRequirement $requirement): JsonResponse
    {
        if ($requirement->status === 'approved') {
            return response()->json(['code' => 1, 'message' => '已通过的需求不可编辑'], 409);
        }

        $data = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'material'   => 'sometimes|string|max:200',
            'spec'       => 'nullable|string|max:200',
            'quantity'   => 'sometimes|numeric|min:0',
            'unit'       => 'nullable|string|max:20',
            'need_date'  => 'nullable|date',
            'priority'   => 'sometimes|string|in:low,medium,high,urgent',
            'creator'    => 'nullable|string|max:50',
            'remark'     => 'nullable|string',
            'status'     => 'sometimes|string|in:pending,approved,rejected,cancelled',
        ]);

        // 审核动作
        if (isset($data['status']) && in_array($data['status'], ['approved', 'rejected'])) {
            $data['reviewed_by'] = $request->user()->id;
            $data['reviewed_at'] = now();
        }

        $requirement->update($data);
        if (isset($data['status']) && in_array($data['status'], ['approved', 'rejected', 'cancelled'], true)) {
            $this->syncApprovalStatus($requirement->fresh(), $request, $data['status']);
        }
        return response()->json(['code' => 0, 'data' => $requirement->fresh()]);
    }

    public function destroy(PurchaseRequirement $requirement): JsonResponse
    {
        if ($requirement->status === 'approved') {
            return response()->json(['code' => 1, 'message' => '已通过的需求不可删除'], 409);
        }
        $requirement->delete();
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    private function syncApprovalRecord(PurchaseRequirement $requirement, Request $request): void
    {
        try {
            $exists = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_requirement')
                ->where('payload->requirement_id', $requirement->id)
                ->exists();

            if ($exists) {
                return;
            }

            $applicant = $request->user();

            // 按模板初始化审批流程
            $flowService = app(ApprovalFlowService::class);
            $template = $flowService->resolveTemplate('purchase_requirement');
            $flowData = $template
                ? $flowService->initFlow($template, $applicant, '提交采购需求审批')
                : ['current_approver_id' => $this->defaultApproverId($applicant?->id), 'flow' => [[
                    'operator' => $applicant?->name ?? '—',
                    'action'   => 'submit',
                    'time'     => now()->toDateTimeString(),
                    'comment'  => '提交采购需求审批',
                ]]];

            ApprovalRecord::create([
                'code'                => $this->nextApprovalCode(),
                'type'                => 'operation',
                'sub_type'            => 'purchase_requirement',
                'title'               => sprintf(
                    '[采购需求] %s %s x %s%s',
                    $requirement->code,
                    $requirement->material,
                    $requirement->quantity,
                    $requirement->unit
                ),
                'priority'            => $this->approvalPriority($requirement->priority),
                'status'              => ApprovalRecord::STATUS_PENDING,
                'applicant_id'        => $applicant?->id,
                'current_approver_id' => $flowData['current_approver_id'],
                'payload'             => [
                    'requirement_id' => $requirement->id,
                    'requirement_code' => $requirement->code,
                    'project_id' => $requirement->project_id,
                    'inventory_item_id' => $requirement->inventory_item_id,
                    'material' => $requirement->material,
                    'spec' => $requirement->spec,
                    'quantity' => (float) $requirement->quantity,
                    'unit' => $requirement->unit,
                    'need_date' => $requirement->need_date?->format('Y-m-d'),
                    'remark' => $requirement->remark,
                ],
                'flow'                => $flowData['flow'],
            ]);
        } catch (\Throwable $e) {
            Log::error('PurchaseRequirement::syncApprovalRecord failed', [
                'requirement_id' => $requirement->id,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    private function syncApprovalStatus(PurchaseRequirement $requirement, Request $request, string $status): void
    {
        try {
            $approval = ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_requirement')
                ->where('payload->requirement_id', $requirement->id)
                ->first();
            if (!$approval) {
                return;
            }

            $flow = is_array($approval->flow) ? $approval->flow : [];
            $flow[] = [
                'operator' => $request->user()?->name ?? '—',
                'action'   => $status === 'approved' ? 'approve' : ($status === 'rejected' ? 'reject' : 'cancel'),
                'time'     => now()->toDateTimeString(),
                'comment'  => $requirement->review_remark ?? $request->input('review_remark') ?? '',
            ];

            $approval->flow = $flow;
            $approval->status = match ($status) {
                'approved' => ApprovalRecord::STATUS_APPROVED,
                'rejected' => ApprovalRecord::STATUS_REJECTED,
                default => ApprovalRecord::STATUS_CANCELLED,
            };
            $approval->comment = $requirement->review_remark ?? $request->input('review_remark');
            $approval->save();
        } catch (\Throwable $e) {
            Log::error('PurchaseRequirement::syncApprovalStatus failed', [
                'requirement_id' => $requirement->id,
                'status' => $status,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    private function nextApprovalCode(): string
    {
        $year = now()->format('Y');
        $count = ApprovalRecord::where('code', 'like', "OPS-{$year}-%")->count() + 1;
        return sprintf('OPS-%s-%04d', $year, $count);
    }

    private function approvalPriority(?string $priority): string
    {
        return match ($priority) {
            'urgent' => 'urgent',
            'high' => 'high',
            'low' => 'low',
            default => 'normal',
        };
    }

    private function defaultApproverId(?int $applicantId): ?int
    {
        $query = User::query()->orderBy('id');
        if ($applicantId) {
            $query->where('id', '<>', $applicantId);
        }

        return $query->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->value('id')
            ?? User::where('user_type', 'system')->value('id')
            ?? User::query()->when($applicantId, fn ($q) => $q->where('id', '<>', $applicantId))->orderBy('id')->value('id')
            ?? $applicantId;
    }
}
