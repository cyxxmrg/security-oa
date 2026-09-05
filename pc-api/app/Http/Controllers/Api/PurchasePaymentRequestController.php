<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchasePaymentRequest;
use App\Services\PurchaseFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'amount'        => 'required|numeric|min:0.01',
            'payment_type'  => 'nullable|string|in:full,advance,progress,retention',
            'request_date'  => 'nullable|date',
            'reason'        => 'nullable|string',
        ]);

        try {
            $pr = app(PurchaseFlowService::class)->createPaymentRequest(
                $data['contract_id'],
                $data,
                $request->user()
            );
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['code' => 0, 'data' => $pr]);
    }

    public function approve(Request $request, int $req): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|string|in:approve,reject',
            'remark'   => 'nullable|string|max:500',
        ]);

        try {
            if ($data['decision'] === 'approve') {
                $approved = app(PurchaseFlowService::class)->approvePaymentRequest(
                    $req,
                    $request->user(),
                    $data['remark'] ?? ''
                );
            } else {
                $approved = DB::transaction(function () use ($req, $data, $request) {
                    $locked = PurchasePaymentRequest::allData()->lockForUpdate()->findOrFail($req);
                    if ($locked->status !== 'pending') {
                        throw new \RuntimeException('只有待审批状态可审批');
                    }
                    $approval = \App\Models\ApprovalRecord::where('type', 'finance')
                        ->where('sub_type', 'purchase_payment')
                        ->where('payload->payment_request_id', $locked->id)
                        ->where('status', \App\Models\ApprovalRecord::STATUS_PENDING)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $flowService = app(\App\Services\ApprovalFlowService::class);
                    $flowResult = $flowService->rejectFlow($approval, $request->user(), $data['remark'] ?? '驳回');
                    $approval->forceFill([
                        'flow' => $flowResult['flow'],
                        'status' => $flowResult['status'],
                        'current_approver_id' => null,
                        'comment' => $data['remark'] ?? '驳回',
                    ])->save();
                    app(PurchaseFlowService::class)->syncApprovalBusinessState(
                        $approval,
                        $request->user(),
                        'rejected',
                        $data['remark'] ?? '驳回'
                    );
                    return PurchasePaymentRequest::allData()->findOrFail($req);
                });
            }
        } catch (\DomainException|\RuntimeException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['code' => 0, 'data' => $approved]);
    }

    public function destroy(Request $request, int $req): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $req) {
            $locked = PurchasePaymentRequest::allData()->lockForUpdate()->findOrFail($req);
            $user = $request->user();
            if (!$user || (int) $locked->applicant_id !== (int) $user->id) {
                return '只有申请人可以删除付款申请';
            }
            if (!in_array($locked->status, ['pending', 'rejected'], true)) {
                return '只有待审批或已驳回的付款申请可以删除';
            }
            if ($locked->payments()->exists()) {
                return '已产生付款记录的申请不可删除';
            }
            if ($locked->vouchers()->exists()) {
                return '存在付款凭证的申请不可删除';
            }
            \App\Models\ApprovalRecord::where('type', 'finance')
                ->where('sub_type', 'purchase_payment')
                ->where('payload->payment_request_id', $locked->id)
                ->where('status', \App\Models\ApprovalRecord::STATUS_PENDING)
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
