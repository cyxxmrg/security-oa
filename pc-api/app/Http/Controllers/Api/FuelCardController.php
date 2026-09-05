<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{FuelCard, FuelCardRecharge, Vehicle};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuelCardController extends Controller
{
    // ========== 油卡管理 ==========

    public function index(Request $request): JsonResponse
    {
        $query = FuelCard::with(['vehicle', 'recharges']);
        if ($request->filled('vehicle_id')) $query->where('vehicle_id', $request->vehicle_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('keyword')) $query->where(function ($q) use ($request) {
            $q->where('card_no', 'like', "%{$request->keyword}%")
              ->orWhere('card_name', 'like', "%{$request->keyword}%");
        });
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'card_no' => 'required|string|max:50|unique:fuel_cards,card_no',
            'card_name' => 'nullable|string|max:100',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'balance' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,lost,expired',
            'issue_date' => 'nullable|date',
            'expire_date' => 'nullable|date|after:issue_date',
            'notes' => 'nullable|string|max:5000',
        ]);
        $data['balance'] = $data['balance'] ?? 0;
        $data['status'] = $data['status'] ?? 'active';
        $row = FuelCard::create($data);
        return response()->json(['code' => 0, 'message' => '油卡已添加', 'data' => $row->load('vehicle')]);
    }

    public function update(Request $request, FuelCard $card): JsonResponse
    {
        if ($request->exists('balance')) {
            return response()->json(['code' => 422, 'message' => '油卡余额只能通过充值流水维护'], 422);
        }
        $data = $request->validate([
            'card_no' => 'sometimes|string|max:50|unique:fuel_cards,card_no,' . $card->id,
            'card_name' => 'nullable|string|max:100',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'status' => 'sometimes|in:active,lost,expired',
            'issue_date' => 'nullable|date',
            'expire_date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);
        if (array_key_exists('card_no', $data)) {
            $data['card_no'] = trim($data['card_no']);
        }
        $issueDate = $data['issue_date'] ?? optional($card->issue_date)->toDateString();
        $expireDate = $data['expire_date'] ?? optional($card->expire_date)->toDateString();
        if ($issueDate && $expireDate && $expireDate <= $issueDate) {
            return response()->json(['code' => 422, 'message' => '油卡到期日期必须晚于发卡日期'], 422);
        }
        $card->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $card->load('vehicle')]);
    }

    public function destroy(FuelCard $card): JsonResponse
    {
        try {
            DB::transaction(function () use ($card) {
                $current = FuelCard::lockForUpdate()->findOrFail($card->id);
                if ($current->recharges()->exists() || (float) $current->balance > 0) {
                    throw new \DomainException('已有余额或充值流水的油卡不能删除，请改为停用');
                }
                $current->delete();
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    // ========== 充值记录 ==========

    public function recharges(Request $request): JsonResponse
    {
        $query = FuelCardRecharge::with('card');
        if ($request->filled('card_id')) $query->where('card_id', $request->card_id);
        if ($request->filled('vehicle_id')) {
            $query->whereHas('card', function ($q) use ($request) {
                $q->where('vehicle_id', $request->vehicle_id);
            });
        }
        if ($request->filled('keyword')) $query->where(function ($q) use ($request) {
            $q->where('voucher_no', 'like', "%{$request->keyword}%")
              ->orWhere('operator', 'like', "%{$request->keyword}%");
        });
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        return response()->json(['code' => 0, 'data' => $query->orderBy('recharge_date', 'desc')->orderBy('id', 'desc')->paginate($perPage)]);
    }

    public function storeRecharge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'card_id' => 'required|integer|exists:fuel_cards,id',
            'amount' => 'required|numeric|min:0.01',
            'recharge_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'operator' => 'nullable|string|max:50',
            'voucher_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:5000',
        ]);
        // 事务: 增加记录 + 同步余额
        try {
            $row = DB::transaction(function () use ($data) {
                $card = FuelCard::lockForUpdate()->findOrFail($data['card_id']);
                if ($card->status !== 'active') {
                    throw new \DomainException('非启用状态的油卡不能充值');
                }
                $r = FuelCardRecharge::create($data);
                $card->balance = round(((float) $card->balance) + (float) $data['amount'], 2);
                $card->save();
                return $r;
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['code' => 0, 'message' => '充值已记录', 'data' => $row->load('card')]);
    }

    public function destroyRecharge(FuelCardRecharge $recharge): JsonResponse
    {
        try {
            DB::transaction(function () use ($recharge) {
                $recharge = FuelCardRecharge::lockForUpdate()->findOrFail($recharge->id);
                $card = FuelCard::lockForUpdate()->findOrFail($recharge->card_id);
                if ((float) $card->balance + 0.0001 < (float) $recharge->amount) {
                    throw new \DomainException('删除该充值记录会导致油卡余额为负，已拒绝操作');
                }
                $card->balance = round(((float) $card->balance) - (float) $recharge->amount, 2);
                $card->save();
                $recharge->delete();
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 422, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    public function stats(): JsonResponse
    {
        $totalCards = FuelCard::count();
        $activeCards = FuelCard::where('status', 'active')->count();
        $boundCards = FuelCard::whereNotNull('vehicle_id')->count();
        $totalBalance = FuelCard::sum('balance');
        $monthRecharge = FuelCardRecharge::where('recharge_date', '>=', now()->subDays(30))->sum('amount');
        $monthCount = FuelCardRecharge::where('recharge_date', '>=', now()->subDays(30))->count();
        return response()->json(['code' => 0, 'data' => compact(
            'totalCards', 'activeCards', 'boundCards', 'totalBalance', 'monthRecharge', 'monthCount'
        )]);
    }
}
