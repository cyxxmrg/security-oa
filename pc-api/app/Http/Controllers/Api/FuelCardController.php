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
        $perPage = $request->per_page ?? 15;
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'card_no' => 'required|string|max:50|unique:fuel_cards,card_no',
            'card_name' => 'nullable|string|max:100',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'balance' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,lost,expired',
            'issue_date' => 'nullable|date',
            'expire_date' => 'nullable|date|after:issue_date',
            'notes' => 'nullable|string',
        ]);
        $data['balance'] = $data['balance'] ?? 0;
        $data['status'] = $data['status'] ?? 'active';
        $row = FuelCard::create($data);
        return response()->json(['code' => 0, 'message' => '油卡已添加', 'data' => $row->load('vehicle')]);
    }

    public function update(Request $request, FuelCard $card): JsonResponse
    {
        $data = $request->validate([
            'card_no' => 'sometimes|string|max:50|unique:fuel_cards,card_no,' . $card->id,
            'card_name' => 'nullable|string|max:100',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'balance' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,lost,expired',
            'issue_date' => 'nullable|date',
            'expire_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        $card->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $card->load('vehicle')]);
    }

    public function destroy(FuelCard $card): JsonResponse
    {
        $card->delete();
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
        $perPage = $request->per_page ?? 15;
        return response()->json(['code' => 0, 'data' => $query->orderBy('recharge_date', 'desc')->orderBy('id', 'desc')->paginate($perPage)]);
    }

    public function storeRecharge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'card_id' => 'required|exists:fuel_cards,id',
            'amount' => 'required|numeric|min:0.01',
            'recharge_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'operator' => 'nullable|string|max:50',
            'voucher_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);
        // 事务: 增加记录 + 同步余额
        $row = DB::transaction(function () use ($data) {
            $r = FuelCardRecharge::create($data);
            $card = FuelCard::find($data['card_id']);
            $card->balance = round(((float) $card->balance) + (float) $data['amount'], 2);
            $card->save();
            return $r;
        });
        return response()->json(['code' => 0, 'message' => '充值已记录', 'data' => $row->load('card')]);
    }

    public function destroyRecharge(FuelCardRecharge $recharge): JsonResponse
    {
        DB::transaction(function () use ($recharge) {
            $card = $recharge->card;
            $card->balance = max(0, round(((float) $card->balance) - (float) $recharge->amount, 2));
            $card->save();
            $recharge->delete();
        });
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
