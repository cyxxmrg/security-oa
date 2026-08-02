<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceivable;
use App\Models\SupplierPayment;
use App\Models\SupplierPayable;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * V0.4.2 总账/应收应付控制器
 *
 * 路由 /api/ledger
 *  1.  GET   /ledger/suppliers                  供应商台账列表
 *  2.  GET   /ledger/suppliers/{id}             单供应商总账
 *  3.  GET   /ledger/suppliers/{id}/payables    某供应商应付明细
 *  4.  POST  /ledger/supplier-payments          新增付款
 *  5.  GET   /ledger/customers                  客户台账列表
 *  6.  GET   /ledger/customers/{id}             单客户总账
 *  7.  GET   /ledger/customers/{id}/receivables 某客户应收明细
 *  8.  POST  /ledger/customer-receipts          新增收款
 *  9.  GET   /ledger/summary                    财务概览
 *  10. GET   /ledger/supplier-payments/{id}     付款详情
 *  11. GET   /ledger/customer-receipts/{id}     收款详情
 *  12. GET   /ledger/aging                      账龄分析
 */
class LedgerController extends Controller
{
    public function __construct(private LedgerService $service) {}

    /** 1. 供应商台账列表（所有） */
    public function suppliers(Request $request): JsonResponse
    {
        $filters = $request->only([
            'supplier_id', 'project_id', 'status', 'from', 'to', 'page', 'per_page',
        ]);
        $result = $this->service->getSupplierLedgerOverview($filters);
        return response()->json(['code' => 0, 'data' => $result]);
    }

