<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * V0.5.7 维修成本归集服务
 *
 * 4 个维度汇总:
 *   - byMonth     月度成本 (按 created_at 月份)
 *   - byProject   按项目
 *   - byCustomer  按客户
 *   - byMethod    按维修方式 (4 选 1)
 *
 * 全部从 repair_orders 主表聚合 (parts/labor/shipping/total_cost)
 * 状态过滤: 只算已完成 (completed/closed), 排除 received/returned/cancelled
 *
 * V1.2.9 新增: 4 个 All 维度方法 (overviewAll/byMonthAll/byProjectAll/byCustomerAll)
 *   数据源: repair_orders (维修) + project_actual_costs (项目实际成本) +
 *           project_materials (项目物料) + project_settlements (项目结算)
 */
class RepairCostStat
{
    /** 计入成本的完成状态 */
    public const COMPLETED_STATUSES = ['completed', 'closed', 'shipped_back'];

    /**
     * KPI 概览: 总成本/工时/件数/已完成数
     */
    public function overview(array $filters = []): array
    {
        [$where, $bindings] = $this->buildFilters($filters);

        $row = DB::selectOne("
            SELECT
                COUNT(*)                                            AS completed_orders,
                COALESCE(SUM(parts_cost),    0)::numeric(14,2)      AS total_parts_cost,
                COALESCE(SUM(labor_cost),    0)::numeric(14,2)      AS total_labor_cost,
                COALESCE(SUM(shipping_cost), 0)::numeric(14,2)      AS total_shipping_cost,
                COALESCE(SUM(total_cost),    0)::numeric(14,2)      AS total_cost,
                COALESCE(SUM(CASE WHEN is_warranty THEN total_cost ELSE 0 END), 0)::numeric(14,2) AS warranty_cost,
                COALESCE(SUM(CASE WHEN NOT is_warranty THEN total_cost ELSE 0 END), 0)::numeric(14,2) AS paid_cost
            FROM repair_orders
            WHERE status IN ('completed','closed','shipped_back')
              {$where}
        ", $bindings);

        // 工时从 repair_methods 聚合
        $hours = DB::selectOne("
            SELECT COALESCE(SUM(rm.hours_spent), 0)::numeric(10,2) AS total_hours
            FROM repair_methods rm
            JOIN repair_orders ro ON ro.id = rm.repair_order_id
            WHERE ro.status IN ('completed','closed','shipped_back')
              {$where}
        ", $bindings);

        return [
            'completed_orders'    => (int) $row->completed_orders,
            'total_parts_cost'    => (float) $row->total_parts_cost,
            'total_labor_cost'    => (float) $row->total_labor_cost,
            'total_shipping_cost' => (float) $row->total_shipping_cost,
            'total_cost'          => (float) $row->total_cost,
            'warranty_cost'       => (float) $row->warranty_cost,
            'paid_cost'           => (float) $row->paid_cost,
            'total_hours'         => (float) ($hours->total_hours ?? 0),
        ];
    }

    /**
     * 月度成本 (近 12 个月)
     */
    public function byMonth(int $months = 12, array $filters = []): array
    {
        [$where, $bindings] = $this->buildFilters($filters);
        $bindings['months'] = $months;

        $rows = DB::select("
            SELECT
                TO_CHAR(received_at, 'YYYY-MM')                    AS month,
                COUNT(*)                                            AS orders_count,
                COALESCE(SUM(parts_cost),    0)::numeric(14,2)      AS parts_cost,
                COALESCE(SUM(labor_cost),    0)::numeric(14,2)      AS labor_cost,
                COALESCE(SUM(shipping_cost), 0)::numeric(14,2)      AS shipping_cost,
                COALESCE(SUM(total_cost),    0)::numeric(14,2)      AS total_cost
            FROM repair_orders
            WHERE status IN ('completed','closed','shipped_back')
              AND received_at >= (CURRENT_DATE - (:months || ' months')::interval)
              {$where}
            GROUP BY month
            ORDER BY month DESC
        ", $bindings);

        return array_map(fn($r) => [
            'month'         => $r->month,
            'orders_count'  => (int) $r->orders_count,
            'parts_cost'    => (float) $r->parts_cost,
            'labor_cost'    => (float) $r->labor_cost,
            'shipping_cost' => (float) $r->shipping_cost,
            'total_cost'    => (float) $r->total_cost,
        ], $rows);
    }

    /**
     * 按项目汇总
     */
    public function byProject(array $filters = []): array
    {
        [$where, $bindings] = $this->buildFilters($filters);

        $rows = DB::select("
            SELECT
                ro.project_id,
                COALESCE(p.name, '未关联项目')                     AS project_name,
                COALESCE(p.project_no, '')                        AS project_code,
                COUNT(*)                                            AS orders_count,
                COALESCE(SUM(ro.total_cost), 0)::numeric(14,2)     AS total_cost
            FROM repair_orders ro
            LEFT JOIN projects p ON p.id = ro.project_id
            WHERE ro.status IN ('completed','closed','shipped_back')
              {$where}
            GROUP BY ro.project_id, p.name, p.project_no
            ORDER BY total_cost DESC
            LIMIT 50
        ", $bindings);

        return array_map(fn($r) => [
            'project_id'   => $r->project_id ? (int) $r->project_id : null,
            'project_name' => $r->project_name,
            'project_code' => $r->project_code,
            'orders_count' => (int) $r->orders_count,
            'total_cost'   => (float) $r->total_cost,
        ], $rows);
    }

    /**
     * 按客户汇总
     */
    public function byCustomer(array $filters = []): array
    {
        [$where, $bindings] = $this->buildFilters($filters);

        $rows = DB::select("
            SELECT
                ro.customer_id,
                COALESCE(c.name, '未关联客户')                    AS customer_name,
                COUNT(*)                                            AS orders_count,
                COALESCE(SUM(ro.total_cost), 0)::numeric(14,2)     AS total_cost,
                COALESCE(SUM(CASE WHEN ro.is_warranty THEN ro.total_cost ELSE 0 END), 0)::numeric(14,2) AS warranty_cost,
                COALESCE(SUM(CASE WHEN NOT ro.is_warranty THEN ro.total_cost ELSE 0 END), 0)::numeric(14,2) AS paid_cost
            FROM repair_orders ro
            LEFT JOIN customers c ON c.id = ro.customer_id
            WHERE ro.status IN ('completed','closed','shipped_back')
              {$where}
            GROUP BY ro.customer_id, c.name
            ORDER BY total_cost DESC
            LIMIT 50
        ", $bindings);

        return array_map(fn($r) => [
            'customer_id'   => $r->customer_id ? (int) $r->customer_id : null,
            'customer_name' => $r->customer_name,
            'orders_count'  => (int) $r->orders_count,
            'total_cost'    => (float) $r->total_cost,
            'warranty_cost' => (float) $r->warranty_cost,
            'paid_cost'     => (float) $r->paid_cost,
        ], $rows);
    }

    /**
     * 按维修方式 (4 选 1) 汇总
     */
    public function byMethod(array $filters = []): array
    {
        [$where, $bindings] = $this->buildFilters($filters);

        $rows = DB::select("
            SELECT
                COALESCE(ro.method_type, 'unspecified')           AS method_type,
                COUNT(*)                                            AS orders_count,
                COALESCE(SUM(ro.total_cost), 0)::numeric(14,2)     AS total_cost
            FROM repair_orders ro
            WHERE ro.status IN ('completed','closed','shipped_back')
              {$where}
            GROUP BY method_type
            ORDER BY total_cost DESC
        ", $bindings);

        // 计算占比
        $total = array_sum(array_column($rows, 'total_cost'));

        return array_map(function($r) use ($total) {
            $cost = (float) $r->total_cost;
            return [
                'method_type'  => $r->method_type,
                'orders_count' => (int) $r->orders_count,
                'total_cost'   => $cost,
                'percentage'   => $total > 0 ? round($cost / $total * 100, 2) : 0,
            ];
        }, $rows);
    }

    /**
     * 本月售后成本 (dashboard 卡片用)
     */
    public function thisMonth(): array
    {
        $rows = DB::selectOne("
            SELECT
                COALESCE(SUM(total_cost), 0)::numeric(14,2) AS cost,
                COUNT(*)                                    AS orders_count
            FROM repair_orders
            WHERE status IN ('completed','closed','shipped_back')
              AND DATE_TRUNC('month', received_at) = DATE_TRUNC('month', CURRENT_DATE)
        ");

        $total = DB::selectOne("
            SELECT COALESCE(SUM(COALESCE(budget_device,0) + COALESCE(budget_material,0) + COALESCE(budget_labor,0) + COALESCE(budget_outsource,0) + COALESCE(budget_other,0)), 0)::numeric(14,2) AS total
            FROM projects
            WHERE status IN ('active', 'completed', 'warranty')
        ");

        $cost = (float) $rows->cost;
        $totalContract = (float) ($total->total ?? 0);
        $ratio = $totalContract > 0 ? round($cost / $totalContract * 100, 2) : 0;

        return [
            'cost'           => $cost,
            'orders_count'   => (int) $rows->orders_count,
            'total_contract' => $totalContract,
            'ratio'          => $ratio, // 售后成本 / 合同金额 (%)
        ];
    }

    /**
     * 构造 WHERE 子句和参数绑定
     */
    private function buildFilters(array $filters): array
    {
        $clauses = [];
        $bindings = [];

        if (!empty($filters['from'])) {
            $clauses[] = 'AND received_at >= :from';
            $bindings['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $clauses[] = 'AND received_at <= :to';
            $bindings['to'] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['customer_id'])) {
            $clauses[] = 'AND ro.customer_id = :customer_id';
            $bindings['customer_id'] = (int) $filters['customer_id'];
        }
        if (!empty($filters['project_id'])) {
            $clauses[] = 'AND ro.project_id = :project_id';
            $bindings['project_id'] = (int) $filters['project_id'];
        }
        if (!empty($filters['method_type'])) {
            $clauses[] = 'AND ro.method_type = :method_type';
            $bindings['method_type'] = $filters['method_type'];
        }
        if (!empty($filters['is_warranty'])) {
            $clauses[] = 'AND ro.is_warranty = :is_warranty';
            $bindings['is_warranty'] = filter_var($filters['is_warranty'], FILTER_VALIDATE_BOOLEAN);
        }

        return [implode("\n  ", $clauses), $bindings];
    }

    // =================== V1.2.9 All-Scope (4 数据源聚合) ===================

    /**
     * 全局项目成本概览 — 聚合 4 个数据源
     *   1. repair_orders       (status IN completed/closed, total_cost)
     *   2. project_actual_costs (amount)
     *   3. project_materials   (total_cost)
     *   4. project_settlements (cost_labor + cost_material + cost_outsource + cost_other)
     */
    public function overviewAll(array $filters = []): array
    {
        $from = !empty($filters['from']) ? $filters['from'] : null;
        $to   = !empty($filters['to'])   ? $filters['to']   : null;

        // 1. 维修成本
        $repairRow = DB::selectOne("
            SELECT
                COUNT(*)                                        AS repair_orders,
                COALESCE(SUM(total_cost), 0)::numeric(14,2)     AS repair_cost,
                COALESCE(SUM(CASE WHEN is_warranty THEN total_cost ELSE 0 END), 0)::numeric(14,2) AS warranty_cost,
                COALESCE(SUM(CASE WHEN NOT is_warranty THEN total_cost ELSE 0 END), 0)::numeric(14,2) AS paid_cost,
                COALESCE(SUM(parts_cost),    0)::numeric(14,2)  AS parts_cost,
                COALESCE(SUM(labor_cost),    0)::numeric(14,2)  AS labor_cost,
                COALESCE(SUM(shipping_cost), 0)::numeric(14,2)  AS shipping_cost
            FROM repair_orders
            WHERE status IN ('completed','closed','shipped_back')
              " . ($from ? "AND received_at >= ? " : '') . "
              " . ($to   ? "AND received_at <= ? " : '') . "
        ", array_filter([$from ? $from . ' 00:00:00' : null, $to ? $to . ' 23:59:59' : null]));

        // 2. 项目实际成本
        $pacRow = DB::selectOne("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0)::numeric(14,2) AS total
            FROM project_actual_costs
            WHERE 1=1
              " . ($from ? "AND cost_date >= ? " : '') . "
              " . ($to   ? "AND cost_date <= ? "   : '') . "
        ", array_filter([$from, $to]));

        // 3. 项目物料 (用 total_cost 字段, use_date 作为时间维度)
        $matRow = DB::selectOne("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(total_cost), 0)::numeric(14,2) AS total
            FROM project_materials
            WHERE 1=1
              " . ($from ? "AND use_date >= ? " : '') . "
              " . ($to   ? "AND use_date <= ? "   : '') . "
        ", array_filter([$from, $to]));

        // 4. 项目结算 (4 个成本字段相加)
        $setRow = DB::selectOne("
            SELECT COUNT(*) AS cnt,
                   COALESCE(SUM(cost_labor),0)::numeric(14,2) AS cost_labor,
                   COALESCE(SUM(cost_material),0)::numeric(14,2) AS cost_material,
                   COALESCE(SUM(cost_outsource),0)::numeric(14,2) AS cost_outsource,
                   COALESCE(SUM(cost_other),0)::numeric(14,2) AS cost_other,
                   (COALESCE(SUM(cost_labor),0) + COALESCE(SUM(cost_material),0) + COALESCE(SUM(cost_outsource),0) + COALESCE(SUM(cost_other),0))::numeric(14,2) AS total
            FROM project_settlements
            WHERE 1=1
              " . ($from ? "AND settlement_date >= ? " : '') . "
              " . ($to   ? "AND settlement_date <= ? "   : '') . "
        ", array_filter([$from, $to]));

        $repair = (float)($repairRow->repair_cost ?? 0);
        $pac    = (float)($pacRow->total ?? 0);
        $mat    = (float)($matRow->total ?? 0);
        $set    = (float)($setRow->total ?? 0);

        return [
            'scope'                => 'all',
            // 维修
            'completed_orders'     => (int)($repairRow->repair_orders ?? 0),
            'total_parts_cost'     => (float)($repairRow->parts_cost ?? 0),
            'total_labor_cost'     => (float)($repairRow->labor_cost ?? 0),
            'total_shipping_cost'  => (float)($repairRow->shipping_cost ?? 0),
            'warranty_cost'        => (float)($repairRow->warranty_cost ?? 0),
            'paid_cost'            => (float)($repairRow->paid_cost ?? 0),
            'repair_cost'          => $repair,
            // 项目 4 类
            'project_actual_cost'  => $pac,
            'project_materials_total' => $mat,
            'project_settlements_total' => $set,
            'project_settlement_labor' => (float)($setRow->cost_labor ?? 0),
            'project_settlement_material' => (float)($setRow->cost_material ?? 0),
            'project_settlement_outsource' => (float)($setRow->cost_outsource ?? 0),
            'project_settlement_other' => (float)($setRow->cost_other ?? 0),
            // 总
            'total_cost'           => round($repair + $pac + $mat + $set, 2),
        ];
    }

    /**
     * 全局项目成本按月聚合
     */
    public function byMonthAll(int $months = 12, array $filters = []): array
    {
        // 维修
        $repairRows = DB::select("
            SELECT TO_CHAR(received_at, 'YYYY-MM') AS month,
                   COALESCE(SUM(total_cost), 0)::numeric(14,2) AS repair_cost,
                   COALESCE(SUM(parts_cost),    0)::numeric(14,2) AS parts_cost,
                   COALESCE(SUM(labor_cost),    0)::numeric(14,2) AS labor_cost,
                   COALESCE(SUM(shipping_cost), 0)::numeric(14,2) AS shipping_cost
            FROM repair_orders
            WHERE status IN ('completed','closed','shipped_back')
              AND received_at >= (CURRENT_DATE - (? || ' months')::interval)
            GROUP BY month
        ", [$months]);

        // 项目实际成本
        $pacRows = DB::select("
            SELECT TO_CHAR(cost_date, 'YYYY-MM') AS month,
                   COALESCE(SUM(amount), 0)::numeric(14,2) AS actual_cost
            FROM project_actual_costs
            WHERE cost_date >= (CURRENT_DATE - (? || ' months')::interval)
            GROUP BY month
        ", [$months]);

        // 项目物料
        $matRows = DB::select("
            SELECT TO_CHAR(use_date, 'YYYY-MM') AS month,
                   COALESCE(SUM(total_cost), 0)::numeric(14,2) AS material_cost
            FROM project_materials
            WHERE use_date >= (CURRENT_DATE - (? || ' months')::interval)
            GROUP BY month
        ", [$months]);

        // 项目结算
        $setRows = DB::select("
            SELECT TO_CHAR(settlement_date, 'YYYY-MM') AS month,
                   (COALESCE(SUM(cost_labor),0) + COALESCE(SUM(cost_material),0) + COALESCE(SUM(cost_outsource),0) + COALESCE(SUM(cost_other),0))::numeric(14,2) AS settlement_cost
            FROM project_settlements
            WHERE settlement_date >= (CURRENT_DATE - (? || ' months')::interval)
            GROUP BY month
        ", [$months]);

        $byMonth = [];
        foreach (array_merge($repairRows, $pacRows, $matRows, $setRows) as $r) {
            $m = $r->month;
            if (!isset($byMonth[$m])) {
                $byMonth[$m] = [
                    'month' => $m, 'repair_cost' => 0, 'parts_cost' => 0,
                    'labor_cost' => 0, 'shipping_cost' => 0,
                    'actual_cost' => 0, 'material_cost' => 0, 'settlement_cost' => 0,
                ];
            }
            foreach (['repair_cost','parts_cost','labor_cost','shipping_cost','actual_cost','material_cost','settlement_cost'] as $f) {
                $byMonth[$m][$f] += (float)($r->$f ?? 0);
            }
        }

        $result = array_map(function ($row) {
            $row['total_cost'] = round($row['repair_cost'] + $row['actual_cost'] + $row['material_cost'] + $row['settlement_cost'], 2);
            return $row;
        }, array_values($byMonth));

        usort($result, fn ($a, $b) => strcmp($b['month'], $a['month']));
        return $result;
    }

    /**
     * 全局按项目聚合成本 (所有项目，不只是维修)
     */
    public function byProjectAll(array $filters = []): array
    {
        // 4 个独立查询, 然后 PHP 里聚合 (避免 CTE UNION 字段名歧义)
        $repairRows = DB::select("
            SELECT ro.project_id,
                   SUM(ro.total_cost)::numeric(14,2) AS repair_cost,
                   COUNT(*) AS repair_orders
            FROM repair_orders ro
            WHERE ro.status IN ('completed','closed','shipped_back')
              AND ro.project_id IS NOT NULL
            GROUP BY ro.project_id
        ");
        $pacRows = DB::select("
            SELECT pac.project_id, SUM(pac.amount)::numeric(14,2) AS actual_cost
            FROM project_actual_costs pac
            GROUP BY pac.project_id
        ");
        $matRows = DB::select("
            SELECT pm.project_id, SUM(pm.total_cost)::numeric(14,2) AS material_cost
            FROM project_materials pm
            GROUP BY pm.project_id
        ");
        $setRows = DB::select("
            SELECT ps.project_id,
                   (COALESCE(SUM(ps.cost_labor),0) + COALESCE(SUM(ps.cost_material),0) + COALESCE(SUM(ps.cost_outsource),0) + COALESCE(SUM(ps.cost_other),0))::numeric(14,2) AS settlement_cost
            FROM project_settlements ps
            GROUP BY ps.project_id
        ");

        $agg = [];
        foreach ($repairRows as $r) {
            $pid = (int) $r->project_id;
            $agg[$pid] = ['project_id' => $pid, 'repair_cost' => (float)$r->repair_cost, 'repair_orders' => (int)$r->repair_orders, 'actual_cost' => 0, 'material_cost' => 0, 'settlement_cost' => 0];
        }
        foreach ($pacRows as $r) {
            $pid = (int) $r->project_id;
            $agg[$pid] ??= ['project_id' => $pid, 'repair_cost' => 0, 'repair_orders' => 0, 'actual_cost' => 0, 'material_cost' => 0, 'settlement_cost' => 0];
            $agg[$pid]['actual_cost'] = (float)$r->actual_cost;
        }
        foreach ($matRows as $r) {
            $pid = (int) $r->project_id;
            $agg[$pid] ??= ['project_id' => $pid, 'repair_cost' => 0, 'repair_orders' => 0, 'actual_cost' => 0, 'material_cost' => 0, 'settlement_cost' => 0];
            $agg[$pid]['material_cost'] = (float)$r->material_cost;
        }
        foreach ($setRows as $r) {
            $pid = (int) $r->project_id;
            $agg[$pid] ??= ['project_id' => $pid, 'repair_cost' => 0, 'repair_orders' => 0, 'actual_cost' => 0, 'material_cost' => 0, 'settlement_cost' => 0];
            $agg[$pid]['settlement_cost'] = (float)$r->settlement_cost;
        }

        // join project names
        $pids = array_keys($agg);
        $names = [];
        if ($pids) {
            $placeholders = implode(',', array_fill(0, count($pids), '?'));
            $nameRows = DB::select("SELECT id, name, COALESCE(project_no, '') AS project_no FROM projects WHERE id IN ($placeholders)", $pids);
            foreach ($nameRows as $n) {
                $names[(int)$n->id] = ['name' => $n->name, 'code' => $n->project_no];
            }
        }

        $result = [];
        foreach ($agg as $row) {
            $info = $names[$row['project_id']] ?? ['name' => '未知项目', 'code' => ''];
            $row['total_cost'] = round($row['repair_cost'] + $row['actual_cost'] + $row['material_cost'] + $row['settlement_cost'], 2);
            $row['project_name'] = $info['name'];
            $row['project_code'] = $info['code'];
            $result[] = $row;
        }
        usort($result, fn($a, $b) => $b['total_cost'] <=> $a['total_cost']);
        return array_slice($result, 0, 100);
    }

    /**
     * 全局按客户聚合成本
     */
    public function byCustomerAll(array $filters = []): array
    {
        $repairRows = DB::select("
            SELECT ro.customer_id,
                   SUM(ro.total_cost)::numeric(14,2) AS repair_cost,
                   COUNT(*) AS orders_count
            FROM repair_orders ro
            WHERE ro.status IN ('completed','closed','shipped_back')
              AND ro.customer_id IS NOT NULL
            GROUP BY ro.customer_id
        ");
        $pacRows = DB::select("
            SELECT p.customer_id, SUM(pac.amount)::numeric(14,2) AS actual_cost
            FROM project_actual_costs pac
            JOIN projects p ON p.id = pac.project_id
            WHERE p.customer_id IS NOT NULL
            GROUP BY p.customer_id
        ");
        $matRows = DB::select("
            SELECT p.customer_id, SUM(pm.total_cost)::numeric(14,2) AS material_cost
            FROM project_materials pm
            JOIN projects p ON p.id = pm.project_id
            WHERE p.customer_id IS NOT NULL
            GROUP BY p.customer_id
        ");
        $setRows = DB::select("
            SELECT p.customer_id,
                   (COALESCE(SUM(ps.cost_labor),0) + COALESCE(SUM(ps.cost_material),0) + COALESCE(SUM(ps.cost_outsource),0) + COALESCE(SUM(ps.cost_other),0))::numeric(14,2) AS settlement_cost
            FROM project_settlements ps
            JOIN projects p ON p.id = ps.project_id
            WHERE p.customer_id IS NOT NULL
            GROUP BY p.customer_id
        ");

        $agg = [];
        foreach ($repairRows as $r) {
            $cid = (int) $r->customer_id;
            $agg[$cid] = ['customer_id' => $cid, 'repair_cost' => (float)$r->repair_cost, 'orders_count' => (int)$r->orders_count, 'actual_cost' => 0, 'material_cost' => 0, 'settlement_cost' => 0];
        }
        foreach ($pacRows as $r) {
            $cid = (int) $r->customer_id;
            $agg[$cid] ??= ['customer_id' => $cid, 'repair_cost' => 0, 'orders_count' => 0, 'actual_cost' => 0, 'material_cost' => 0, 'settlement_cost' => 0];
            $agg[$cid]['actual_cost'] = (float)$r->actual_cost;
        }
        foreach ($matRows as $r) {
            $cid = (int) $r->customer_id;
            $agg[$cid] ??= ['customer_id' => $cid, 'repair_cost' => 0, 'orders_count' => 0, 'actual_cost' => 0, 'material_cost' => 0, 'settlement_cost' => 0];
            $agg[$cid]['material_cost'] = (float)$r->material_cost;
        }
        foreach ($setRows as $r) {
            $cid = (int) $r->customer_id;
            $agg[$cid] ??= ['customer_id' => $cid, 'repair_cost' => 0, 'orders_count' => 0, 'actual_cost' => 0, 'material_cost' => 0, 'settlement_cost' => 0];
            $agg[$cid]['settlement_cost'] = (float)$r->settlement_cost;
        }

        $cids = array_keys($agg);
        $names = [];
        if ($cids) {
            $placeholders = implode(',', array_fill(0, count($cids), '?'));
            $nameRows = DB::select("SELECT id, name FROM users WHERE id IN ($placeholders)", $cids);
            foreach ($nameRows as $n) {
                $names[(int)$n->id] = $n->name;
            }
        }

        $result = [];
        foreach ($agg as $row) {
            $row['total_cost'] = round($row['repair_cost'] + $row['actual_cost'] + $row['material_cost'] + $row['settlement_cost'], 2);
            $row['customer_name'] = $names[$row['customer_id']] ?? '未知客户';
            $result[] = $row;
        }
        usort($result, fn($a, $b) => $b['total_cost'] <=> $a['total_cost']);
        return array_slice($result, 0, 100);
    }
}
