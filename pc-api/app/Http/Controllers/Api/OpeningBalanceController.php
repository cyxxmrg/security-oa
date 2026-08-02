<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receivable;
use App\Models\Payable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * V1.3.2 期初数据管理
 *
 * 职责:
 *  1. 录入应收/应付期初 (source='opening')
 *  2. 查看期初数据
 *  3. 锁定期初 (锁定后不可手动增删改)
 */
class OpeningBalanceController extends Controller
{
    /**
     * 期初数据是否已锁定
     */
    private function isLocked(): bool
    {
        return DB::table('system_settings')
            ->where('key', 'opening_balances_locked')
            ->value('value') === 'true';
    }

    /**
     * GET /api/settings/opening-balances/status — 查询锁定状态
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => [
                'locked' => $this->isLocked(),
            ],
        ]);
    }

    /**
     * POST /api/settings/opening-balances/lock — 锁定期初
     */
    public function lock(): JsonResponse
    {
        $user = request()->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['code' => 1003, 'message' => '仅管理员可锁定期初数据'], 403);
        }

        DB::table('system_settings')
            ->where('key', 'opening_balances_locked')
            ->update(['value' => 'true', 'updated_at' => now()]);

        return response()->json(['code' => 0, 'message' => '期初数据已锁定']);
    }

    /**
     * POST /api/settings/opening-balances/unlock — 解锁期初 (仅 system)
     */
    public function unlock(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->is_system) {
            return response()->json(['code' => 1003, 'message' => '仅 system 超级管理员可解锁期初数据'], 403);
        }

        DB::table('system_settings')
            ->where('key', 'opening_balances_locked')
            ->update(['value' => 'false', 'updated_at' => now()]);

        return response()->json(['code' => 0, 'message' => '期初数据已解锁']);
    }

    // ═══════════════════════════════════════════
    //  应收期初
    // ═══════════════════════════════════════════

    /**
     * GET /api/opening/receivables — 应收期初列表
     */
    public function receivables(Request $request): JsonResponse
    {
        $query = Receivable::where('source', 'opening');
        $perPage = max(1, min((int)($request->per_page ?? 15), 200));
        return response()->json([
            'code' => 0,
            'data' => $query->orderBy('created_at', 'desc')->paginate($perPage),
        ]);
    }

    /**
     * POST /api/opening/receivables — 新增应收期初
     */
    public function storeReceivable(Request $request): JsonResponse
    {
        if ($this->isLocked()) {
            return response()->json(['code' => 1002, 'message' => '期初数据已锁定，不可新增'], 422);
        }

        $data = $request->validate([
            'customer_id'      => 'required|integer|exists:customers,id',
            'project_id'       => 'nullable|integer|exists:projects,id',
            'contract_id'      => 'nullable|integer|exists:project_contracts,id',
            'amount'           => 'required|numeric|min:0',
            'due_date'         => 'required|date',
            'notes'            => 'nullable|string|max:500',
        ]);
        $data['source'] = 'opening';
        $data['received_amount'] = 0;
        $data['remaining_amount'] = $data['amount'];
        $data['overdue_days'] = 0;
        $data['status'] = 'pending';

        $receivable = Receivable::create($data);

        return response()->json(['code' => 0, 'message' => '应收期初已创建', 'data' => $receivable]);
    }

    /**
     * DELETE /api/opening/receivables/{id} — 删除应收期初
     */
    public function destroyReceivable(Receivable $receivable): JsonResponse
    {
        if ($this->isLocked()) {
            return response()->json(['code' => 1002, 'message' => '期初数据已锁定，不可删除'], 422);
        }
        if ($receivable->source !== 'opening') {
            return response()->json(['code' => 1004, 'message' => '非期初数据不可通过此接口删除'], 422);
        }
        $receivable->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    // ═══════════════════════════════════════════
    //  应付期初
    // ═══════════════════════════════════════════

    /**
     * GET /api/opening/payables — 应付期初列表
     */
    public function payables(Request $request): JsonResponse
    {
        $query = Payable::where('source', 'opening');
        $perPage = max(1, min((int)($request->per_page ?? 15), 200));
        return response()->json([
            'code' => 0,
            'data' => $query->orderBy('created_at', 'desc')->paginate($perPage),
        ]);
    }

    /**
     * POST /api/opening/payables — 新增应付期初
     */
    public function storePayable(Request $request): JsonResponse
    {
        if ($this->isLocked()) {
            return response()->json(['code' => 1002, 'message' => '期初数据已锁定，不可新增'], 422);
        }

        $data = $request->validate([
            'supplier_id'       => 'required|integer|exists:suppliers,id',
            'project_id'        => 'nullable|integer|exists:projects,id',
            'contract_id'       => 'nullable|integer|exists:purchase_contracts,id',
            'amount'            => 'required|numeric|min:0',
            'due_date'          => 'required|date',
            'notes'             => 'nullable|string|max:500',
        ]);
        $data['source'] = 'opening';
        $data['paid_amount'] = 0;
        $data['remaining_amount'] = $data['amount'];
        $data['status'] = 'pending';

        $payable = Payable::create($data);

        return response()->json(['code' => 0, 'message' => '应付期初已创建', 'data' => $payable]);
    }

    /**
     * DELETE /api/opening/payables/{id} — 删除应付期初
     */
    public function destroyPayable(Payable $payable): JsonResponse
    {
        if ($this->isLocked()) {
            return response()->json(['code' => 1002, 'message' => '期初数据已锁定，不可删除'], 422);
        }
        if ($payable->source !== 'opening') {
            return response()->json(['code' => 1004, 'message' => '非期初数据不可通过此接口删除'], 422);
        }
        $payable->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }
}
