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
            'applicant'     => 'nullable|string|max:50',
            'reason'        => 'nullable|string',
        ]);

        $data['payment_type'] = $data['payment_type'] ?? 'full';
        $data['status']       = 'pending';
        $data['applicant_id'] = $request->user()->id;

        $result = DB::transaction(function () use ($data) {
            $contract = PurchaseContract::lockForUpdate()->findOrFail($data['contract_id']);
            if (in_array($contract->status, ['draft', 'cancelled'], true)) {
                return ['error' => '合同当前状态不可申请付款'];
            }
            if (!empty($data['supplier_id']) && (int) $data['supplier_id'] !== (int) $contract->supplier_id) {
                return ['error' => '付款申请供应商与合同不匹配'];
            }
            $data['supplier_id'] = $contract->supplier_id;
            $existingAmount = (float) PurchasePaymentRequest::where('contract_id', $contract->id)
                ->whereIn('status', ['pending', 'approved', 'paid'])
                ->sum('amount');
            $amount = (float) $data['amount'];
            if ((float) $contract->total_amount > 0 && $existingAmount + $amount - (float) $contract->total_amount > 0.0001) {
                return ['error' => '付款申请金额超过合同未申请金额'];
            }
            return ['request' => PurchasePaymentRequest::create($data)];
        });
        if (isset($result['error'])) {
            return response()->json(['code' => 1, 'message' => $result['error']], 422);
        }
        $pr = $result['request'];
        return response()->json(['code' => 0, 'data' => $pr]);
    }

    public function approve(Request $request, PurchasePaymentRequest $pr): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|string|in:approve,reject',
            'remark'   => 'nullable|string|max:500',
        ]);

        $approved = DB::transaction(function () use ($pr, $data, $request) {
            $locked = PurchasePaymentRequest::lockForUpdate()->findOrFail($pr->id);
            if ($locked->status !== 'pending') {
                return null;
            }
            $locked->update([
                'status'         => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'approver_id'    => $request->user()->id,
                'approved_at'    => now(),
                'approve_remark' => $data['remark'] ?? null,
            ]);

            try {
                $approval = ApprovalRecord::where('type', 'finance')
                    ->where('sub_type', 'purchase_payment')
                    ->where('payload->payment_request_id', $locked->id)
                    ->lockForUpdate()
                    ->first();
                if ($approval) {
                    $user = $request->user();
                    $comment = $data['remark'] ?? ($data['decision'] === 'approve' ? '同意' : '驳回');
                    $flowService = app(ApprovalFlowService::class);
                    $flowResult = $data['decision'] === 'approve'
                        ? $flowService->advanceFlow($approval, $user, $comment)
                        : $flowService->rejectFlow($approval, $user, $comment);
                    $approval->flow = $flowResult['flow'];
                    $approval->status = $flowResult['status'];
                    $approval->current_approver_id = $flowResult['current_approver_id'];
                    $approval->comment = $comment;
                    $approval->save();
                }
            } catch (\Throwable $e) {
                \Log::error('PurchasePaymentRequest::approve sync failed', ['msg' => $e->getMessage()]);
            }
            return $locked->fresh();
        });
        if (!$approved) {
            return response()->json(['code' => 1, 'message' => '只有待审批状态可审批'], 409);
        }
        return response()->json(['code' => 0, 'data' => $approved]);
    }

    public function destroy(PurchasePaymentRequest $pr): JsonResponse
    {
        $result = DB::transaction(function () use ($pr) {
            $locked = PurchasePaymentRequest::lockForUpdate()->findOrFail($pr->id);
            if ($locked->status === 'paid' || $locked->payments()->exists()) {
                return '已付款的申请不可删除';
            }
            if ($locked->vouchers()->exists()) {
                return '存在付款凭证的申请不可删除';
            }
            $locked->delete();
            return null;
        });
        if ($result) {
            return response()->json(['code' => 1, 'message' => $result], 409);
        }
        return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
    }
}
