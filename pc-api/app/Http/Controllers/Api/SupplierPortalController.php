<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalQuote;
use App\Models\ExternalQuoteRequest;
use App\Models\Supplier;
use App\Services\ExternalQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V0.4.2 供应商门户控制器
 *
 * 路由 /api/supplier-portal（用 middleware supplier.only + supplier.scope 保护）
 *  1. GET  /supplier-portal/profile          当前供应商信息
 *  2. GET  /supplier-portal/quote-requests   可报价的请求列表
 *  3. POST /supplier-portal/quote-requests/{id}/submit  提交报价
 *  4. GET  /supplier-portal/quotes           我方历史报价
 */
class SupplierPortalController extends Controller
{
    public function __construct(private ExternalQuoteService $service) {}

    /** 1. 当前供应商 profile */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $supplier = Supplier::with(['contacts', 'attachments'])
            ->findOrFail($user->supplier_id);
        return response()->json(['code' => 0, 'data' => $supplier]);
    }

    /** 2. 可报价的请求列表（过滤已截止/已取消） */
    public function quoteRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        $list = ExternalQuoteRequest::where('status', ExternalQuoteRequest::STATUS_OPEN)
            ->with(['project:id,name,project_no', 'awardedSupplier:id,name,code'])
            ->orderBy('deadline')
            ->get()
            ->map(function ($req) use ($user) {
                $hasQuoted = ExternalQuote::where('request_id', $req->id)
                    ->where('supplier_id', $user->supplier_id)
                    ->exists();
                $req->has_quoted = $hasQuoted;
                return $req;
            });
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /** 3. 提交报价 */
    public function submitQuote(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'items'           => ['required', 'array', 'min:1'],
            'items.*.name'    => ['required', 'string', 'max:200'],
            'items.*.qty'     => ['required', 'numeric', 'min:0.01'],
            'items.*.unit'    => ['nullable', 'string', 'max:20'],
            'items.*.price'   => ['required', 'numeric', 'min:0'],
            'total_amount'    => ['required', 'numeric', 'min:0.01'],
            'valid_until'     => ['nullable', 'date'],
            'lead_time_days'  => ['nullable', 'integer', 'min:0'],
            'payment_terms'   => ['nullable', 'string', 'max:20'],
            'attachments'     => ['nullable', 'array'],
            'note'            => ['nullable', 'string', 'max:2000'],
        ]);

        $quote = $this->service->submitQuote(
            requestId:  $id,
            supplierId: (int) $user->supplier_id,
            data:       $validated,
            userId:     $user->id,
        );
        return response()->json(['code' => 0, 'data' => $quote], 201);
    }

    /** 4. 我方历史报价 */
    public function myQuotes(Request $request): JsonResponse
    {
        $user = $request->user();
        $list = ExternalQuote::where('supplier_id', $user->supplier_id)
            ->with(['request:id,code,title,status,deadline'])
            ->orderByDesc('submitted_at')
            ->get();
        return response()->json(['code' => 0, 'data' => $list]);
    }
}
