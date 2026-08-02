<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRecord;
use App\Models\PurchaseContract;
use App\Models\PurchasePaymentRequest;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 采购付款申请 (Payment Request) — 5 端点
 *
 *  GET    /api/purchase/payment-requests             列表
 *  POST   /api/purchase/payment-requests             新建
 *  GET    /api/purchase/payment-requests/stats       统计
 *  POST   /api/purchase/payment-requests/{req}/approve  审批
 *  DELETE /api/purchase/payment-requests/{req}       撤回/删除
 */
class PurchasePaymentRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchasePaymentRequest::query();
        if ($request->filled('contract_id'))   $query->where('contract_id', $request->contract_id);
        if ($request->filled('supplier_id'))   $query->where('supplier_id', $request->supplier_id);
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('payment_type'))  $query->where('payment_type', $request->payment_type);

        $perPage = (int) ($request->per_page ?? 15);
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate(max(1, min($perPage, 200)))]);
    }

    public function stats(): JsonResponse
    {
        $rows = PurchasePaymentRequest::query()
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount),0) as amount')
            ->groupBy('status')
            ->get();
        $by = $rows->pluck('count', 'status')->toArray();
        $amountBy = $rows->pluck('amount', 'status')->toArray();

        return response()->json([
            'code' => 0,
            'data' => [
                'pending'  => $by['pending']  ?? 0,
                'approved' => $by['approved'] ?? 0,
                'rejected' => $by['rejected'] ?? 0,
                'paid'     => $by['paid']     ?? 0,
                'total'    => array_sum($by),
                'total_amount' => array_sum($amountBy),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id'   => 'required|integer|exists:purchase_contracts,id',
            'supplier_id'   => 'nullable|integer|exists:suppliers,id',
            'amount'        => 'required|numeric|min:0',
            'payment_type'  => 'nullable|string|in:full,advance,progress,retention',
            'request_date'  => 'nullable|date',
            'applicant'     => 'nullable|string|max:50',
            'reason'        => 'nullable|string',
        ]);

        $data['payment_type'] = $data['payment_type'] ?? 'full';
        $data['status']       = 'pending';
        $data['applicant_id'] = $request->user()->id;

        // 若没传 supplier_id，从合同里取
        if (empty($data['supplier_id']) && $contract = PurchaseContract::find($data['contract_id'])) {
            $data['supplier_id'] = $contract->supplier_id;
        }

        $contract = $contract ?? PurchaseContract::findOrFail($data['contract_id']);
        $existingAmount = (float) PurchasePaymentRequest::where('contract_id', $contract->id)
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->sum('amount');
        $amount = (float) $data['amount'];
        if ((float) $contract->total_amount > 0 && $existingAmount + $amount - (float) $contract->total_amount > 0.0001) {
            return response()->json(['code' => 1, 'message' => '付款申请金额超过合同未申请金额'], 422);
        }

        $pr = PurchasePaymentRequest::create($data);
        return response()->json(['code' => 0, 'data' => $pr]);
    }

    public function approve(Request $request, PurchasePaymentRequest $pr): JsonResponse
    {
        if ($pr->status !== 'pending') {
            return response()->json(['code' => 1, 'message' => '只有待审批状态可审批'], 409);
        }

        $data = $request->validate([
            'decision' => 'required|string|in:approve,reject',
            'remark'   => 'nullable|string|max:500',
        ]);

        $pr->update([
            'status'         => $data['decision'] === 'approve' ? 'approved' : 'rejected',
            'approver_id'    => $request->user()->id,
            'approved_at'    => now(),
            'approve_remark' => $data['remark'] ?? null,
        ]);

        try {
            $approval = ApprovalRecord::where('type', 'finance')
                ->where('sub_type', 'purchase_payment')
                ->where('payload->payment_request_id', $pr->id)
                ->first();
            if ($approval) {
                $user = User::find($request->user()->id);
                $comment = $data['remark'] ?? ($data['decision'] === 'approve' ? '同意' : '驳回');
                $flowService = app(ApprovalFlowService::class);

                if ($data['decision'] === 'approve') {
                    $result = $flowService->advanceFlow($approval, $user, $comment);
                } else {
                    $result = $flowService->rejectFlow($approval, $user, $comment);
                }

                $approval->flow = $result['flow'];
                $approval->status = $result['status'];
                $approval->current_approver_id = $result['current_approver_id'];
                $approval->comment = $comment;
                $approval->save();
            }
        } catch (\Throwable $e) {
            \Log::error('PurchasePaymentRequest::approve sync failed', ['msg' => $e->getMessage()]);
        }

        return response()->json(['code' => 0, 'data' => $pr->fresh()]);
    }

    public function destroy(PurchasePaymentRequest $pr): JsonResponse
    {
        if ($pr->status === 'paid') {
            return response()->json(['code' => 1, 'message' => '已付款的申请不可删除'], 409);
        }
        $pr->delete();
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }
}
