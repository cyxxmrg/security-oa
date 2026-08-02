<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseContract;
use App\Models\PurchaseShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 采购合同 (Contract) — 7 端点
 *
 *  GET    /api/purchase/contracts             列表
 *  POST   /api/purchase/contracts             新建
 *  GET    /api/purchase/contracts/stats       统计
 *  POST   /api/purchase/contracts/{c}/ship    触发生成发货单
 *  GET    /api/purchase/contracts/{c}         详情
 *  PUT    /api/purchase/contracts/{c}         更新
 *  DELETE /api/purchase/contracts/{c}         删除
 */
class PurchaseContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseContract::query();
        if ($request->filled('plan_id'))      $query->where('plan_id', $request->plan_id);
        if ($request->filled('supplier_id'))  $query->where('supplier_id', $request->supplier_id);
        if ($request->filled('project_id'))   $query->where('project_id', $request->project_id);
        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('keyword'))      $query->where(function ($q) use ($request) {
            $kw = '%' . $request->keyword . '%';
            $q->where('code', 'like', $kw)->orWhere('title', 'like', $kw);
        });

        $perPage = (int) ($request->per_page ?? 15);
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate(max(1, min($perPage, 200)))]);
    }

    public function show(PurchaseContract $contract): JsonResponse
    {
        $contract->load(['plan', 'project', 'supplier', 'shipments', 'paymentRequests']);
        return response()->json(['code' => 0, 'data' => $contract]);
    }

    public function stats(): JsonResponse
    {
        $rows = PurchaseContract::query()
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total_amount),0) as amount')
            ->groupBy('status')
            ->get();
        $by = $rows->pluck('count', 'status')->toArray();
        $amountBy = $rows->pluck('amount', 'status')->toArray();

        return response()->json([
            'code' => 0,
            'data' => [
                'draft'     => $by['draft']     ?? 0,
                'signed'    => $by['signed']    ?? 0,
                'shipping'  => $by['shipping']  ?? 0,
                'completed' => $by['completed'] ?? 0,
                'cancelled' => $by['cancelled'] ?? 0,
                'total'     => array_sum($by),
                'total_amount' => array_sum($amountBy),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id'           => 'nullable|integer|exists:purchase_plans,id',
            'project_id'        => 'nullable|integer|exists:projects,id',
            'supplier_id'       => 'required|integer|exists:suppliers,id',
            'title'             => 'required|string|max:200',
            'total_amount'      => 'required|numeric|min:0',
            'signed_at'         => 'nullable|date',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'payment_terms'     => 'nullable|string|max:200',
            'delivery_address'  => 'nullable|string|max:200',
            'signer'            => 'nullable|string|max:50',
            'remark'            => 'nullable|string',
        ]);

        $data['status']    = 'draft';
        $data['signer_id'] = $request->user()->id;
        $data['signed_at'] = $data['signed_at'] ?? now()->toDateString();

        $contract = PurchaseContract::create($data);
        return response()->json(['code' => 0, 'data' => $contract]);
    }

    public function update(Request $request, PurchaseContract $contract): JsonResponse
    {
        if (in_array($contract->status, ['shipping', 'completed'])) {
            return response()->json(['code' => 1, 'message' => '已开始发货/已完成的合同不可编辑'], 409);
        }

        $data = $request->validate([
            'plan_id'           => 'nullable|integer|exists:purchase_plans,id',
            'project_id'        => 'nullable|integer|exists:projects,id',
            'supplier_id'       => 'sometimes|integer|exists:suppliers,id',
            'title'             => 'sometimes|string|max:200',
            'total_amount'      => 'sometimes|numeric|min:0',
            'signed_at'         => 'nullable|date',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'payment_terms'     => 'nullable|string|max:200',
            'delivery_address'  => 'nullable|string|max:200',
            'status'            => 'sometimes|string|in:draft,signed,shipping,completed,cancelled',
            'signer'            => 'nullable|string|max:50',
            'remark'            => 'nullable|string',
        ]);

        $contract->update($data);
        return response()->json(['code' => 0, 'data' => $contract->fresh()]);
    }

    public function destroy(PurchaseContract $contract): JsonResponse
    {
        if ($contract->status === 'completed') {
            return response()->json(['code' => 1, 'message' => '已完成的合同不可删除'], 409);
        }
        if ($contract->shipments()->exists()) {
            return response()->json(['code' => 1, 'message' => '存在关联发货单，请先清理'], 409);
        }
        $contract->delete();
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }

    public function ship(Request $request, PurchaseContract $contract): JsonResponse
    {
        if (!in_array($contract->status, ['signed', 'shipping'])) {
            return response()->json(['code' => 1, 'message' => '只有已签订/运输中的合同可发货'], 409);
        }

        $data = $request->validate([
            'carrier'              => 'required|string|max:100',
            'tracking_no'          => 'nullable|string|max:100',
            'shipped_at'           => 'nullable|date',
            'expected_arrival_at'  => 'nullable|date',
            'consignee'            => 'nullable|string|max:50',
            'remark'               => 'nullable|string',
        ]);

        $result = DB::transaction(function () use ($contract, $data) {
            $shipment = PurchaseShipment::create([
                'contract_id'         => $contract->id,
                'supplier_id'         => $contract->supplier_id,
                'shipped_at'          => $data['shipped_at']          ?? now()->toDateString(),
                'expected_arrival_at' => $data['expected_arrival_at'] ?? null,
                'carrier'             => $data['carrier'],
                'tracking_no'         => $data['tracking_no']         ?? null,
                'consignee'           => $data['consignee']           ?? null,
                'remark'              => $data['remark']              ?? null,
                'status'              => 'shipped',
            ]);

            $contract->update(['status' => 'shipping']);

            return $shipment;
        });

        return response()->json(['code' => 0, 'data' => $result]);
    }
}
