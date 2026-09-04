<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @deprecated 采购审批已统一迁移到 approval_records_v2 审批中心。
 *
 * 保留控制器仅用于兼容旧客户端，禁止继续创建或处理旧版审批单。
 */
class PurchaseApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->deprecatedResponse();
    }

    public function store(Request $request): JsonResponse
    {
        return $this->deprecatedResponse();
    }

    public function decide(Request $request, PurchaseApproval $approval): JsonResponse
    {
        return $this->deprecatedResponse();
    }

    private function deprecatedResponse(): JsonResponse
    {
        return response()->json([
            'code' => 410,
            'message' => '旧版采购审批接口已停用，请使用审批中心接口',
        ], 410);
    }
}
