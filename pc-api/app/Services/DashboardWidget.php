<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * V0.5.7 块5 — Dashboard 多维度统计
 *
 * 4 widget:
 *   - methodDistribution   维修方式饼图数据 (按 count 排序)
 *   - cycleTimePercentile 维修周期 P50/P90 (按月)
 *   - faultTypeTop        返修原因 Top 5 (按 30d)
 *   - technicianRanking   工程师效率 Top 5 (按完成工单数 + 平均周期)
 */
class DashboardWidget
{
    /**
     * 维修方式饼图数据
     * 返回: { free_warranty: 5, free_contract: 3, paid_repair: 12, ... }
     * 只算已关闭的返修单
     */
    public function methodDistribution(int $days = 90): array
    {
        $rows = DB::select("
            SELECT
                COALESCE(ro.method_type, 'unspecified') AS method_type,
                COUNT(*) AS cnt
            FROM repair_orders ro
            WHERE ro.status IN ('completed','closed','shipped_back','repaired')
              AND ro.received_at >= (CURRENT_DATE - (:days || ' days')::interval)
            GROUP BY method_type
            ORDER BY cnt DESC
        ", ['days' => $days]);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->method_type] = (int) $r->cnt;
        }
        return $out;
    }

    /**
     * 维修周期 P50/P90 (天)
     * 统计已关闭返修单: (updated_at - received_at) 的 P50/P90
     * 返回: { p50: 3.2, p90: 8.5, sample_count: 30, max_days: 15 }
     */
    public function cycleTimePercentile(int $days = 90): array
    {
        // 用 PG 的 percentile_cont (注意: percentile 内部不能 EXTRACT, 在外层算)
        $row = DB::selectOne("
            WITH durations AS (
                SELECT
                    EXTRACT(EPOCH FROM (updated_at - received_at))::numeric(10,2) AS seconds
                FROM repair_orders
                WHERE status IN ('completed','closed','shipped_back')
                  AND received_at IS NOT NULL
                  AND updated_at IS NOT NULL
                  AND updated_at > received_at
                  AND received_at >= (CURRENT_DATE - (:days || ' days')::interval)
            )
            SELECT
                count(*) AS cnt,
                COALESCE(percentile_cont(0.5) WITHIN GROUP (ORDER BY seconds), 0)::numeric(10,2) AS p50_sec,
                COALESCE(percentile_cont(0.9) WITHIN GROUP (ORDER BY seconds), 0)::numeric(10,2) AS p90_sec,
                COALESCE(MAX(seconds), 0)::numeric(10,2) AS max_sec
            FROM durations
        ", ['days' => $days]);

        $cnt = (int) $row->cnt;
        return [
            'sample_count' => $cnt,
            'p50_days'     => round((float) $row->p50_sec / 86400, 2),
            'p90_days'     => round((float) $row->p90_sec / 86400, 2),
            'max_days'     => round((float) $row->max_sec / 86400, 2),
            'available'    => $cnt > 0,
        ];
    }

    /**
     * 返修原因 Top 5 (近 30 天)
     * 返回: [{ fault_type: 'power', count: 8, label: '电源故障' }, ...]
     */
    public function faultTypeTop(int $days = 30, int $limit = 5): array
    {
        // 用 system_dicts.label 翻译 (V0.5.7 块B)
        $rows = DB::select("
            SELECT
                ro.fault_type,
                COUNT(*) AS cnt
            FROM repair_orders ro
            WHERE ro.fault_type IS NOT NULL
              AND ro.fault_type <> ''
              AND ro.received_at >= (CURRENT_DATE - (:days || ' days')::interval)
            GROUP BY ro.fault_type
            ORDER BY cnt DESC
            LIMIT :limit
        ", ['days' => $days, 'limit' => $limit]);

        if (empty($rows)) return [];

        // 尝试从字典拿 label
        $codes = array_map(fn($r) => $r->fault_type, $rows);
        $dictRows = DB::table('system_dicts')
            ->where('kind', 'fault_type')
            ->whereIn('code', $codes)
            ->pluck('label', 'code');

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'code'       => $r->fault_type,
                'label'      => $dictRows[$r->fault_type] ?? $r->fault_type,
                'count'      => (int) $r->cnt,
                'percentage' => 0, // 后续算
            ];
        }
        $total = array_sum(array_column($out, 'count'));
        foreach ($out as &$row) {
            $row['percentage'] = $total > 0 ? round($row['count'] / $total * 100, 1) : 0;
        }
        return $out;
    }

    /**
     * 工程师效率 Top 5
     * 指标: 完成工单数 (主) + 平均完成周期 (天) (次)
     * 返回: [{ engineer_id, name, completed_count, avg_days, total_revenue }, ...]
     */
    public function technicianRanking(int $days = 30, int $limit = 5): array
    {
        // repair_orders.received_by 关联 users
        $rows = DB::select("
            SELECT
                ro.received_by AS user_id,
                COALESCE(u.name, '未分配') AS name,
                COUNT(*) AS completed_count,
                COALESCE(AVG(EXTRACT(EPOCH FROM (ro.updated_at - ro.received_at)) / 86400)::numeric(10,2), 0) AS avg_days,
                COALESCE(SUM(ro.total_cost), 0)::numeric(14,2) AS total_revenue
            FROM repair_orders ro
            LEFT JOIN users u ON u.id = ro.received_by
            WHERE ro.received_by IS NOT NULL
              AND ro.status IN ('completed','closed','shipped_back')
              AND ro.received_at IS NOT NULL
              AND ro.updated_at > ro.received_at
              AND ro.received_at >= (CURRENT_DATE - (:days || ' days')::interval)
            GROUP BY ro.received_by, u.name
            ORDER BY completed_count DESC, avg_days ASC
            LIMIT :limit
        ", ['days' => $days, 'limit' => $limit]);

        return array_map(fn($r) => [
            'user_id'         => (int) $r->user_id,
            'name'            => $r->name,
            'completed_count' => (int) $r->completed_count,
            'avg_days'        => (float) $r->avg_days,
            'total_revenue'   => (float) $r->total_revenue,
        ], $rows);
    }
}
