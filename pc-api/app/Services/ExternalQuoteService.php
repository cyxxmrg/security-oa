<?php

namespace App\Services;

use App\Models\ExternalQuote;
use App\Models\ExternalQuoteRequest;
use App\Models\ProjectBudget;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * V0.4.2 对外报价服务
 *
 * 关键流程:
 *  - createRequest: 创建报价请求（自动生成 public_token）
 *  - submitQuote:   供应商提交报价
 *  - awardQuote:    中标 → 改状态 + 创建 PO + 创建 SupplierPayable
 *  - closeRequest / cancelRequest
 */
class ExternalQuoteService
{
    /**
     * 生成报价请求编号 EQR-YYYY-NNNN
     */
    public function generateRequestCode(): string
    {
        $year   = date('Y');
        $prefix = "EQR-{$year}-";
        $latest = ExternalQuoteRequest::where('code', 'like', $prefix . '%')
            ->orderByDesc('id')->value('code');
        $next = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) {
            $next = ((int) $m[1]) + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 生成 quote 编号 EQ-YYYY-NNNN
     */
    public function generateQuoteCode(): string
    {
        $year   = date('Y');
        $prefix = "EQ-{$year}-";
        $latest = ExternalQuote::where('code', 'like', $prefix . '%')
            ->orderByDesc('id')->value('code');
        $next = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $m)) {
            $next = ((int) $m[1]) + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 创建报价请求
     */
    public function createRequest(array $data, int $userId): ExternalQuoteRequest
    {
        return DB::transaction(function () use ($data, $userId) {
            return ExternalQuoteRequest::create([
                'project_id'     => $data['project_id'] ?? null,
                'code'           => $this->generateRequestCode(),
                'title'          => $data['title'] ?? '',
                'required_items' => $data['required_items'] ?? [],
                'required_files' => $data['required_files'] ?? [],
                'deadline'       => $data['deadline'] ?? null,
                'status'         => ExternalQuoteRequest::STATUS_OPEN,
                'public_token'   => (string) Str::uuid(),
                'created_by'     => $userId,
                'description'    => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * 提交报价（供应商门户调用）
     *
     * @param int $userId 提交人 user_id（供应商账号）
     */
    public function submitQuote(int $requestId, int $supplierId, array $data, int $userId): ExternalQuote
    {
        return DB::transaction(function () use ($requestId, $supplierId, $data, $userId) {
            $req = ExternalQuoteRequest::findOrFail($requestId);
            if ($req->status !== ExternalQuoteRequest::STATUS_OPEN) {
                throw new \RuntimeException('该报价请求已截止/取消，不可再提交');
            }

            // 防重：同一 supplier 同一 request 不允许多次
            $exists = ExternalQuote::where('request_id', $requestId)
                ->where('supplier_id', $supplierId)
                ->exists();
            if ($exists) {
                throw new \RuntimeException('该供应商已提交过报价');
            }

            return ExternalQuote::create([
                'request_id'     => $requestId,
                'supplier_id'    => $supplierId,
                'code'           => $this->generateQuoteCode(),
                'items'          => $data['items'] ?? [],
                'total_amount'   => $data['total_amount'] ?? 0,
                'valid_until'    => $data['valid_until'] ?? null,
                'lead_time_days' => $data['lead_time_days'] ?? 0,
                'payment_terms'  => $data['payment_terms'] ?? '30days',
                'attachments'    => $data['attachments'] ?? [],
                'note'           => $data['note'] ?? null,
                'submitted_by'   => $userId,
                'submitted_at'   => now(),
                'status'         => ExternalQuote::STATUS_SUBMITTED,
            ]);
        });
    }

    /**
     * 标记入围（不改 request 状态）
     */
    public function shortlistQuote(int $quoteId, int $userId): ExternalQuote
    {
        return DB::transaction(function () use ($quoteId, $userId) {
            $quote = ExternalQuote::findOrFail($quoteId);
            if (!in_array($quote->status, [ExternalQuote::STATUS_SUBMITTED, ExternalQuote::STATUS_SHORTLISTED], true)) {
                throw new \RuntimeException('当前状态不可入围');
            }
            $quote->update([
                'status'      => ExternalQuote::STATUS_SHORTLISTED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);
            return $quote->fresh();
        });
    }

    /**
     * 驳回
     */
    public function rejectQuote(int $quoteId, int $userId, ?string $reason = null): ExternalQuote
    {
        return DB::transaction(function () use ($quoteId, $userId) {
            $quote = ExternalQuote::findOrFail($quoteId);
            $quote->update([
                'status'      => ExternalQuote::STATUS_REJECTED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'note'        => $reason ? "驳回: {$reason}\n" . ($quote->note ?? '') : $quote->note,
            ]);
            return $quote->fresh();
        });
    }

    /**
     * 中标（核心流程）
     *
     * 1. external_quotes.status = awarded
     * 2. external_quote_requests.status = awarded, awarded_supplier_id, awarded_quote_id
     * 3. 创建 purchase_orders 记录
     * 4. 创建 supplier_payables 记录
     */
    public function awardQuote(int $quoteId, int $userId): array
    {
        return DB::transaction(function () use ($quoteId, $userId) {
            $quote = ExternalQuote::with('request')->findOrFail($quoteId);
            if ($quote->status === ExternalQuote::STATUS_AWARDED) {
                throw new \RuntimeException('该报价已中标');
            }
            $request = $quote->request;
            if (!$request) {
                throw new \RuntimeException('报价请求不存在');
            }
            if ($request->status !== ExternalQuoteRequest::STATUS_OPEN) {
                throw new \RuntimeException('该报价请求不可定标');
            }

            // 1) quote 中标
            $quote->update([
                'status'      => ExternalQuote::STATUS_AWARDED,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            // 2) request 标记
            $request->update([
                'status'              => ExternalQuoteRequest::STATUS_AWARDED,
                'awarded_supplier_id' => $quote->supplier_id,
                'awarded_quote_id'    => $quote->id,
            ]);

            // 3) 创建 PO
            $po = PurchaseOrder::create([
                'project_id'   => $request->project_id,
                'supplier_id'  => $quote->supplier_id,
                'po_no'        => $this->generatePoNo(),
                'total_amount' => $quote->total_amount,
                'status'       => 'draft',
                'notes'        => "来源: ExternalQuote #{$quote->code} / Request #{$request->code}",
            ]);

            // 4) 创建 supplier_payable
            $payable = SupplierPayable::create([
                'supplier_id'  => $quote->supplier_id,
                'project_id'   => $request->project_id,
                'source_type'  => SupplierPayable::SOURCE_QUOTE,
                'source_id'    => $quote->id,
                'ref_no'       => $po->po_no,
                'amount'       => $quote->total_amount,
                'paid_amount'  => 0,
                'status'       => SupplierPayable::STATUS_PENDING,
                'note'         => "由报价 {$quote->code} 自动生成",
                'created_by'   => $userId,
            ]);

            return [
                'quote'    => $quote->fresh(),
                'request'  => $request->fresh(),
                'po'       => $po,
                'payable'  => $payable,
            ];
        });
    }

    /**
     * 关闭报价请求（不再接受报价）
     */
    public function closeRequest(int $requestId): ExternalQuoteRequest
    {
        return DB::transaction(function () use ($requestId) {
            $req = ExternalQuoteRequest::findOrFail($requestId);
            if ($req->status !== ExternalQuoteRequest::STATUS_OPEN) {
                throw new \RuntimeException('只有征集中状态可关闭');
            }
            $req->update(['status' => ExternalQuoteRequest::STATUS_CLOSED]);
            return $req->fresh();
        });
    }

    /**
     * 取消报价请求
     */
    public function cancelRequest(int $requestId): ExternalQuoteRequest
    {
        return DB::transaction(function () use ($requestId) {
            $req = ExternalQuoteRequest::findOrFail($requestId);
            if (in_array($req->status, [ExternalQuoteRequest::STATUS_AWARDED, ExternalQuoteRequest::STATUS_CANCELLED], true)) {
                throw new \RuntimeException('已定标/已取消不可再操作');
            }
            $req->update(['status' => ExternalQuoteRequest::STATUS_CANCELLED]);
            return $req->fresh();
        });
    }

    /**
     * PO 编号生成 PO-YYYYMMDD-NNN
     */
    private function generatePoNo(): string
    {
        $today = date('Ymd');
        $prefix = "PO-{$today}-";
        $count = PurchaseOrder::where('po_no', 'like', $prefix . '%')->count();
        return $prefix . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * 报价请求列表（含聚合 quote 数）
     */
    public function listRequests(array $filters = []): array
    {
        $q = ExternalQuoteRequest::withCount('quotes')
            ->with(['project:id,name,project_no', 'creator:id,name', 'awardedSupplier:id,name,code']);

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $q->where(function ($w) use ($kw) {
                $w->where('code', 'like', "%{$kw}%")
                  ->orWhere('title', 'like', "%{$kw}%");
            });
        }
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['project_id'])) {
            $q->where('project_id', $filters['project_id']);
        }

        $total = (clone $q)->count();
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $size  = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $items = $q->orderByDesc('id')->skip(($page - 1) * $size)->take($size)->get();

        return ['items' => $items, 'total' => $total];
    }
}
