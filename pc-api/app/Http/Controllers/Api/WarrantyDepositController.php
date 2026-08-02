<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\WarrantyDeposit;
use App\Models\WarrantyDepositLog;
use App\Services\WarrantyDepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.5 质保金控制器
 *
 * 路由前缀 /api/warranty-deposits
 *  1. GET    /warranty-deposits                 列表
 *  2. POST   /warranty-deposits                 创建
 *  3. GET    /warranty-deposits/{id}            详情
 *  4. POST   /warranty-deposits/{id}/partial-release  部分释放
 *  5. POST   /warranty-deposits/{id}/full-release     全部释放
 *  6. POST   /warranty-deposits/{id}/forfeit          没收
 */
class WarrantyDepositController extends Controller
{
    public function __construct(protected WarrantyDepositService $service) {}

    // 1. 列表
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'project_id'  => $request->input('project_id'),
            'customer_id' => $request->input('customer_id'),
            'status'      => $request->input('status'),
            'keyword'     => $request->input('keyword'),
        ];

        $result = $this->service->listDeposits(
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 20),
            $filters
        );

        return response()->json([
            'code' => 0,
            'data' => [
                'items' => $result['items'],
                'total' => $result['total'],
                'current_page' => (int) $request->input('page', 1),
                'per_page' => (int) $request->input('per_page', 20),
            ],
        ]);
    }

    // 2. 创建
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'      => ['required', 'integer', 'exists:projects,id'],
            'customer_id'     => ['required', 'integer', 'exists:customers,id'],
            'contract_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'deposit_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_amount'  => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'hold_date'       => ['required', 'date'],
            'release_date'    => ['nullable', 'date', 'after_or_equal:hold_date'],
            'reason'          => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $deposit = $this->service->createDeposit($validated, $request->user()->id);
            return response()->json(['code' => 0, 'data' => $deposit, 'message' => '质保金记录创建成功'], 201);
        } catch (\Throwable $e) {
            \Log::error('创建质保金失败', ['err' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['code' => 1, 'message' => '创建失败: ' . $e->getMessage()], 422);
        }
    }

    // 3. 详情
    public function show(int $id): JsonResponse
    {
        $deposit = WarrantyDeposit::with([
            'project:id,name,project_no',
            'customer:id,name',
            'approver:id,name',
            'creator:id,name',
            'logs.bankAccount:id,name,bank_name,account_no',
        ])->findOrFail($id);

        return response()->json(['code' => 0, 'data' => $deposit]);
    }

    // 4. 部分释放
    public function partialRelease(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'reason'          => ['required', 'string', 'max:500'],
            'bank_account_id' => ['nullable', 'integer', 'exists:finance_accounts,id'],
            'beneficiary'     => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $before = WarrantyDeposit::findOrFail($id);
            $beforeStatus = $before->status;

            $deposit = $this->service->partialRelease($id, (float) $validated['amount'], $validated['reason'], $request->user()->id);

            // 写操作记录
            WarrantyDepositLog::create([
                'deposit_id'      => $id,
                'operation_type'  => 'partial_release',
                'amount'          => $validated['amount'],
                'before_status'   => $beforeStatus,
                'after_status'    => $deposit->status,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'beneficiary'     => $validated['beneficiary'] ?? null,
                'reason'          => $validated['reason'],
                'operator_id'     => $request->user()->id,
            ]);

            // 同步到资金账户：创建收款记录并增加余额
            if (!empty($validated['bank_account_id'])) {
                DB::transaction(function () use ($validated, $request) {
                    FinancePayment::create([
                        'account_id'   => $validated['bank_account_id'],
                        'amount'       => $validated['amount'],
                        'payment_date' => now()->toDateString(),
                        'method'       => 'bank_transfer',
                        'remark'       => '质保金释放: ' . ($validated['reason'] ?? ''),
                    ]);
                    FinanceAccount::where('id', $validated['bank_account_id'])
                        ->increment('balance', (float) $validated['amount']);
                });
            }

            return response()->json(['code' => 0, 'data' => $deposit, 'message' => '部分释放成功']);
        } catch (\Throwable $e) {
            \Log::error('部分释放失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '部分释放失败: ' . $e->getMessage()], 422);
        }
    }

    // 5. 全部释放
    public function fullRelease(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['nullable', 'integer', 'exists:finance_accounts,id'],
            'beneficiary'     => ['nullable', 'string', 'max:100'],
        ]);
        $validated['reason'] = $request->input('reason', '全部释放');

        try {
            $before = WarrantyDeposit::findOrFail($id);
            $beforeStatus = $before->status;

            $deposit = $this->service->fullRelease($id, $request->user()->id);

            // 写操作记录
            $remaining = (float) $before->deposit_amount - (float) $before->release_amount - (float) $before->forfeit_amount;
            $releaseAmount = max(0, $remaining);

            WarrantyDepositLog::create([
                'deposit_id'      => $id,
                'operation_type'  => 'full_release',
                'amount'          => $releaseAmount,
                'before_status'   => $beforeStatus,
                'after_status'    => $deposit->status,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'beneficiary'     => $validated['beneficiary'] ?? null,
                'operator_id'     => $request->user()->id,
            ]);

            // 同步到资金账户：创建收款记录并增加余额
            if (!empty($validated['bank_account_id'])) {
                DB::transaction(function () use ($validated, $releaseAmount, $request) {
                    FinancePayment::create([
                        'account_id'   => $validated['bank_account_id'],
                        'amount'       => $releaseAmount,
                        'payment_date' => now()->toDateString(),
                        'method'       => 'bank_transfer',
                        'remark'       => '质保金释放: ' . ($validated['reason'] ?? '全部释放'),
                    ]);
                    FinanceAccount::where('id', $validated['bank_account_id'])
                        ->increment('balance', (float) $releaseAmount);
                });
            }

            return response()->json(['code' => 0, 'data' => $deposit, 'message' => '全部释放成功']);
        } catch (\Throwable $e) {
            \Log::error('全部释放失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '全部释放失败: ' . $e->getMessage()], 422);
        }
    }

    // 6. 没收
    public function forfeit(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $before = WarrantyDeposit::findOrFail($id);
            $beforeStatus = $before->status;

            $deposit = $this->service->forfeit($id, (float) $validated['amount'], $validated['reason'], $request->user()->id);

            // 写操作记录
            WarrantyDepositLog::create([
                'deposit_id'     => $id,
                'operation_type' => 'forfeit',
                'amount'         => $validated['amount'],
                'before_status'  => $beforeStatus,
                'after_status'   => $deposit->status,
                'reason'         => $validated['reason'],
                'operator_id'    => $request->user()->id,
            ]);

            return response()->json(['code' => 0, 'data' => $deposit, 'message' => '质保金已没收']);
        } catch (\Throwable $e) {
            \Log::error('没收失败', ['err' => $e->getMessage(), 'id' => $id]);
            return response()->json(['code' => 1, 'message' => '没收失败: ' . $e->getMessage()], 422);
        }
    }
}
