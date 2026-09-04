<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\PurchaseRequirement;
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
            'quantity'   => 'required|numeric|min:0.01',
            'unit'       => 'nullable|string|max:20',
            'need_date'  => 'nullable|date',
            'priority'   => 'nullable|string|in:low,medium,high,urgent',
            'remark'     => 'nullable|string',
        ]);

        try {
            $req = app(\App\Services\PurchaseFlowService::class)->createRequirement($data, $request->user());
            return response()->json(['code' => 0, 'data' => $req]);
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, PurchaseRequirement $requirement): JsonResponse
    {
        if (!in_array($requirement->status, ['pending', 'rejected'], true)) {
            return response()->json(['code' => 1, 'message' => '当前状态不可编辑'], 409);
        }

        $data = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'material'   => 'sometimes|string|max:200',
            'spec'       => 'nullable|string|max:200',
            'quantity'   => 'sometimes|numeric|min:0.01',
            'unit'       => 'nullable|string|max:20',
            'need_date'  => 'nullable|date',
            'priority'   => 'sometimes|string|in:low,medium,high,urgent',
            'remark'     => 'nullable|string',
        ]);

        $updated = DB::transaction(function () use ($requirement, $data, $request) {
            $locked = PurchaseRequirement::lockForUpdate()->findOrFail($requirement->id);
            $wasRejected = $locked->status === 'rejected';
            if ($wasRejected) {
                ApprovalRecord::where('type', 'operation')
                    ->where('sub_type', 'purchase_requirement')
                    ->where('payload->requirement_id', $locked->id)
                    ->delete();
                $locked->forceFill([
                    'status' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_remark' => null,
                ]);
            }
            $locked->fill($data)->save();
            app(\App\Services\PurchaseFlowService::class)
                ->syncRequirementApprovalRecord($locked->fresh(), $request->user());
            return $locked->fresh();
        });
        return response()->json(['code' => 0, 'data' => $updated]);
    }

    public function destroy(PurchaseRequirement $requirement): JsonResponse
    {
        $result = DB::transaction(function () use ($requirement) {
            $locked = PurchaseRequirement::lockForUpdate()->findOrFail($requirement->id);
            if (!in_array($locked->status, ['pending', 'rejected', 'cancelled'], true)) {
                return '当前状态不可删除';
            }
            ApprovalRecord::where('type', 'operation')
                ->where('sub_type', 'purchase_requirement')
                ->where('payload->requirement_id', $locked->id)
                ->delete();
            $locked->delete();
            return null;
        });
        if ($result) {
            return response()->json(['code' => 1, 'message' => $result], 409);
        }
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

}
