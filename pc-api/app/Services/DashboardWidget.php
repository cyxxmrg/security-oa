<?php

namespace App\Services;

use App\Models\RepairOrder;
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
        $rows = RepairOrder::query()
            ->whereIn('status', ['completed', 'closed', 'shipped_back', 'repaired'])
            ->whereRaw("received_at >= (CURRENT_DATE - (? || ' days')::interval)", [$days])
            ->selectRaw("COALESCE(method_type, 'unspecified') AS method_type, COUNT(*) AS cnt")
            ->groupBy('method_type')
            ->orderByDesc('cnt')
            ->get();

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
        $durations = RepairOrder::query()
            ->whereIn('status', ['completed', 'closed', 'shipped_back'])
            ->whereNotNull('received_at')
            ->whereNotNull('updated_at')
            ->whereColumn('updated_at', '>', 'received_at')
            ->whereRaw("received_at >= (CURRENT_DATE - (? || ' days')::interval)", [$days])
            ->selectRaw('EXTRACT(EPOCH FROM (updated_at - received_at))::numeric(10,2) AS seconds');

        $row = DB::query()
            ->fromSub($durations, 'durations')
            ->selectRaw('count(*) AS cnt')
            ->selectRaw("COALESCE(percentile_cont(0.5) WITHIN GROUP (ORDER BY seconds), 0)::numeric(10,2) AS p50_sec")
            ->selectRaw("COALESCE(percentile_cont(0.9) WITHIN GROUP (ORDER BY seconds), 0)::numeric(10,2) AS p90_sec")
            ->selectRaw('COALESCE(MAX(seconds), 0)::numeric(10,2) AS max_sec')
            ->first();

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
        $rows = RepairOrder::query()
            ->whereNotNull('fault_type')
            ->where('fault_type', '<>', '')
            ->whereRaw("received_at >= (CURRENT_DATE - (? || ' days')::interval)", [$days])
            ->selectRaw('fault_type, COUNT(*) AS cnt')
            ->groupBy('fault_type')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get();

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
        $rows = RepairOrder::query()
            ->leftJoin('users as u', 'u.id', '=', 'repair_orders.received_by')
            ->whereNotNull('repair_orders.received_by')
            ->whereIn('repair_orders.status', ['completed', 'closed', 'shipped_back'])
            ->whereNotNull('repair_orders.received_at')
            ->whereColumn('repair_orders.updated_at', '>', 'repair_orders.received_at')
            ->whereRaw("repair_orders.received_at >= (CURRENT_DATE - (? || ' days')::interval)", [$days])
            ->selectRaw("repair_orders.received_by AS user_id,
                COALESCE(u.name, '未分配') AS name,
                COUNT(*) AS completed_count,
                COALESCE(AVG(EXTRACT(EPOCH FROM (repair_orders.updated_at - repair_orders.received_at)) / 86400)::numeric(10,2), 0) AS avg_days,
                COALESCE(SUM(repair_orders.total_cost), 0)::numeric(14,2) AS total_revenue")
            ->groupBy('repair_orders.received_by', 'u.name')
            ->orderByDesc('completed_count')
            ->orderBy('avg_days')
            ->limit($limit)
            ->get();

        return array_map(fn($r) => [
            'user_id'         => (int) $r->user_id,
            'name'            => $r->name,
            'completed_count' => (int) $r->completed_count,
            'avg_days'        => (float) $r->avg_days,
            'total_revenue'   => (float) $r->total_revenue,
        ], $rows);
    }
}
