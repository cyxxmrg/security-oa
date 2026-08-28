<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsQueryService;
use App\Support\AuthScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * V1.2.7 E2: 6 大报表 API
 *
 * 端点:
 *  GET /api/analytics/revenue        - 月度营收 (含同比)
 *  GET /api/analytics/sales-funnel   - 销售漏斗
 *  GET /api/analytics/project-health - 项目健康度
 *  GET /api/analytics/customer-rfm   - 客户 RFM
 *  GET /api/analytics/inventory-aging - 库存周转
 *  GET /api/analytics/finance-pnl    - 财务利润表
 *  GET /api/analytics/refresh-status - 物化视图刷新状态
 *  GET /api/analytics/export/pdf     - PDF 导出
 *
 * 权限: analytics.view (spatie permission)
 * 缓存: 5min (AnalyticsQueryService 内部)
 */
class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsQueryService $svc) {}

    /**
     * 月度营收
     */
    public function revenue(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->svc->revenue($this->scopedFilters($request, ['start', 'end', 'industry'])));
    }

    /**
     * 销售漏斗
     */
    public function salesFunnel(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->svc->salesFunnel($this->scopedFilters($request, ['weeks'])));
    }

    /**
     * 项目健康度
     */
    public function projectHealth(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->svc->projectHealth($this->scopedFilters($request, ['color', 'limit'])));
    }

    /**
     * RFM 客户价值
     */
    public function customerRfm(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->svc->customerRfm($this->scopedFilters($request, ['segment', 'limit'])));
    }

    /**
     * 库存周转
     */
    public function inventoryAging(Request $request): JsonResponse
    {
        if (!AuthScope::isUnrestricted($request->user())) {
            return response()->json(['code' => 403, 'message' => '库存报表暂不支持当前数据范围'], 403);
        }
        return $this->respond(fn () => $this->svc->inventoryAging($this->scopedFilters($request, ['status'])));
    }

    /**
     * 月度利润表
     */
    public function financePnl(Request $request): JsonResponse
    {
        if (!AuthScope::isUnrestricted($request->user())) {
            return response()->json(['code' => 403, 'message' => '财务利润表仅限全局授权账号'], 403);
        }
        return $this->respond(fn () => $this->svc->financePnl($this->scopedFilters($request, ['start', 'end'])));
    }

    /**
     * 物化视图刷新状态 (前端 dashboard 显示 "数据更新于 02:30")
     * V1.2.9p E2 fix: 容错 - 视图不存在 refreshed_at 列时,
     *   fallback 到 Cache('analytics:refresh_at') 记录的时间戳
     *   (由 RefreshAnalyticsViews command 每次刷新后写入)
     */
    public function refreshStatus(): JsonResponse
    {
        $views = [
            'mv_revenue_monthly' => '月度营收',
            'mv_sales_funnel'    => '销售漏斗',
            'mv_project_health'  => '项目健康度',
            'mv_customer_rfm'    => '客户 RFM',
            'mv_inventory_aging' => '库存周转',
            'mv_finance_pnl'     => '财务利润表',
        ];
        $fallback = \Illuminate\Support\Facades\Cache::get('analytics:refresh_at');
        $status = [];
        foreach ($views as $view => $label) {
            try {
                $cnt = \DB::selectOne("SELECT COUNT(*) AS cnt FROM {$view}")->cnt ?? 0;
                $hasCol = \DB::selectOne("
                    SELECT EXISTS (
                        SELECT 1 FROM information_schema.columns
                        WHERE table_name = ? AND column_name = 'refreshed_at'
                    ) AS hit
                ", [$view])->hit;
                $last = $hasCol
                    ? (\DB::selectOne("SELECT MAX(refreshed_at) AS last FROM {$view}")->last ?? $fallback)
                    : $fallback;
                $status[] = [
                    'view'         => $view,
                    'label'        => $label,
                    'rows'         => (int)$cnt,
                    'refreshed_at' => $last,
                ];
            } catch (\Throwable $e) {
                $status[] = [
                    'view'         => $view,
                    'label'        => $label,
                    'rows'         => 0,
                    'refreshed_at' => $fallback,
                    'error'        => $e->getMessage(),
                ];
            }
        }
        return response()->json(['code' => 0, 'data' => $status]);
    }

    /**
     * 统一响应封装 (错误兜底)
     */
    private function respond(\Closure $cb): JsonResponse
    {
        try {
            $data = $cb();
            return response()->json(['code' => 0, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('Analytics API failed', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'code'    => 500,
                'message' => '报表查询失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function scopedFilters(Request $request, array $keys): array
    {
        $filters = $request->only($keys);
        $user = $request->user();
        if (AuthScope::isUnrestricted($user)) {
            return $filters;
        }

        $filters['_scope_department_id'] = $user?->department_id;
        $filters['_scope_sales_ids'] = $user?->department_id
            ? \App\Models\User::where('department_id', $user->department_id)->pluck('id')->all()
            : [$user?->id];
        $filters['_scope_manager_ids'] = $filters['_scope_sales_ids'];
        return $filters;
    }
}
