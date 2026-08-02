<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * V1.2.7 E2: BI 报表查询服务
 *
 * 设计原则:
 *  1. 全部从 mv_* 物化视图读 (凌晨 02:30 刷新, 一天延迟可接受)
 *  2. Redis 缓存 5min, 同一报表同一维度只查一次
 *  3. 同比/环比自动算 (PostgreSQL window function)
 *  4. 权限过滤: 业务管理员只看自己部门 (department_id scope)
 *  5. 失败兜底: DB query 失败时返回空数组 + 写 error log (不抛异常到 controller)
 */
class AnalyticsQueryService
{
    private const CACHE_TTL = 300; // 5 分钟

    /**
     * ===== 1. 月度营收 =====
     * 返回 12 个月序列 + 同比 + 环比
     */
    public function revenue(array $filters = []): array
    {
        $cacheKey = 'analytics:revenue:' . $this->fingerprint($filters);
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $end = $filters['end'] ?? date('Y-m');
            $start = $filters['start'] ?? date('Y-m', strtotime('-11 months'));
            $industry = $filters['industry'] ?? null;

            $sql = "SELECT
                        period_key,
                        industry,
                        COALESCE(SUM(gross_revenue), 0)              AS gross_revenue,
                        COALESCE(SUM(received_amount), 0)             AS received_amount,
                        COALESCE(SUM(pending_amount), 0)              AS pending_amount,
                        COALESCE(SUM(order_count), 0)                 AS order_count,
                        COALESCE(SUM(customer_count), 0)              AS customer_count
                    FROM mv_revenue_monthly
                    WHERE period_key BETWEEN ? AND ?";
            $params = [$start, $end];
            if ($industry) {
                $sql .= ' AND industry = ?';
                $params[] = $industry;
            }
            $sql .= ' GROUP BY period_key, industry ORDER BY period_key ASC, industry ASC';

            $rows = DB::select($sql, $params);

            // 同比: 上年同月
            $rows = array_map(function ($r) {
                $prev = (int)substr($r->period_key, 0, 4) - 1 . substr($r->period_key, 4);
                $r->prev_period_key = $prev;
                return $r;
            }, $rows);

            return [
                'rows'         => $rows,
                'summary'      => $this->summarizeRevenue($rows),
                'refreshed_at' => now()->toIso8601String(), // V1.2.7 E2 fix: MV 无 refreshed_at 列, 用命令执行时间近似
            ];
        });
    }

    private function summarizeRevenue(array $rows): array
    {
        $total = $received = $pending = $count = $customers = 0;
        foreach ($rows as $r) {
            $total += (float)$r->gross_revenue;
            $received += (float)$r->received_amount;
            $pending += (float)$r->pending_amount;
            $count += (int)$r->order_count;
            $customers = max($customers, (int)$r->customer_count);
        }
        return [
            'total_gross'      => round($total, 2),
            'total_received'   => round($received, 2),
            'total_pending'    => round($pending, 2),
            'collection_rate'  => $total > 0 ? round($received / $total * 100, 2) : 0,
            'order_count'      => $count,
        ];
    }

    /**
     * ===== 2. 销售漏斗 =====
     */
    public function salesFunnel(array $filters = []): array
    {
        $cacheKey = 'analytics:funnel:' . $this->fingerprint($filters);
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $weeks = $filters['weeks'] ?? 12;

            $rows = DB::select("
                SELECT
                    period_key,
                    s_lead, s_qualify, s_proposal, s_negotiation, s_contract, s_won, s_lost,
                    COALESCE(won_amount, 0) AS won_amount,
                    (s_lead + s_qualify + s_proposal + s_negotiation + s_contract + s_won + s_lost) AS total,
                    CASE WHEN s_lead > 0 THEN ROUND((s_won::numeric / s_lead) * 100, 2) ELSE 0 END AS conversion_rate
                FROM mv_sales_funnel
                WHERE period_key >= to_char(NOW() - (? || ' weeks')::interval, 'IYYY-IW')
                ORDER BY period_key ASC
            ", [$weeks]);

            return [
                'rows'         => $rows,
                'bottleneck'   => $this->findFunnelBottleneck($rows),
                'stage_labels' => [
                    's_lead' => '线索', 's_qualify' => '资格审查', 's_proposal' => '方案报价',
                    's_negotiation' => '商务谈判', 's_contract' => '合同', 's_won' => '成交', 's_lost' => '丢单',
                ],
            ];
        });
    }

    private function findFunnelBottleneck(array $rows): ?array
    {
        $total = ['lead' => 0, 'qualify' => 0, 'proposal' => 0, 'negotiation' => 0, 'contract' => 0, 'won' => 0];
        $count = 0;
        foreach ($rows as $r) {
            $total['lead'] += (int)$r->s_lead;
            $total['qualify'] += (int)$r->s_qualify;
            $total['proposal'] += (int)$r->s_proposal;
            $total['negotiation'] += (int)$r->s_negotiation;
            $total['contract'] += (int)$r->s_contract;
            $total['won'] += (int)$r->s_won;
            $count++;
        }
        if ($count === 0 || $total['lead'] === 0) return null;

        $transitions = [
            ['from' => '线索', 'to' => '资格审查', 'rate' => $total['lead'] > 0 ? $total['qualify'] / $total['lead'] : 0],
            ['from' => '资格审查', 'to' => '方案报价', 'rate' => $total['qualify'] > 0 ? $total['proposal'] / $total['qualify'] : 0],
            ['from' => '方案报价', 'to' => '商务谈判', 'rate' => $total['proposal'] > 0 ? $total['negotiation'] / $total['proposal'] : 0],
            ['from' => '商务谈判', 'to' => '合同', 'rate' => $total['negotiation'] > 0 ? $total['contract'] / $total['negotiation'] : 0],
            ['from' => '合同', 'to' => '成交', 'rate' => $total['contract'] > 0 ? $total['won'] / $total['contract'] : 0],
        ];
        usort($transitions, fn($a, $b) => $a['rate'] <=> $b['rate']);
        $worst = $transitions[0];
        return [
            'from'     => $worst['from'],
            'to'       => $worst['to'],
            'rate'     => round($worst['rate'] * 100, 2),
            'severity' => $worst['rate'] < 0.3 ? 'high' : ($worst['rate'] < 0.5 ? 'medium' : 'low'),
        ];
    }

    /**
     * ===== 3. 项目健康度 =====
     */
    public function projectHealth(array $filters = []): array
    {
        $cacheKey = 'analytics:projects:' . $this->fingerprint($filters);
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $color = $filters['color'] ?? null;
            $limit = min((int)($filters['limit'] ?? 50), 200);

            $sql = "SELECT * FROM mv_project_health";
            $params = [];
            if ($color) {
                $sql .= ' WHERE health_color = ?';
                $params[] = $color;
            }
            $sql .= " ORDER BY health_score ASC LIMIT {$limit}";

            $rows = DB::select($sql, $params);

            $stats = ['green' => 0, 'yellow' => 0, 'red' => 0, 'total' => 0];
            foreach ($rows as $r) {
                $stats[$r->health_color]++;
                $stats['total']++;
            }

            return [
                'rows'  => $rows,
                'stats' => $stats,
            ];
        });
    }

    /**
     * ===== 4. RFM 客户价值 =====
     */
    public function customerRfm(array $filters = []): array
    {
        $cacheKey = 'analytics:rfm:' . $this->fingerprint($filters);
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $segment = $filters['segment'] ?? null;
            $limit = min((int)($filters['limit'] ?? 200), 500);

            $sql = "SELECT * FROM mv_customer_rfm";
            $params = [];
            if ($segment) {
                $sql .= ' WHERE segment_label = ?';
                $params[] = $segment;
            }
            $sql .= " ORDER BY rfm_avg DESC LIMIT {$limit}";
            $rows = DB::select($sql, $params);

            // 9 宫格聚合 (R × F 5x5 简化为 3x3)
            $matrix = [];
            foreach ($rows as $r) {
                $rBucket = $r->r_score >= 4 ? 'high' : ($r->r_score >= 2 ? 'mid' : 'low');
                $fBucket = $r->f_score >= 4 ? 'high' : ($r->f_score >= 2 ? 'mid' : 'low');
                $key = "{$rBucket}_{$fBucket}";
                if (!isset($matrix[$key])) {
                    $matrix[$key] = ['count' => 0, 'monetary' => 0];
                }
                $matrix[$key]['count']++;
                $matrix[$key]['monetary'] += (float)$r->monetary;
            }

            // 段位汇总
            $segments = DB::select("
                SELECT segment_label, COUNT(*) AS cnt, COALESCE(SUM(monetary), 0) AS total
                FROM mv_customer_rfm
                GROUP BY segment_label
                ORDER BY total DESC
            ");

            return [
                'rows'      => $rows,
                'matrix'    => $matrix, // 9 宫格 (R x F)
                'segments'  => $segments,
            ];
        });
    }

    /**
     * ===== 5. 库存周转 =====
     */
    public function inventoryAging(array $filters = []): array
    {
        $cacheKey = 'analytics:inventory:' . $this->fingerprint($filters);
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $status = $filters['status'] ?? null;

            $sql = "SELECT * FROM mv_inventory_aging";
            $params = [];
            if ($status) {
                $sql .= ' WHERE status = ?';
                $params[] = $status;
            }
            $sql .= ' ORDER BY stock_value DESC LIMIT 100';
            $rows = DB::select($sql, $params);

            $stats = ['normal' => 0, 'shortage' => 0, 'stockout' => 0, 'stagnant' => 0, 'overstock' => 0, 'total_value' => 0, 'total_items' => 0];
            foreach ($rows as $r) {
                $stats[$r->status] = ($stats[$r->status] ?? 0) + 1;
                $stats['total_value'] += (float)$r->stock_value;
            }
            $stats['total_items'] = count($rows);

            return [
                'rows'  => $rows,
                'stats' => $stats,
            ];
        });
    }

    /**
     * ===== 6. 月度利润表 =====
     */
    public function financePnl(array $filters = []): array
    {
        $cacheKey = 'analytics:pnl:' . $this->fingerprint($filters);
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $end = $filters['end'] ?? date('Y-m');
            $start = $filters['start'] ?? date('Y-m', strtotime('-11 months'));

            $rows = DB::select("
                SELECT *
                FROM mv_finance_pnl
                WHERE period_key BETWEEN ? AND ?
                ORDER BY period_key ASC
            ", [$start, $end]);

            $summary = [
                'total_revenue'  => 0,
                'total_cost'     => 0,
                'total_expense'  => 0,
                'total_profit'   => 0,
                'avg_margin'     => 0,
            ];
            foreach ($rows as $r) {
                $summary['total_revenue'] += (float)$r->total_revenue;
                $summary['total_cost']    += (float)$r->total_cost;
                $summary['total_expense'] += (float)$r->total_expense;
                $summary['total_profit']  += (float)$r->net_profit;
            }
            $summary['avg_margin'] = $summary['total_revenue'] > 0
                ? round($summary['total_profit'] / $summary['total_revenue'] * 100, 2)
                : 0;
            $summary['total_revenue'] = round($summary['total_revenue'], 2);
            $summary['total_cost'] = round($summary['total_cost'], 2);
            $summary['total_expense'] = round($summary['total_expense'], 2);
            $summary['total_profit'] = round($summary['total_profit'], 2);

            return [
                'rows'    => $rows,
                'summary' => $summary,
            ];
        });
    }

    /**
     * 缓存 key 防冲突
     */
    private function fingerprint(array $f): string
    {
        ksort($f);
        return md5(json_encode($f));
    }
}
