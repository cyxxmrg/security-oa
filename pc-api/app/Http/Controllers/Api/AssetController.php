<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetInventory;
use App\Models\FixedAsset;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 固定资产管理 — V1.4.0（全生命周期）
 *
 * 台账 / 分类树 / 折旧 / 维修 / 盘点 / 报废 / 调拨
 */
class AssetController extends Controller
{
    public function __construct(private AssetService $svc) {}

    // ===== 分类 =====
    public function categoryTree(): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->categoryTree()]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->storeCategory($request)]);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['code' => 0, 'data' => $this->svc->updateCategory($request, $id)]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    public function destroyCategory(int $id): JsonResponse
    {
        try {
            $this->svc->destroyCategory($id);
            return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    // ===== 台账 =====
    public function index(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->index($request)]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->store($request)]);
    }

    public function show(FixedAsset $asset): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->show($asset)]);
    }

    public function update(Request $request, FixedAsset $asset): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->update($request, $asset)]);
    }

    public function destroy(FixedAsset $asset): JsonResponse
    {
        try {
            $this->svc->destroy($asset);
            return response()->json(['code' => 0, 'data' => ['deleted' => true]]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    // ===== 折旧 =====
    public function depreciate(Request $request): JsonResponse
    {
        try {
            return response()->json(['code' => 0, 'data' => $this->svc->depreciate($request)]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }
    }

    public function depreciations(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->depreciations($request)]);
    }

    // ===== 维修保养 =====
    public function maintenances(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->maintenances($request)]);
    }

    public function storeMaintenance(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->storeMaintenance($request)]);
    }

    // ===== 盘点 =====
    public function inventories(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->inventories($request)]);
    }

    public function storeInventory(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->storeInventory($request)]);
    }

    public function completeInventory(AssetInventory $inventory): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->completeInventory($inventory)]);
    }

    // ===== 报废处置 =====
    public function disposals(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->disposals($request)]);
    }

    public function storeDisposal(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->storeDisposal($request)]);
    }

    // ===== 调拨 =====
    public function transfers(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->transfers($request)]);
    }

    public function storeTransfer(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->storeTransfer($request)]);
    }
}
