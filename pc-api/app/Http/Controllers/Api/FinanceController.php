<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ClearsListCache;
use App\Models\Receivable as ReceivableModel;
use App\Models\Payable as PayableModel;
use App\Models\FinancePayment;
use App\Models\FinanceAccount;
use App\Models\FinanceInvoice;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use App\Http\Middleware\EnforcePaginationLimit;

class FinanceController extends Controller
{
    use ClearsListCache;

    public function overview(Request $request): JsonResponse
    {
        $monthStart = now()->startOfMonth();
        $totalRevenue = ReceivableModel::where('received_date', '>=', $monthStart)->sum('received_amount');
        $totalReceivable = ReceivableModel::where('status', '!=', 'fully_paid')->sum('remaining_amount');
        $totalPayable = PayableModel::where('status', '!=', 'fully_paid')->sum('remaining_amount');
        return response()->json(['code' => 0, 'data' => compact('totalRevenue', 'totalReceivable', 'totalPayable')]);
    }

    // ===== 财务汇总 =====
    public function summary(Request $request): JsonResponse
    {
        $totalReceivable = ReceivableModel::sum('amount');
        $totalReceived = ReceivableModel::sum('received_amount');
        $totalPayable = PayableModel::sum('amount');
        $totalPaid = PayableModel::sum('paid_amount');
        $totalAccount = FinanceAccount::where('status', 'active')->sum('balance');
        $invoiceCount = FinanceInvoice::count();
        $invoiceIssued = FinanceInvoice::where('status', 'issued')->count();

        return response()->json(['code' => 0, 'data' => [
            'receivable' => ['total' => $totalReceivable, 'received' => $totalReceived, 'remaining' => $totalReceivable - $totalReceived],
            'payable' => ['total' => $totalPayable, 'paid' => $totalPaid, 'remaining' => $totalPayable - $totalPaid],
            'account' => ['total' => $totalAccount],
            'invoice' => ['total' => $invoiceCount, 'issued' => $invoiceIssued],
        ]]);
    }

    // ===== 付款记录 =====
    public function payments(Request $request): JsonResponse
    {
        $perPage = max(1, min((int)($request->integer('per_page') ?: EnforcePaginationLimit::maxPerPage()), 200));
        $query = \App\Models\FinancePayment::with(['account', 'receivable', 'payable', 'project:id,name']);
        // V1.2.16 fix: 只显示独立付款单, 排除收款/内部转账等
        $query->whereNull('receivable_id')->whereNull('payable_id')->whereNull('transfer_group_id');
        $paginated = $query->orderBy('payment_date', 'desc')->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        // V1.2.16: 附加 project_name 便于前端显示
        $paginated->getCollection()->transform(function ($p) {
            $p->project_name = $p->project?->name;
            return $p;
        });
        return response()->json(['code' => 0, 'data' => $paginated]);
    }

