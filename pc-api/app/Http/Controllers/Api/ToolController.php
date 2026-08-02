<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 工具使用单 — V1.3.4 简化版
 *
 * 列表直接平铺领用/归还明细; 上方三个动作: 新增工具领用单 / 新增工具归还单 / 库存转工具。
 * 全部委托 InventoryService (toolConvert / toolMovement / paginateToolRecords / listTools)
 */
class ToolController extends Controller
{
    public function __construct(private InventoryService $svc) {}

    /** 工具使用明细列表 (领用/归还流水直显) */
    public function records(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->paginateToolRecords($request)]);
    }

    /** 工具台账列表 (领用/归还选择器) */
    public function tools(Request $request): JsonResponse
    {
        return response()->json(['code' => 0, 'data' => $this->svc->listTools($request)]);
    }

    /** 库存转工具: 库存商品 → 工具, 自动生成固定资产编号 */
    public function convert(Request $request): JsonResponse
    {
        $result = $this->svc->toolConvert($request);
        return response()->json(['code' => 0, 'data' => $result]);
    }

    /** 新增工具领用单 */
    public function checkout(Request $request): JsonResponse
    {
        try {
            $result = $this->svc->toolMovement($request, 'checkout');
            return response()->json(['code' => 0, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }

    /** 新增工具归还单 */
    public function returnItem(Request $request): JsonResponse
    {
        try {
            $result = $this->svc->toolMovement($request, 'return');
            return response()->json(['code' => 0, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 409);
        }
    }
}
