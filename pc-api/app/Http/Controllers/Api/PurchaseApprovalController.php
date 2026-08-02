<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 采购审批 (Approval) — 3 端点
 *
 *  GET  /api/purchase/approvals             列表 + 筛选
 *  POST /api/purchase/approvals             新建审批单
 *  POST /api/purchase/approvals/{appr}/decide  审批通过/拒绝
 */
class PurchaseApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseApproval::query();
        if ($request->filled('target_type')) $query->where('target_type', $request->target_type);
        if ($request->filled('target_id'))   $query->where('target_id', $request->target_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('applicant_id'))$query->where('applicant_id', $request->applicant_id);

        $perPage = (int) ($request->per_page ?? 15);
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate(max(1, min($perPage, 200)))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => 'required|string|in:plan,contract,payment_request',
            'target_id'   => 'required|integer|min:1',
            'title'       => 'required|string|max:200',
            'reason'      => 'nullable|string',
            'amount'      => 'nullable|numeric|min:0',
            'applicant'   => 'nullable|string|max:50',
        ]);

        $data['applicant_id'] = $request->user()->id;
        $data['applicant']    = $data['applicant'] ?? $request->user()->name ?? null;
        $data['applied_at']   = now();
        $data['status']       = 'pending';

        $appr = PurchaseApproval::create($data);
        return response()->json(['code' => 0, 'data' => $appr]);
    }

    public function decide(Request $request, PurchaseApproval $approval): JsonResponse
    {
        if ($approval->status !== 'pending') {
            return response()->json(['code' => 1, 'message' => '该审批单已处理'], 409);
        }

        $data = $request->validate([
            'decision' => 'required|string|in:approve,reject',
            'remark'   => 'nullable|string|max:500',
        ]);

        $approval->update([
            'status'         => $data['decision'] === 'approve' ? 'approved' : 'rejected',
            'approver_id'    => $request->user()->id,
            'approved_at'    => now(),
            'approve_remark' => $data['remark'] ?? null,
        ]);

        return response()->json(['code' => 0, 'data' => $approval->fresh()]);
    }
}