    /** V1.2.12o 新建独立付款单 (前端 Payment.vue 调用) */
    public function storePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payee'        => 'required|string|max:200',
            'amount'       => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method'       => 'nullable|string|max:50',
            'payment_no'   => 'nullable|string|max:100',
            'project_id'   => 'nullable|integer|exists:projects,id',
            'account_id'   => 'nullable|integer|exists:finance_accounts,id',
            'handler'      => 'nullable|string|max:50',
            'notes'        => 'nullable|string|max:2000',
            'supplier_id'  => 'nullable|integer|exists:suppliers,id',
            'contract_id'  => 'nullable|integer|exists:purchase_contracts,id',
        ]);

        $payment = \App\Models\FinancePayment::create([
            'payable_id'    => null,
            'account_id'    => $data['account_id'] ?? null,
            'project_id'    => $data['project_id'] ?? null,
            'supplier_id'   => $data['supplier_id'] ?? null,
            'amount'        => $data['amount'],
            'payment_date'  => $data['payment_date'],
            'method'        => $data['method'] ?? null,
            'voucher_no'    => $data['payment_no'] ?? null,
            'payee'         => $data['payee'] ?? null,
            'operator'      => $data['handler'] ?? ($request->user()->name ?? ''),
            'remark'        => $data['notes'] ?? null,
            'type'          => 'standalone',
        ]);

        // V1.2.12o: 自动扣减资金账户余额
        if (!empty($data['account_id'])) {
            $account = \App\Models\FinanceAccount::lockForUpdate()->find($data['account_id']);
            if ($account) $account->decrement('balance', $data['amount']);
        }

        // V1.2.16: 更新供应商应付账款 (两表同步: payables + supplier_payables)
        if (!empty($data['supplier_id']) && $data['amount'] > 0) {
            $remaining = (float) $data['amount'];

            // 同步 payables 表 (应付账款页在用)
            $oldPayables = \App\Models\Payable::where('supplier_id', $data['supplier_id'])
                ->where('remaining_amount', '>', 0)
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($oldPayables as $p) {
                if ($remaining <= 0) break;
                $bal = (float) $p->remaining_amount;
                if ($bal <= 0) continue;
                $apply = min($remaining, $bal);
                $newPaid = round((float) $p->paid_amount + $apply, 2);
                $newRemaining = round($bal - $apply, 2);
                $p->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'status'           => $newRemaining <= 0 ? 'fully_paid' : ($newPaid > 0 ? 'partial' : 'pending'),
                ]);
                $remaining = round($remaining - $apply, 2);
            }
            if ($remaining > 0) {
                \App\Models\Payable::create([
                    'supplier_id'      => $data['supplier_id'],
                    'project_id'       => $data['project_id'] ?? null,
                    'amount'           => (float) $data['amount'],
                    'paid_amount'      => (float) $data['amount'],
                    'remaining_amount' => 0,
                    'status'           => 'fully_paid',
                    'due_date'         => $data['payment_date'] ?? now(),
                    'notes'            => '付款单: ' . ($data['payment_no'] ?? '#' . $payment->id),
                ]);
            }

            // 同步 supplier_payables 表 (台账在用), 恢复 remaining 从原始金额
            $remaining = (float) $data['amount'];
            $newPayables = \App\Models\SupplierPayable::where('supplier_id', $data['supplier_id'])
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($newPayables as $p) {
                if ($remaining <= 0) break;
                $balance = (float) $p->balance;
                if ($balance <= 0) continue;
                $apply = min($remaining, $balance);
                $newPaid = round((float) $p->paid_amount + $apply, 2);
                $p->update([
                    'paid_amount' => $newPaid,
                    'status'      => ($newPaid >= (float) $p->amount) ? 'paid' : 'partial',
                ]);
                $remaining = round($remaining - $apply, 2);
            }
            if ($remaining > 0) {
                \App\Models\SupplierPayable::create([
                    'supplier_id' => $data['supplier_id'],
                    'project_id'  => $data['project_id'] ?? null,
                    'amount'      => (float) $data['amount'],
                    'paid_amount' => (float) $data['amount'],
                    'status'      => 'paid',
                    'due_date'    => $data['payment_date'] ?? now(),
                    'ref_no'      => $data['payment_no'] ?? ('FP-' . $payment->id),
                    'note'        => '付款单: ' . ($data['payment_no'] ?? '#' . $payment->id),
                    'created_by'  => $request->user()->id,
                ]);
            }
        }

        return response()->json(['code' => 0, 'data' => $payment->fresh(), 'message' => '付款单已创建'], 201);
    }

    // ===== 应收 =====
    public function receivables(Request $request): JsonResponse
    {
        // V1.3.1: 缓存响应 JSON, 避免 Eloquent 序列化开销
        $cacheKey = 'receivables:index:' . ($request->user()?->id ?? 0) . ':' . md5(serialize($request->all()));
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json(json_decode($cached, true));
        }

        $perPage = max(1, min((int)($request->integer('per_page') ?: EnforcePaginationLimit::maxPerPage()), 200));
        $query = ReceivableModel::with(['customer', 'project']);
        if ($cid = $request->query('customer_id')) $query->where('customer_id', $cid);
        if ($pid = $request->query('project_id')) $query->where('project_id', $pid);
        if ($st = $request->query('status')) $query->where('status', $st);
        if ($kw = $request->query('keyword')) $query->whereHas('customer', fn($q) => $q->where('name', 'like', "%{$kw}%"));

        $result = $query->orderBy('due_date')->paginate($perPage)->appends($request->query());

        $json = json_encode(['code' => 0, 'data' => $result->toArray()]);
        Cache::put($cacheKey, $json, 30);

        return response()->json(json_decode($json, true));
    }

    public function storeReceivable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'required|numeric|min:0',
            'received_amount' => 'nullable|numeric|min:0',
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
        $data['received_amount'] = $data['received_amount'] ?? 0;
        $data['remaining_amount'] = $data['amount'] - $data['received_amount'];
        if ($data['remaining_amount'] <= 0) {
            $data['status'] = 'fully_paid';
            $data['received_date'] = now()->toDateString();
        } elseif ($data['received_amount'] > 0) {
            $data['status'] = 'partial';
        } else {
            $data['status'] = 'pending';
        }
        $this->clearListCache('receivables:index');
        return response()->json(['code' => 0, 'data' => ReceivableModel::create($data), 'message' => '应收已创建']);
    }

    public function updateReceivable(Request $request, ReceivableModel $receivable): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'sometimes|required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'received_amount' => 'nullable|numeric|min:0',
            'due_date' => 'sometimes|required|date',
            'notes' => 'nullable|string',
        ]);
        if (isset($data['amount']) || isset($data['received_amount'])) {
            $data['remaining_amount'] = ($data['amount'] ?? $receivable->amount) - ($data['received_amount'] ?? $receivable->received_amount);
        }
        $receivable->update($data);
        // 业务规则：自动同步状态
        if ($receivable->remaining_amount <= 0) {
            $receivable->update(['status' => 'fully_paid', 'received_date' => $receivable->received_date ?? now()->toDateString()]);
        } elseif ($receivable->received_amount > 0) {
            $receivable->update(['status' => 'partial']);
        }
        return response()->json(['code' => 0, 'data' => $receivable, 'message' => '已更新']);
    }

    public function destroyReceivable(ReceivableModel $receivable): JsonResponse
    {
        if ($receivable->received_amount > 0) {
        $this->clearListCache('receivables:index');
            return response()->json(['code' => 1001, 'message' => '该应收单已有收款记录，不允许删除'], 422);
        }
        $receivable->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    // ===== 应付 =====
    public function payables(Request $request): JsonResponse
    {
        $perPage = max(1, min((int)($request->integer('per_page') ?: EnforcePaginationLimit::maxPerPage()), 200));
        $query = PayableModel::with(['supplier', 'project']);
        if ($sid = $request->query('supplier_id')) $query->where('supplier_id', $sid);
        if ($pid = $request->query('project_id')) $query->where('project_id', $pid);
        if ($st = $request->query('status')) $query->where('status', $st);
        if ($kw = $request->query('keyword')) $query->whereHas('supplier', fn($q) => $q->where('name', 'like', "%{$kw}%"));
        return response()->json(['code' => 0, 'data' => $query->orderBy('due_date')->paginate($perPage)->appends($request->query())]);
    }

    public function storePayable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'due_date' => 'required|date',
            'payment_term' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $data['paid_amount'] = $data['paid_amount'] ?? 0;
        $data['remaining_amount'] = $data['amount'] - $data['paid_amount'];
        if ($data['remaining_amount'] <= 0) {
            $data['status'] = 'fully_paid';
            $data['paid_date'] = now()->toDateString();
        } elseif ($data['paid_amount'] > 0) {
            $data['status'] = 'partial';
        } else {
            $data['status'] = 'pending';
        }
        return response()->json(['code' => 0, 'data' => PayableModel::create($data), 'message' => '应付已创建']);
    }

    public function updatePayable(Request $request, PayableModel $payable): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'due_date' => 'sometimes|required|date',
            'payment_term' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        if (isset($data['amount']) || isset($data['paid_amount'])) {
            $data['remaining_amount'] = ($data['amount'] ?? $payable->amount) - ($data['paid_amount'] ?? $payable->paid_amount);
        }
        $payable->update($data);
        if ($payable->remaining_amount <= 0) {
            $payable->update(['status' => 'fully_paid', 'paid_date' => $payable->paid_date ?? now()->toDateString()]);
        } elseif ($payable->paid_amount > 0) {
            $payable->update(['status' => 'partial']);
        }
        return response()->json(['code' => 0, 'data' => $payable, 'message' => '已更新']);
    }

    public function destroyPayable(PayableModel $payable): JsonResponse
    {
        if ($payable->paid_amount > 0) {
            return response()->json(['code' => 1001, 'message' => '该应付单已有付款记录，不允许删除'], 422);
        }
        $payable->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    // ===== 收款管理（应收子操作） =====
    /**
     * 收一笔款（部分或全额）。事务：建 finance_payments + 加 receivable.received_amount + 加 account.balance
     */
    public function storeReceivablePayment(Request $request, ReceivableModel $receivable): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'account_id' => 'nullable|exists:finance_accounts,id',
            'method' => 'nullable|string|max:50',
            'voucher_no' => 'nullable|string|max:100',
            'operator' => 'nullable|string|max:50',
            'remark' => 'nullable|string',
        ]);
        $amount = (float)$data['amount'];
        $remaining = (float)$receivable->remaining_amount;
        if ($amount - $remaining > 0.0001) {
            return response()->json(['code' => 1002, 'message' => "本次收款({$amount})超过未收金额({$remaining})"], 422);
        }
        $payment = DB::transaction(function () use ($data, $amount, $receivable) {
            $data['receivable_id'] = $receivable->id;
            $data['amount'] = $amount;
            $payment = FinancePayment::create($data);
            $newReceived = (float)$receivable->received_amount + $amount;
            $newRemaining = max(0, (float)$receivable->amount - $newReceived);
            $status = $newRemaining <= 0.0001 ? 'fully_paid' : 'partial';
            $receivable->update([
                'received_amount' => $newReceived,
                'remaining_amount' => $newRemaining,
                'status' => $status,
                'received_date' => $newRemaining <= 0.0001 ? ($receivable->received_date ?? $data['payment_date']) : $receivable->received_date,
            ]);
            // 入账到指定资金账户
            if (!empty($data['account_id'])) {
                $account = FinanceAccount::lockForUpdate()->find($data['account_id']);
                if ($account) {
                    $account->increment('balance', $amount);
                }
            }
            return $payment;
        });
        return response()->json(['code' => 0, 'data' => $payment->load('account'), 'message' => '收款已登记']);
    }

    public function receivablePayments(Request $request, ReceivableModel $receivable): JsonResponse
    {
        $list = FinancePayment::with('account')
            ->where('receivable_id', $receivable->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();
        return response()->json(['code' => 0, 'data' => $list]);
    }

    /**
     * 收完关闭（force fully_paid，剩余金额视为已收齐）
     */
    public function closeReceivable(Request $request, ReceivableModel $receivable): JsonResponse
    {
        if ($receivable->status === 'fully_paid') {
            return response()->json(['code' => 1003, 'message' => '该应收单已收完'], 422);
        }
        $data = $request->validate([
            'account_id' => 'nullable|exists:finance_accounts,id',
            'payment_date' => 'nullable|date',
            'method' => 'nullable|string|max:50',
            'voucher_no' => 'nullable|string|max:100',
            'operator' => 'nullable|string|max:50',
            'remark' => 'nullable|string',
        ]);
        $amount = (float)$receivable->remaining_amount;
        $paymentDate = $data['payment_date'] ?? now()->toDateString();
        DB::transaction(function () use ($data, $amount, $receivable, $paymentDate) {
            if ($amount > 0) {
                FinancePayment::create([
                    'receivable_id' => $receivable->id,
                    'account_id' => $data['account_id'] ?? null,
                    'amount' => $amount,
                    'payment_date' => $paymentDate,
                    'method' => $data['method'] ?? null,
                    'voucher_no' => $data['voucher_no'] ?? null,
                    'operator' => $data['operator'] ?? null,
                    'remark' => $data['remark'] ?? '一次性收完',
                ]);
                if (!empty($data['account_id'])) {
                    $account = FinanceAccount::lockForUpdate()->find($data['account_id']);
                    if ($account) $account->increment('balance', $amount);
                }
            }
            $receivable->update([
                'received_amount' => $receivable->amount,
                'remaining_amount' => 0,
                'status' => 'fully_paid',
                'received_date' => $receivable->received_date ?? $paymentDate,
            ]);
        });
        return response()->json(['code' => 0, 'data' => $receivable->fresh(), 'message' => '应收已关闭']);
    }

    // ===== 付款管理（应付子操作） =====
    public function storePayablePayment(Request $request, PayableModel $payable): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'account_id' => 'nullable|exists:finance_accounts,id',
            'method' => 'nullable|string|max:50',
            'voucher_no' => 'nullable|string|max:100',
            'operator' => 'nullable|string|max:50',
            'remark' => 'nullable|string',
        ]);
        $amount = (float)$data['amount'];
        $remaining = (float)$payable->remaining_amount;
        // V1.2.16 fix: 允许预付(超过应付的付款), 移除限制
        // if ($amount - $remaining > 0.0001) { ... }

        $payment = DB::transaction(function () use ($data, $amount, $payable) {
            $data['payable_id'] = $payable->id;
            $data['amount'] = $amount;
            $payment = FinancePayment::create($data);
            $newPaid = (float)$payable->paid_amount + $amount;
            // V1.2.16 fix: 去掉 max(0,) 截断, remaining_amount 允许负数表示预付
            $newRemaining = round((float)$payable->amount - $newPaid, 2);
            $status = $newRemaining < 0 ? 'overpaid' : ($newRemaining <= 0.0001 ? 'fully_paid' : 'partial');
            $payable->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => $newRemaining,
                'status' => $status,
                'paid_date' => $newRemaining <= 0.0001 ? ($payable->paid_date ?? $data['payment_date']) : $payable->paid_date,
            ]);
            // 出账从资金账户扣减
            if (!empty($data['account_id'])) {
                $account = FinanceAccount::lockForUpdate()->find($data['account_id']);
                if ($account) $account->decrement('balance', $amount);
            }
            return $payment;
        });
        return response()->json(['code' => 0, 'data' => $payment->load('account'), 'message' => '付款已登记']);
    }

    public function payablePayments(Request $request, PayableModel $payable): JsonResponse
    {
        $list = FinancePayment::with('account')
            ->where('payable_id', $payable->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();
        return response()->json(['code' => 0, 'data' => $list]);
    }

    // ===== 资金账户 =====
    public function accounts(Request $request): JsonResponse
    {
        $query = FinanceAccount::query();
        if ($t = $request->query('type')) $query->where('type', $t);
        if ($st = $request->query('status')) $query->where('status', $st);
        if ($kw = $request->query('keyword')) $query->where(function ($q) use ($kw) {
            $q->where('name', 'like', "%{$kw}%")->orWhere('bank_name', 'like', "%{$kw}%")->orWhere('account_no', 'like', "%{$kw}%");
        });
        // V1.2.16 fix: 接受 per_page 参数, 否则 Laravel 默认 15 条/页, 新增账户在第 2 页看不到
        $perPage = max(1, min((int)($request->integer('per_page') ?: EnforcePaginationLimit::maxPerPage()), 200));
        $data = $query->orderBy('id')->paginate($perPage)->appends($request->query());
        // 统计
        $stats = [
            'total_balance'      => (float)FinanceAccount::where('status', 'active')->sum('balance'),
            'count'              => FinanceAccount::count(),
            'active_count'       => FinanceAccount::where('status', 'active')->count(),
            // V1.2.16 fix: 本月交易笔数 — 避免前端逐账户循环 N 次
            'monthly_txn_count'  => (int)FinancePayment::whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->count(),
        ];
        return response()->json(['code' => 0, 'data' => $data, 'stats' => $stats]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => ['required', Rule::in(['bank', 'cash', 'alipay', 'wechat', 'other'])],
            'balance' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:100',
            'account_no' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'frozen', 'closed'])],
            'remark' => 'nullable|string',
        ]);
        $data['balance'] = $data['balance'] ?? 0;
        $data['currency'] = $data['currency'] ?? 'CNY';
        $data['status'] = $data['status'] ?? 'active';
        return response()->json(['code' => 0, 'data' => FinanceAccount::create($data), 'message' => '账户已创建']);
    }

    public function updateAccount(Request $request, FinanceAccount $account): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'type' => ['sometimes', 'required', Rule::in(['bank', 'cash', 'alipay', 'wechat', 'other'])],
            'balance' => 'sometimes|nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:100',
            'account_no' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'frozen', 'closed'])],
            'remark' => 'nullable|string',
        ]);
        $account->update($data);
        return response()->json(['code' => 0, 'data' => $account, 'message' => '账户已更新']);
    }

    public function destroyAccount(FinanceAccount $account): JsonResponse
    {
        if ((float)$account->balance > 0.0001 || (float)$account->balance < -0.0001) {
            return response()->json(['code' => 1004, 'message' => '账户余额不为0，请先转出或结清'], 422);
        }
        if (FinancePayment::where('account_id', $account->id)->exists()) {
            return response()->json(['code' => 1005, 'message' => '该账户存在流水记录，不允许删除'], 422);
        }
        $account->delete();
        return response()->json(['code' => 0, 'message' => '账户已删除']);
    }

    /**
     * 公司内部转账：从 from_account 扣减 → to_account 增加 → 各记一条 payment（type 通过 method 区分）
     */
    /**
     * V1.2.16: 内部转账明细列表 — 按 transfer_group_id 聚合, 一笔转账显示一行
     */
    public function internalTransfers(Request $request): JsonResponse
    {
        $perPage = max(1, min((int)($request->integer('per_page') ?: EnforcePaginationLimit::maxPerPage()), 200));
        $query = DB::table('finance_payments')
            ->where('is_internal_transfer', true)
            ->whereNotNull('transfer_group_id');
        if ($kw = $request->query('keyword')) {
            $query->where(function ($q) use ($kw) {
                $q->where('transfer_group_id', 'like', "%{$kw}%")
                  ->orWhere('remark', 'like', "%{$kw}%")
                  ->orWhere('voucher_no', 'like', "%{$kw}%");
            });
        }
        if ($from = $request->query('date_from')) $query->where('payment_date', '>=', $from);
        if ($to   = $request->query('date_to'))   $query->where('payment_date', '<=', $to);

        // 用 group_id 聚合
        $rows = (clone $query)
            ->select([
                'transfer_group_id',
                DB::raw('MAX(payment_date) AS payment_date'),
                DB::raw('MAX(method) AS method'),
                DB::raw('MAX(voucher_no) AS voucher_no'),
                DB::raw('MAX(operator) AS operator'),
                DB::raw('MAX(remark) AS remark'),
                DB::raw('MAX(created_at) AS created_at'),
                DB::raw('COUNT(*) AS item_count'),
                DB::raw('SUM(amount) AS net_amount'),
            ])
            ->groupBy('transfer_group_id')
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->paginate($perPage)->appends($request->query());

        // 加载源/目标账户 (一笔转账两条记录, 一正一负, 负数是源)
        $allGroupIds = collect($rows->items())->pluck('transfer_group_id')->all();
        $groupPayments = $allGroupIds ? DB::table('finance_payments')
            ->whereIn('transfer_group_id', $allGroupIds)
            ->orderBy('id')
            ->get()
            ->groupBy('transfer_group_id') : collect();

        $accountIds = $groupPayments->flatten()->pluck('account_id')->filter()->unique()->values()->all();
        $accounts = $accountIds ? DB::table('finance_accounts')
            ->whereIn('id', $accountIds)
            ->select('id', 'name', 'type')->get()->keyBy('id') : collect();

        $data = collect($rows->items())->map(function ($r) use ($groupPayments, $accounts) {
            $payments = $groupPayments->get($r->transfer_group_id, collect());
            $out = $payments->firstWhere('amount', '<', 0);
            $in  = $payments->firstWhere('amount', '>', 0);
            return [
                'transfer_group_id' => $r->transfer_group_id,
                'payment_date'      => $r->payment_date,
                'method'            => $r->method,
                'voucher_no'        => $r->voucher_no,
                'operator'          => $r->operator,
                'remark'            => $r->remark,
                'created_at'        => $r->created_at,
                'item_count'        => (int)$r->item_count,
                'amount'            => abs((float)($in->amount ?? $out->amount ?? 0)),
                'from_account'      => $out ? ['id' => $out->account_id, 'name' => optional($accounts->get($out->account_id))->name] : null,
                'to_account'        => $in  ? ['id' => $in->account_id,  'name' => optional($accounts->get($in->account_id))->name]  : null,
            ];
        });

        // 统计
        $stats = [
            'count'       => DB::table('finance_payments')->where('is_internal_transfer', true)->distinct()->count('transfer_group_id'),
            'total_amount'=> (float)DB::table('finance_payments')->where('is_internal_transfer', true)->where('amount', '>', 0)->sum('amount'),
        ];

        return response()->json([
            'code' => 0,
            'data' => [
                'data' => $data,
                'total' => $rows->total(),
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'last_page' => $rows->lastPage(),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * V1.2.16: 单笔内部转账详情
     */
    public function internalTransferDetail(string $groupId): JsonResponse
    {
        $rows = FinancePayment::with('account')
            ->where('transfer_group_id', $groupId)
            ->orderBy('id')
            ->get();
        if ($rows->isEmpty()) {
            return response()->json(['code' => 1004, 'message' => '转账记录不存在'], 404);
        }
        $out = $rows->firstWhere('amount', '<', 0);
        $in  = $rows->firstWhere('amount', '>', 0);
        $first = $rows->first();
        return response()->json([
            'code' => 0,
            'data' => [
                'transfer_group_id' => $groupId,
                'payment_date'      => $first->payment_date,
                'method'            => $first->method,
                'voucher_no'        => $first->voucher_no,
                'operator'          => $first->operator,
                'remark'            => $first->remark,
                'created_at'        => $first->created_at,
                'amount'            => abs((float)($in->amount ?? $out->amount ?? 0)),
                'from_account'      => $out ? ['id' => $out->account_id, 'name' => optional($out->account)->name, 'balance_after' => (float)$out->account?->balance] : null,
                'to_account'        => $in  ? ['id' => $in->account_id,  'name' => optional($in->account)->name,  'balance_after' => (float)$in->account?->balance]  : null,
                'items'             => $rows->map(fn($r) => [
                    'id'           => $r->id,
                    'account_id'   => $r->account_id,
                    'account_name' => optional($r->account)->name,
                    'amount'       => (float)$r->amount,
                    'direction'    => $r->amount < 0 ? 'out' : 'in',
                ])->values(),
            ],
        ]);
    }

    public function transferAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_account_id' => 'required|exists:finance_accounts,id|different:to_account_id',
            'to_account_id' => 'required|exists:finance_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',  // 兼容前端 transfer_date
            'transfer_date' => 'nullable|date', // 前端字段名
            'method' => 'nullable|string|max:50',
            'voucher_no' => 'nullable|string|max:100',
            'operator' => 'nullable|string|max:50',
            'remark' => 'nullable|string',
        ]);
        // 兼容前端 transfer_date 字段
        $data['payment_date'] = $data['payment_date'] ?? $data['transfer_date'] ?? now()->toDateString();
        $amount = (float)$data['amount'];
        $result = DB::transaction(function () use ($data, $amount) {
            $from = FinanceAccount::lockForUpdate()->find($data['from_account_id']);
            $to = FinanceAccount::lockForUpdate()->find($data['to_account_id']);
            if ((float)$from->balance < $amount) {
                abort(response()->json(['code' => 1006, 'message' => '转出账户余额不足'], 422));
            }
            $from->decrement('balance', $amount);
            $to->increment('balance', $amount);
            $remark = $data['remark'] ?? "内部转账: {$from->name} → {$to->name}";
            // V1.2.16: 同一笔转账共享 transfer_group_id, 用于列表聚合
            $transferGroupId = 'TRF-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $outPayment = FinancePayment::create([
                'account_id' => $from->id,
                'amount' => -$amount, // 负数表示转出
                'payment_date' => $data['payment_date'],
                'method' => $data['method'] ?? '内部转账',
                'voucher_no' => $data['voucher_no'] ?? null,
                'transfer_group_id' => $transferGroupId,
                'is_internal_transfer' => true,
                'operator' => $data['operator'] ?? null,
                'remark' => $remark,
            ]);
            $inPayment = FinancePayment::create([
                'account_id' => $to->id,
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'method' => $data['method'] ?? '内部转账',
                'voucher_no' => $data['voucher_no'] ?? null,
                'transfer_group_id' => $transferGroupId,
                'is_internal_transfer' => true,
                'operator' => $data['operator'] ?? null,
                'remark' => $remark,
            ]);
            return [$outPayment, $inPayment];
        });
        return response()->json(['code' => 0, 'data' => $result, 'message' => '转账成功']);
    }

    public function accountTransactions(Request $request, FinanceAccount $account): JsonResponse
    {
        $query = FinancePayment::with('receivable', 'payable')
            ->where('account_id', $account->id);
        if ($from = $request->query('from')) $query->where('payment_date', '>=', $from);
        if ($to = $request->query('to')) $query->where('payment_date', '<=', $to);
        $list = $query->orderByDesc('payment_date')->orderByDesc('id')->paginate();
        return response()->json(['code' => 0, 'data' => $list]);
    }

    // ===== 发票管理 =====
    public function invoices(Request $request): JsonResponse
    {
        $query = FinanceInvoice::with(['customer', 'supplier', 'project', 'receivable', 'contract', 'applicant']);
        if ($dir = $request->query('direction')) $query->where('direction', $dir);
        if ($cid = $request->query('customer_id')) $query->where('customer_id', $cid);
        if ($pid = $request->query('project_id')) $query->where('project_id', $pid);
        if ($st = $request->query('status')) $query->where('status', $st);
        if ($kw = $request->query('keyword')) $query->where(function ($q) use ($kw) {
            $q->where('invoice_no', 'like', "%{$kw}%")->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$kw}%"));
        });
        $data = $query->orderByDesc('issue_date')->orderByDesc('id')->paginate();
        $stats = [
            'total_amount'  => (float)FinanceInvoice::where('status', 'issued')->sum('total_amount'),
            'total_tax'     => (float)FinanceInvoice::where('status', 'issued')->sum('tax_amount'),
            'count'         => FinanceInvoice::count(),
            'issued_count'  => FinanceInvoice::where('status', 'issued')->count(),
        ];
        return response()->json(['code' => 0, 'data' => $data, 'stats' => $stats]);
    }

    public function showInvoice(FinanceInvoice $invoice): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $invoice->load(['customer', 'project', 'receivable', 'contract', 'applicant'])]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_no' => 'nullable|string|max:50', // 改为可选，自动生成
            'direction' => 'nullable|in:sales,purchase', // V1.2.10: 销售发票/采购发票
            'invoice_type' => ['nullable', Rule::in(['special', 'ordinary', 'electronic'])],
            'customer_id' => 'nullable|exists:customers,id', // 改为可选
            'supplier_id' => 'nullable|exists:suppliers,id',
            'project_id' => 'nullable|exists:projects,id',
            'receivable_id' => 'nullable|exists:receivables,id',
            'contract_id' => 'nullable|exists:project_contracts,id',
            'amount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'issue_date' => 'nullable|date',
            'status' => 'nullable|string', // 移除严格限制，允许任意状态
            'remark' => 'nullable|string',
        ]);
        // 自动生成发票号
        $data['invoice_no'] = $data['invoice_no'] ?? 'INV' . date('YmdHis') . rand(100, 999);
        $amount = (float)($data['amount'] ?? 0);
        $taxRate = (float)($data['tax_rate'] ?? 0);
        $data['tax_amount'] = isset($data['tax_amount']) ? (float)$data['tax_amount'] : round($amount * $taxRate / 100, 2);
        $data['total_amount'] = isset($data['total_amount']) ? (float)$data['total_amount'] : round($amount + ($data['tax_amount'] ?? 0), 2);
        $data['invoice_type'] = $data['invoice_type'] ?? 'ordinary';
        $data['status'] = $data['status'] ?? 'requested'; // V1.2.10: 默认申请状态
        $data['applicant_id'] = $data['applicant_id'] ?? $request->user()->id; // 自动记录申请人
        $data['direction'] = $data['direction'] ?? 'sales'; // 默认销售发票
        // 兜底：NOT NULL 字段
        $data['issue_date']   = $data['issue_date'] ?? now()->toDateString();
        $data['amount']       = isset($data['amount']) ? (float)$data['amount'] : 0;
        $data['tax_rate']     = isset($data['tax_rate']) ? (float)$data['tax_rate'] : 0;
        $data['tax_amount']   = isset($data['tax_amount']) ? (float)$data['tax_amount'] : round($data['amount'] * $data['tax_rate'] / 100, 2);
        $data['total_amount'] = isset($data['total_amount']) ? (float)$data['total_amount'] : round($data['amount'] + $data['tax_amount'], 2);
        return response()->json(['code' => 0, 'data' => FinanceInvoice::create($data), 'message' => '发票已创建']);
    }

    public function updateInvoice(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'invoice_no' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('finance_invoices', 'invoice_no')->ignore($invoice->id)],
            'invoice_type' => ['nullable', Rule::in(['special', 'ordinary', 'electronic'])],
            'customer_id' => 'sometimes|required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'receivable_id' => 'nullable|exists:receivables,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'issue_date' => 'sometimes|required|date',
            'status' => ['nullable', Rule::in(['draft', 'issued', 'cancelled'])],
            'remark' => 'nullable|string',
        ]);
        // 重新算税与合计
        if (isset($data['amount']) || isset($data['tax_rate']) || isset($data['tax_amount'])) {
            $amount = (float)($data['amount'] ?? $invoice->amount);
            $taxRate = (float)($data['tax_rate'] ?? $invoice->tax_rate);
            if (!isset($data['tax_amount'])) {
                $data['tax_amount'] = round($amount * $taxRate / 100, 2);
            }
            if (!isset($data['total_amount'])) {
                $data['total_amount'] = round((float)$data['amount'] + (float)$data['tax_amount'], 2);
            }
        }
        $invoice->update($data);
        return response()->json(['code' => 0, 'data' => $invoice, 'message' => '已更新']);
    }

    public function destroyInvoice(FinanceInvoice $invoice): JsonResponse
    {
        if ($invoice->status === 'issued') {
            return response()->json(['code' => 1007, 'message' => '已开出的发票不允许直接删除，请先冲红'], 422);
        }
        $invoice->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    // ===== 报表：账龄分析 =====
    public function agingSummary(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $buckets = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
        $totalReceivable = 0;
        $totalPayable = 0;

        $receivables = ReceivableModel::where('status', '!=', 'fully_paid')->get();
        $receivableAging = $buckets;
        foreach ($receivables as $r) {
            $days = $r->due_date ? (int)floor((strtotime($today) - strtotime($r->due_date)) / 86400) : 0;
            $remaining = (float)$r->remaining_amount;
            $totalReceivable += $remaining;
            if ($days <= 0) $receivableAging['0-30'] += $remaining;
            elseif ($days <= 30) $receivableAging['0-30'] += $remaining;
            elseif ($days <= 60) $receivableAging['31-60'] += $remaining;
            elseif ($days <= 90) $receivableAging['61-90'] += $remaining;
            else $receivableAging['90+'] += $remaining;
        }

        $payables = PayableModel::where('status', '!=', 'fully_paid')->get();
        $payableAging = $buckets;
        foreach ($payables as $p) {
            $days = $p->due_date ? (int)floor((strtotime($today) - strtotime($p->due_date)) / 86400) : 0;
            $remaining = (float)$p->remaining_amount;
            $totalPayable += $remaining;
            if ($days <= 30) $payableAging['0-30'] += $remaining;
            elseif ($days <= 60) $payableAging['31-60'] += $remaining;
            elseif ($days <= 90) $payableAging['61-90'] += $remaining;
            else $payableAging['90+'] += $remaining;
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'as_of' => $today,
                'receivable' => ['total' => round($totalReceivable, 2), 'aging' => array_map(fn($v) => round($v, 2), $receivableAging)],
                'payable'    => ['total' => round($totalPayable, 2), 'aging' => array_map(fn($v) => round($v, 2), $payableAging)],
            ],
        ]);
    }

    // ===== 报表：现金流 =====
    public function cashflowSummary(Request $request): JsonResponse
    {
        $months = (int)$request->query('months', 6);
        $months = max(1, min(24, $months));
        $start = now()->subMonths($months - 1)->startOfMonth()->toDateString();

        $labels = [];
        $inflows = [];
        $outflows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $mStart = now()->subMonths($i)->startOfMonth();
            $mEnd = (clone $mStart)->endOfMonth();
            $labels[] = $mStart->format('Y-m');
            $inflows[] = (float)FinancePayment::whereNotNull('receivable_id')
                ->whereBetween('payment_date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum('amount');
            $outflows[] = (float)FinancePayment::whereNotNull('payable_id')
                ->whereBetween('payment_date', [$mStart->toDateString(), $mEnd->toDateString()])
                ->sum('amount');
        }

        $totalIn = array_sum($inflows);
        $totalOut = array_sum($outflows);

        return response()->json([
            'code' => 0,
            'data' => [
                'months' => $months,
                'labels' => $labels,
                'inflows' => $inflows,
                'outflows' => $outflows,
                'net' => array_map(fn($i, $o) => round($i - $o, 2), $inflows, $outflows),
                'total_in' => round($totalIn, 2),
                'total_out' => round($totalOut, 2),
                'net_total' => round($totalIn - $totalOut, 2),
            ],
        ]);
    }

    // ===== 兼容前端 /finance/receipts 收款单 =====
    public function receipts(Request $request): JsonResponse
    {
        // 收款单 = FinancePayment (receivable_id IS NOT NULL)
        $list = FinancePayment::with(['receivable.customer', 'account'])
            ->whereNotNull('receivable_id')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($request->query('per_page', 20));

        $total = FinancePayment::whereNotNull('receivable_id')->sum('amount');

        return response()->json([
            'code' => 0,
            'data' => [
                'list' => $list,
                'total_amount' => round((float)$total, 2),
                'count' => $list->total(),
            ],
        ]);
    }

    public function storeReceipt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receivable_id' => 'required|integer|exists:receivables,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'nullable|string|max:50',
            'account_id' => 'nullable|exists:finance_accounts,id',
            'voucher_no' => 'nullable|string|max:100',
            'remark' => 'nullable|string|max:255',
        ]);

        $receivable = ReceivableModel::findOrFail($data['receivable_id']);
        $amount = (float)$data['amount'];
        $remaining = (float)($receivable->remaining_amount ?? ($receivable->amount - ($receivable->received_amount ?? 0)));
        // V1.2.16 fix: 允许预付款(超过应收的收款), 移除超收限制
        // if ($amount - $remaining > 0.0001) { ... }

        $payment = DB::transaction(function () use ($data, $amount, $receivable) {
            $payment = FinancePayment::create($data);
            $newReceived = (float)$receivable->received_amount + $amount;
            // V1.2.16 fix: 去掉 max(0,) 截断, remaining_amount 允许负数表示预付款
            $newRemaining = round((float)$receivable->amount - $newReceived, 2);
            $newStatus = $newRemaining <= -0.0001 ? 'overpaid' : ($newRemaining <= 0.0001 ? 'fully_paid' : 'partial');
            $receivable->update([
                'received_amount' => $newReceived,
                'remaining_amount' => $newRemaining,
                'status' => $newStatus,
            ]);
            if (!empty($data['account_id'])) {
                $account = FinanceAccount::lockForUpdate()->find($data['account_id']);
                if ($account) $account->increment('balance', $amount);
            }
            return $payment;
        });

        return response()->json(['code' => 0, 'data' => $payment->load('account'), 'message' => '收款已登记']);
    }

    public function showReceipt($id): JsonResponse
    {
        $payment = FinancePayment::with('receivable.customer', 'account')->findOrFail($id);
        return response()->json(['code' => 0, 'data' => $payment]);
    }

    // ===== 兼容前端 /finance/transfers 转账记录 =====
    public function transfers(Request $request): JsonResponse
    {
        // 转账付款单（payable_id IS NOT NULL 或带 transfer 备注的）
        // 简单实现：列出所有 payable 类型的 FinancePayment
        $list = FinancePayment::with(['payable', 'account'])
            ->where(function ($q) {
                $q->whereNotNull('payable_id')->orWhere('remark', 'like', '%转账%');
            })
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($request->query('per_page', 20));

        $total = FinancePayment::whereNotNull('payable_id')->sum('amount');

        return response()->json([
            'code' => 0,
            'data' => [
                'list' => $list,
                'total_amount' => round((float)$total, 2),
                'count' => $list->total(),
            ],
        ]);
    }

    /** V1.2.16: 项目利润表 */
    public function projectProfit(Request $request): JsonResponse
    {
        $projects = \App\Models\Project::select('id', 'name', 'customer_id')->get()->keyBy('id');

        $receipts = \App\Models\CustomerReceipt::whereNotNull('project_id')
            ->selectRaw('project_id, sum(amount) as total')->groupBy('project_id')
            ->pluck('total', 'project_id');
        $receivables = \App\Models\Receivable::whereNotNull('project_id')
            ->selectRaw('project_id, sum(amount) as total')->groupBy('project_id')
            ->pluck('total', 'project_id');
        $stockOut = \App\Models\StockRecord::whereNotNull('project_id')->where('type', 'out')
            ->selectRaw('project_id, sum(total_amount) as total')->groupBy('project_id')
            ->pluck('total', 'project_id');
        $stockIn = \App\Models\StockRecord::whereNotNull('project_id')->where('type', 'in')
            ->selectRaw('project_id, sum(total_amount) as total')->groupBy('project_id')
            ->pluck('total', 'project_id');
        $payments = \App\Models\FinancePayment::whereNotNull('project_id')
            ->selectRaw('project_id, sum(amount) as total')->groupBy('project_id')
            ->pluck('total', 'project_id');
        $workOrders = \App\Models\WorkOrder::whereNotNull('project_id')
            ->selectRaw('project_id, sum(total_cost) as total')->groupBy('project_id')
            ->pluck('total', 'project_id');
        $expenses = \App\Models\ExpenseClaim::whereNotNull('project_id')->where('status', 'approved')
            ->selectRaw('project_id, sum(total_amount) as total')->groupBy('project_id')
            ->pluck('total', 'project_id');

        $rows = [];
        foreach ($projects as $pid => $proj) {
            $income = (float)($receipts[$pid] ?? 0) + (float)($receivables[$pid] ?? 0);
            $expense = (float)($stockOut[$pid] ?? 0) + (float)($stockIn[$pid] ?? 0)
                     + (float)($payments[$pid] ?? 0) + (float)($workOrders[$pid] ?? 0)
                     + (float)($expenses[$pid] ?? 0);
            $rows[] = [
                'project_id'   => $pid, 'project_name' => $proj->name,
                'income'       => round($income, 2), 'expense' => round($expense, 2),
                'profit'       => round($income - $expense, 2),
                '_detail'      => [
                    '收款收入' => round($receipts[$pid] ?? 0, 2),
                    '合同应收' => round($receivables[$pid] ?? 0, 2),
                    '材料出库' => round($stockOut[$pid] ?? 0, 2),
                    '采购入库' => round($stockIn[$pid] ?? 0, 2),
                    '对外付款' => round($payments[$pid] ?? 0, 2),
                    '工单人工' => round($workOrders[$pid] ?? 0, 2),
                    '报销费用' => round($expenses[$pid] ?? 0, 2),
                ],
            ];
        }
        usort($rows, fn($a, $b) => $b['profit'] <=> $a['profit']);

        $total = ['income' => round(array_sum(array_column($rows, 'income')), 2),
                  'expense' => round(array_sum(array_column($rows, 'expense')), 2),
                  'profit' => round(array_sum(array_column($rows, 'profit')), 2)];

        return response()->json(['code' => 0, 'data' => ['items' => $rows, 'total' => $total]]);
    }

}
