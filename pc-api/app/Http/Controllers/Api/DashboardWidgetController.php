<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * V0.5.7 块5 — Dashboard 多维度 widget
 *
 * 5 端点:
 *   GET /api/dashboard/widget/method-distribution  维修方式饼图
 *   GET /api/dashboard/widget/cycle-percentile     P50/P90 周期
 *   GET /api/dashboard/widget/fault-top            返修原因 Top 5
 *   GET /api/dashboard/widget/technician-rank      工程师效率 Top 5
 *   GET /api/dashboard/widget/all                  一次拿全部 (V0.6.3 加 120s Redis 缓存)
 */
class DashboardWidgetController extends Controller
{
    public function __construct(private DashboardWidget $widget) {}

    /** GET /api/dashboard/widget/method-distribution?days=90 */
    public function methodDistribution(Request $request): JsonResponse
    {
        $days = $this->parseDays($request);
        $data = Cache::remember("dashboard:widget:method:{$days}", 300, fn () => $this->widget->methodDistribution($days));
        return response()->json(['code' => 0, 'data' => $data]);
    }

    /** GET /api/dashboard/widget/cycle-percentile?days=90 */
    public function cyclePercentile(Request $request): JsonResponse
    {
        $days = $this->parseDays($request);
        $data = Cache::remember("dashboard:widget:cycle:{$days}", 300, fn () => $this->widget->cycleTimePercentile($days));
        return response()->json(['code' => 0, 'data' => $data]);
    }

    /** GET /api/dashboard/widget/fault-top?days=30&limit=5 */
    public function faultTop(Request $request): JsonResponse
    {
        $days = $this->parseDays($request, 30);
        $limit = max(1, min(20, (int) $request->query('limit', 5)));
        $data = Cache::remember("dashboard:widget:fault:{$days}:{$limit}", 300, fn () => $this->widget->faultTypeTop($days, $limit));
        return response()->json(['code' => 0, 'data' => $data]);
    }

    /** GET /api/dashboard/widget/technician-rank?days=30&limit=5 */
    public function technicianRank(Request $request): JsonResponse
    {
        $days = $this->parseDays($request, 30);
        $limit = max(1, min(20, (int) $request->query('limit', 5)));
        $data = Cache::remember("dashboard:widget:tech:{$days}:{$limit}", 300, fn () => $this->widget->technicianRanking($days, $limit));
        return response()->json(['code' => 0, 'data' => $data]);
    }

    /** GET /api/dashboard/widget/all — 一次拿全部 (减少前端请求数) */
    public function all(Request $request): JsonResponse
    {
        $days = $this->parseDays($request);
        // V0.6.3 性能优化: 整个 widget/all 加 120s Redis 缓存 (dashboard 每刷新都调)
        $cacheKey = "dashboard:widget:all:{$days}";
        $data = Cache::remember($cacheKey, 120, function () use ($days) {
            return [
                'method_distribution'   => $this->widget->methodDistribution($days),
                'cycle_percentile'      => $this->widget->cycleTimePercentile($days),
                'fault_top'             => $this->widget->faultTypeTop(30, 5),
                'technician_ranking'    => $this->widget->technicianRanking(30, 5),
                'updated_at'            => now()->toIso8601String(),
            ];
        });
        return response()->json(['code' => 0, 'data' => $data]);
    }

    private function parseDays(Request $request, int $default = 90): int
    {
        return max(1, min(365, (int) $request->query('days', $default)));
    }
}