    /** 2. 单供应商总账 */
    public function supplierLedger(int $id, Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'from', 'to']);
        $result  = $this->service->getSupplierLedger($id, $filters);
        return response()->json(['code' => 0, 'data' => $result]);
    }

    /** 3. 某供应商应付明细 */
    public function supplierPayables(int $id, Request $request): JsonResponse
    {
        $query = SupplierPayable::where('supplier_id', $id)
            ->with(['project:id,name,project_no']);
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        $list = $query->orderBy('due_date')->paginate(20);
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /** 4. 新增供应商付款 */
    public function createSupplierPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'         => ['required', 'integer', 'exists:suppliers,id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'payment_date'        => ['required', 'date'],
            'method'              => ['required', Rule::in(['cash', 'bank', 'alipay', 'wechat', 'other'])],
            'voucher_no'          => ['nullable', 'string', 'max:50'],
            'bank_account'        => ['nullable', 'string', 'max:50'],
            'operator'            => ['nullable', 'string', 'max:50'],
            'remark'              => ['nullable', 'string', 'max:1000'],
            'allocations'         => ['required', 'array', 'min:1'],
            'allocations.*.payable_id' => ['required', 'integer'],
            'allocations.*.amount'     => ['required', 'numeric', 'min:0.01'],
        ]);

        // 校验分摊金额合计 = payment.amount
        $sum = array_sum(array_column($validated['allocations'], 'amount'));
        if (abs($sum - (float) $validated['amount']) > 0.01) {
            return response()->json([
                'code' => 1, 'msg' => "分摊金额合计 ¥{$sum} 与付款金额 ¥{$validated['amount']} 不一致",
            ], 422);
        }

        $payment = SupplierPayment::create([
            'supplier_id'   => $validated['supplier_id'],
            'amount'        => $validated['amount'],
            'payment_date'  => $validated['payment_date'],
            'method'        => $validated['method'],
            'voucher_no'    => $validated['voucher_no'] ?? null,
            'bank_account'  => $validated['bank_account'] ?? null,
            'operator'      => $validated['operator'] ?? null,
            'remark'        => $validated['remark'] ?? null,
            'allocations'   => $validated['allocations'],
            'created_by'    => $request->user()->id,
        ]);

        // 自动应用分摊
        $this->service->applySupplierPayment($payment->id);

        return response()->json(['code' => 0, 'data' => $payment->fresh()], 201);
    }

    /** 5. 客户台账列表 */
    public function customers(Request $request): JsonResponse
    {
        $filters = $request->only([
            'customer_id', 'project_id', 'status', 'receivable_type', 'from', 'to', 'page', 'per_page',
        ]);
        $result = $this->service->getCustomerLedgerOverview($filters);
        return response()->json(['code' => 0, 'data' => $result]);
    }

    /** 6. 单客户总账 */
    public function customerLedger(int $id, Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'receivable_type', 'from', 'to']);
        $result  = $this->service->getCustomerLedger($id, $filters);
        return response()->json(['code' => 0, 'data' => $result]);
    }

    /** 7. 客户应收明细 */
    public function customerReceivables(int $id, Request $request): JsonResponse
    {
        $query = CustomerReceivable::where('customer_id', $id)
            ->with(['project:id,name,project_no']);
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->input('receivable_type')) {
            $query->where('receivable_type', $type);
        }
        $list = $query->orderBy('due_date')->paginate(20);
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /** 8. 新增客户收款 */
    public function createCustomerReceipt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id'             => ['required', 'integer', 'exists:customers,id'],
            'project_id'              => ['nullable', 'integer', 'exists:projects,id'],
            'amount'                  => ['required', 'numeric', 'min:0.01'],
            'receipt_date'            => ['required', 'date'],
            'method'                  => ['required', Rule::in(['cash', 'bank', 'alipay', 'wechat', 'check', 'other'])],
            'voucher_no'              => ['nullable', 'string', 'max:50'],
            'bank_account'            => ['nullable', 'string', 'max:50'],
            'account_id'              => ['nullable', 'integer', 'exists:finance_accounts,id'],
            'operator'                => ['nullable', 'string', 'max:50'],
            'remark'                  => ['nullable', 'string', 'max:1000'],
            'allocations'             => ['required', 'array', 'min:1'],
            'allocations.*.receivable_id' => ['required', 'integer'],
            'allocations.*.amount'         => ['required', 'numeric', 'min:0.01'],
        ]);

        $sum = array_sum(array_column($validated['allocations'], 'amount'));
        if (abs($sum - (float) $validated['amount']) > 0.01) {
            return response()->json([
                'code' => 1, 'msg' => "分摊金额合计 ¥{$sum} 与收款金额 ¥{$validated['amount']} 不一致",
            ], 422);
        }

        $receipt = CustomerReceipt::create([
            'customer_id'  => $validated['customer_id'],
            'project_id'   => $validated['project_id'] ?? null,
            'amount'       => $validated['amount'],
            'receipt_date' => $validated['receipt_date'],
            'method'       => $validated['method'],
            'voucher_no'   => $validated['voucher_no'] ?? null,
            'bank_account' => $validated['bank_account'] ?? null,
            'account_id'   => $validated['account_id'] ?? null,
            'operator'     => $validated['operator'] ?? null,
            'remark'       => $validated['remark'] ?? null,
            'allocations'  => $validated['allocations'],
            'created_by'   => $request->user()->id,
        ]);

        $this->service->applyCustomerReceipt($receipt->id);

        return response()->json(['code' => 0, 'data' => $receipt->fresh()], 201);
    }

    /** 9. 财务概览 */
    public function summary(): JsonResponse
    {
        $supplierOverview = $this->service->getSupplierLedgerOverview();
        $customerOverview = $this->service->getCustomerLedgerOverview();

        // 7 日内到期
        $upcomingPayables = (float) SupplierPayable::whereIn('status', ['pending', 'partial'])
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->sum('balance');
        $upcomingReceivables = (float) CustomerReceivable::whereIn('status', ['pending', 'partial'])
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->sum('balance');

        return response()->json([
            'code' => 0,
            'data' => [
                'payable'  => [
                    'total'        => $supplierOverview['summary'],
                    'payable_count' => $supplierOverview['total'],
                ],
                'receivable' => [
                    'total'        => $customerOverview['summary'],
                    'receivable_count' => $customerOverview['total'],
                ],
                'upcoming_7d' => [
                    'payable'    => round($upcomingPayables, 2),
                    'receivable' => round($upcomingReceivables, 2),
                ],
            ],
        ]);
    }

    /** 10. 付款详情 */
    public function showSupplierPayment(int $id): JsonResponse
    {
        $payment = SupplierPayment::with(['supplier:id,name,code'])->findOrFail($id);
        return response()->json(['code' => 0, 'data' => $payment]);
    }

    /** 11. 收款详情 */
    public function showCustomerReceipt(int $id): JsonResponse
    {
        $receipt = CustomerReceipt::with(['customer:id,name'])->findOrFail($id);
        return response()->json(['code' => 0, 'data' => $receipt]);
    }

    /** 11b. 收款列表 (V1.2.12n) */
    public function customerReceipts(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 20));
        $perPage = max(1, min($perPage, 500));
        $q = CustomerReceipt::with(['customer:id,name', 'project:id,name', 'account:id,name', 'creator:id,name']);
        if ($request->filled('customer_id')) $q->where('customer_id', (int) $request->query('customer_id'));
        if ($request->filled('keyword')) {
            $kw = trim((string) $request->query('keyword'));
            $q->where(function ($x) use ($kw) {
                $x->where('voucher_no', 'like', "%{$kw}%")
                  ->orWhere('remark', 'like', "%{$kw}%");
            });
        }
        $list = $q->orderByDesc('receipt_date')->orderByDesc('id')->paginate($perPage);
        $total = (clone $q)->sum('amount');
        return response()->json([
            'code' => 0,
            'data' => [
                'list' => $list,
                'total_amount' => round((float) $total, 2),
                'count' => $list->total(),
            ],
        ]);
    }

    /** 12. 账龄分析 */
    public function aging(Request $request): JsonResponse
    {
        $type = $request->input('type', 'payable'); // payable / receivable
        if ($type === 'payable') {
            $rows = $this->agingByModel(SupplierPayable::query(), 'due_date');
        } else {
            $rows = $this->agingByModel(CustomerReceivable::query(), 'due_date');
        }
        return response()->json(['code' => 0, 'data' => $rows]);
    }

    /**
     * 账龄分桶：0-30 / 31-60 / 61-90 / 90+ / 未到期
     */
    private function agingByModel($query, string $dateCol): array
    {
        $today = now()->startOfDay();
        $isSupplier = str_contains(get_class($query->getModel()), 'SupplierPayable');
        $items = $query->whereIn('status', ['pending', 'partial', 'overdue'])->get();

        $buckets = [
            'not_due' => ['label' => '未到期', 'amount' => 0, 'count' => 0],
            '0_30'    => ['label' => '0-30天', 'amount' => 0, 'count' => 0],
            '31_60'   => ['label' => '31-60天', 'amount' => 0, 'count' => 0],
            '61_90'   => ['label' => '61-90天', 'amount' => 0, 'count' => 0],
            '90_plus' => ['label' => '90天+', 'amount' => 0, 'count' => 0],
        ];

        foreach ($items as $it) {
            $bal = (float) ($it->balance ?? 0);
            if ($it->due_date === null) {
                $key = 'not_due';
            } else {
                $days = (int) $today->diffInDays($it->due_date, false); // 负值=逾期
                $key = match (true) {
                    $days >= 0   => 'not_due',
                    $days >= -30 => '0_30',
                    $days >= -60 => '31_60',
                    $days >= -90 => '61_90',
                    default      => '90_plus',
                };
            }
            $buckets[$key]['amount'] += $bal;
            $buckets[$key]['count']  += 1;
        }
        return array_map(fn ($b) => [
            'key'    => $b['label'],
            'amount' => round($b['amount'], 2),
            'count'  => $b['count'],
        ], $buckets);
    }
}
