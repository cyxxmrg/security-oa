<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RepairCostStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V0.5.7 块4 — 维修成本归集
 *
 * 提供 4 维度汇总 + 概览 KPI
 *   GET /api/repair-cost/overview    概览
 *   GET /api/repair-cost/by-month    月度
 *   GET /api/repair-cost/by-project  项目
 *   GET /api/repair-cost/by-customer 客户
 *   GET /api/repair-cost/by-method   维修方式
 *
 * V1.2.9 新增 scope 查询参数:
 *   scope=repair (默认, 仅维修)
 *   scope=project (全部项目: 维修+实际成本+物料+结算)
 *   scope=all (同上, project 的别名)
 */
class RepairCostSummaryController extends Controller
{
    public function __construct(private RepairCostStat $stat) {}

    /**
     * 概览 KPI
     * query: from, to, customer_id, project_id, method_type, scope
     */
    public function overview(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $scope = $request->query('scope', 'repair');
        $method = in_array($scope, ['project', 'all']) ? 'overviewAll' : 'overview';

        return response()->json([
            'code' => 0,
            'data' => $this->stat->$method($filters),
        ]);
    }

    /**
     * 月度 (近 N 月, 默认 12)
     */
    public function byMonth(Request $request): JsonResponse
    {
        $months = (int) $request->query('months', 12);
        $months = max(1, min($months, 36)); // 1-36 限制

        $filters = $this->extractFilters($request);
        $scope = $request->query('scope', 'repair');
        $method = in_array($scope, ['project', 'all']) ? 'byMonthAll' : 'byMonth';

        return response()->json([
            'code' => 0,
            'data' => $this->stat->$method($months, $filters),
        ]);
    }

    public function byProject(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $scope = $request->query('scope', 'repair');
        $method = in_array($scope, ['project', 'all']) ? 'byProjectAll' : 'byProject';

        return response()->json([
            'code' => 0,
            'data' => $this->stat->$method($filters),
        ]);
    }

    public function byCustomer(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $scope = $request->query('scope', 'repair');
        $method = in_array($scope, ['project', 'all']) ? 'byCustomerAll' : 'byCustomer';

        return response()->json([
            'code' => 0,
            'data' => $this->stat->$method($filters),
        ]);
    }

    public function byMethod(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        return response()->json([
            'code' => 0,
            'data' => $this->stat->byMethod($filters),
        ]);
    }

    private function extractFilters(Request $request): array
    {
        return array_filter([
            'from'         => $request->query('from'),
            'to'           => $request->query('to'),
            'customer_id'  => $request->query('customer_id'),
            'project_id'   => $request->query('project_id'),
            'method_type'  => $request->query('method_type'),
            'is_warranty'  => $request->query('is_warranty'),
        ], fn($v) => $v !== null && $v !== '');
    }
}
