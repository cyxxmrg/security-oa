<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * V1.2.9p E2: 修复 6 大物化视图的 source 表与 stage 映射
 *
 * === 背景 ===
 * E1 (2026_06_28_140000_create_analytics_materialized_views) 写时假设:
 *   - 应收款表叫 customer_receivables
 *   - opportunities.stage 取值 = (lead/qualify/proposal/negotiation/contract/won/lost)
 *
 * 实际 117 部署后的真实情况 (2026-07-07 探查):
 *   - customer_receivables 表存在但 0 行 (空, 被多次 wipe)
 *   - 真正的应收款表叫 receivables (100 行, status=paid/partial)
 *   - payables 是 194 行的应付表
 *   - opportunities.stage 真实值 = (inquiry/qualification/quoted/negotiation/won/lost/proposal)
 *   - service_orders 表存在且 100 行, rating 数据可用
 *
 * 结果: 老板看板的 7 个 API 中 4 个返回 0:
 *   - /analytics/revenue          → 总营收 0
 *   - /analytics/sales-funnel     → 各阶段全 0 (stage 字符串错位)
 *   - /analytics/customer-rfm     → monetary=0 (source 错)
 *   - /analytics/finance-pnl      → 净亏 600 (revenue 部分全 0)
 *
 * === 修复 ===
 * 1. 6 个视图全部 DROP+CREATE (idempotent, 防 CONCURRENTLY 锁)
 * 2. 全部迁到真实存在的表 (receivables/payables/finance_payments)
 * 3. status 字符串映射: 'paid' = 收讫, 'partial' = 部分
 * 4. opportunities.stage 过滤重写:
 *    lead  ← inquiry|lead
 *    qualify ← qualification
 *    proposal ← proposal|quoted
 *    negotiation ← negotiation|negotiating
 *    contract ← signed  (如果用到; 当前真实数据没有)
 *    won ← won
 *    lost ← lost
 * 5. customers 没有 created_by 列, 用 assigned_user_id (跟 sales_owner join)
 */
return new class extends Migration
{
    private const VIEWS = [
        'mv_revenue_monthly',
        'mv_sales_funnel',
        'mv_project_health',
        'mv_customer_rfm',
        'mv_inventory_aging',
        'mv_finance_pnl',
    ];

    public function up(): void
    {
        // ===== 先 DROP 全部 6 个视图 (idempotent) =====
        // 用 CASCADE 把关联 unique index 一起删
        foreach (self::VIEWS as $v) {
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS {$v} CASCADE");
        }

        // ===== 1. 月度营收: 改用 receivables =====
        DB::statement("
            CREATE MATERIALIZED VIEW mv_revenue_monthly AS
            SELECT
                to_char(date_trunc('month', COALESCE(r.due_date::timestamp, r.created_at)), 'YYYY-MM') AS period_key,
                COALESCE(c.industry, 'unknown')                            AS industry,
                COALESCE(s.department_id::text, '0')                        AS department_id,
                COUNT(DISTINCT r.id)                                       AS order_count,
                COUNT(DISTINCT r.customer_id)                              AS customer_count,
                COALESCE(SUM(r.amount), 0)                                 AS gross_revenue,
                COALESCE(SUM(r.received_amount), 0)                        AS received_amount,
                COALESCE(SUM(GREATEST(r.amount - r.received_amount, 0)), 0) AS pending_amount
            FROM receivables r
            LEFT JOIN customers c         ON c.id = r.customer_id
            LEFT JOIN users s             ON s.id = c.assigned_user_id
            WHERE r.status IN ('paid', 'partial', 'pending', 'overdue')
            GROUP BY 1, 2, 3
        ");
        DB::statement("
            CREATE UNIQUE INDEX idx_mv_revenue_monthly_pk
            ON mv_revenue_monthly (period_key, industry, department_id)
        ");
        DB::statement("CREATE INDEX idx_mv_revenue_period ON mv_revenue_monthly (period_key DESC)");

        // ===== 2. 销售漏斗: 修 stage 字符串 (对齐 opportunities 真实枚举) =====
        DB::statement("
            CREATE MATERIALIZED VIEW mv_sales_funnel AS
            SELECT
                to_char(date_trunc('week', COALESCE(o.created_at::timestamp, NOW())), 'IYYY-IW') AS period_key,
                COALESCE(o.sales_id::text, 'unassigned')                                AS sales_id,
                COUNT(*) FILTER (WHERE o.stage IN ('inquiry', 'lead', 'contact'))        AS s_lead,
                COUNT(*) FILTER (WHERE o.stage IN ('qualification', 'qualify'))         AS s_qualify,
                COUNT(*) FILTER (WHERE o.stage IN ('proposal', 'quoted'))               AS s_proposal,
                COUNT(*) FILTER (WHERE o.stage IN ('negotiation', 'negotiating'))       AS s_negotiation,
                COUNT(*) FILTER (WHERE o.stage IN ('contract', 'signed'))               AS s_contract,
                COUNT(*) FILTER (WHERE o.stage IN ('won', 'signed'))                    AS s_won,
                COUNT(*) FILTER (WHERE o.stage = 'lost')                                AS s_lost,
                COALESCE(SUM(o.estimated_amount) FILTER (WHERE o.stage IN ('won', 'signed')), 0) AS won_amount
            FROM opportunities o
            WHERE o.created_at >= NOW() - INTERVAL '12 months'
            GROUP BY 1, 2
        ");
        DB::statement("
            CREATE UNIQUE INDEX idx_mv_sales_funnel_pk
            ON mv_sales_funnel (period_key, sales_id)
        ");

        // ===== 3. 项目健康度: 原 schema 对齐 OK, 保留实现 + 兼容 service_orders.project_id =====
        DB::statement("
            CREATE MATERIALIZED VIEW mv_project_health AS
            WITH project_total_budget AS (
                SELECT
                    id AS project_id,
                    COALESCE(budget_device, 0) + COALESCE(budget_material, 0) + COALESCE(budget_labor, 0)
                    + COALESCE(budget_outsource, 0) + COALESCE(budget_other, 0) AS total_budget
                FROM projects
            ),
            project_actual_cost AS (
                SELECT project_id, COALESCE(SUM(amount), 0) AS total_actual
                FROM project_actual_costs
                GROUP BY project_id
            )
            SELECT
                p.id                                        AS project_id,
                p.project_no                                AS project_code,
                p.name                                      AS project_name,
                p.type                                      AS project_type,
                p.stage                                     AS stage,
                p.status                                    AS status,
                p.manager_id                                AS manager_id,
                COALESCE(um.name, '未分配')                  AS manager_name,
                LEAST(100, GREATEST(0, COALESCE(p.progress, 0)))         AS score_progress,
                CASE
                    WHEN COALESCE(pac.total_actual, 0) = 0 THEN 100
                    WHEN COALESCE(ptb.total_budget, 0) = 0 THEN 50
                    WHEN pac.total_actual <= ptb.total_budget THEN 100
                    ELSE GREATEST(0, (ptb.total_budget::numeric / pac.total_actual * 100)::int)
                END                                          AS score_cost,
                LEAST(100, COALESCE(satisfaction.avg_score, 4) * 20) AS score_quality,
                CASE
                    WHEN p.end_date IS NULL THEN 50
                    WHEN p.end_date < CURRENT_DATE THEN 100
                    WHEN p.start_date IS NULL THEN 50
                    ELSE LEAST(100, GREATEST(0, ((CURRENT_DATE - p.start_date)::numeric / NULLIF((p.end_date - p.start_date), 0) * 100)::int))
                END                                          AS score_schedule,
                (
                    LEAST(100, GREATEST(0, COALESCE(p.progress, 0))) * 0.30 +
                    CASE
                        WHEN COALESCE(pac.total_actual, 0) = 0 THEN 100
                        WHEN COALESCE(ptb.total_budget, 0) = 0 THEN 50
                        WHEN pac.total_actual <= ptb.total_budget THEN 100
                        ELSE GREATEST(0, (ptb.total_budget::numeric / pac.total_actual * 100)::int)
                    END * 0.25 +
                    LEAST(100, COALESCE(satisfaction.avg_score, 4) * 20) * 0.25 +
                    CASE
                        WHEN p.end_date IS NULL THEN 50
                        WHEN p.end_date < CURRENT_DATE THEN 100
                        WHEN p.start_date IS NULL THEN 50
                        ELSE LEAST(100, GREATEST(0, ((CURRENT_DATE - p.start_date)::numeric / NULLIF((p.end_date - p.start_date), 0) * 100)::int))
                    END * 0.20
                )::int                                       AS health_score,
                CASE
                    WHEN (
                        LEAST(100, GREATEST(0, COALESCE(p.progress, 0))) * 0.30 +
                        CASE WHEN COALESCE(pac.total_actual, 0) = 0 THEN 100
                             WHEN COALESCE(ptb.total_budget, 0) = 0 THEN 50
                             WHEN pac.total_actual <= ptb.total_budget THEN 100
                             ELSE GREATEST(0, (ptb.total_budget::numeric / pac.total_actual * 100)::int)
                        END * 0.25 +
                        LEAST(100, COALESCE(satisfaction.avg_score, 4) * 20) * 0.25 +
                        CASE WHEN p.end_date IS NULL THEN 50
                             WHEN p.end_date < CURRENT_DATE THEN 100
                             WHEN p.start_date IS NULL THEN 50
                             ELSE LEAST(100, GREATEST(0, ((CURRENT_DATE - p.start_date)::numeric / NULLIF((p.end_date - p.start_date), 0) * 100)::int))
                        END * 0.20
                    ) >= 80 THEN 'green'
                    WHEN (
                        LEAST(100, GREATEST(0, COALESCE(p.progress, 0))) * 0.30 +
                        CASE WHEN COALESCE(pac.total_actual, 0) = 0 THEN 100
                             WHEN COALESCE(ptb.total_budget, 0) = 0 THEN 50
                             WHEN pac.total_actual <= ptb.total_budget THEN 100
                             ELSE GREATEST(0, (ptb.total_budget::numeric / pac.total_actual * 100)::int)
                        END * 0.25 +
                        LEAST(100, COALESCE(satisfaction.avg_score, 4) * 20) * 0.25 +
                        CASE WHEN p.end_date IS NULL THEN 50
                             WHEN p.end_date < CURRENT_DATE THEN 100
                             WHEN p.start_date IS NULL THEN 50
                             ELSE LEAST(100, GREATEST(0, ((CURRENT_DATE - p.start_date)::numeric / NULLIF((p.end_date - p.start_date), 0) * 100)::int))
                        END * 0.20
                    ) >= 60 THEN 'yellow'
                    ELSE 'red'
                END                                          AS health_color,
                NOW()                                        AS refreshed_at
            FROM projects p
            LEFT JOIN users um              ON um.id = p.manager_id
            LEFT JOIN project_total_budget ptb ON ptb.project_id = p.id
            LEFT JOIN project_actual_cost pac ON pac.project_id = p.id
            LEFT JOIN LATERAL (
                SELECT AVG(rating)::numeric AS avg_score
                FROM service_orders so
                WHERE so.project_id = p.id
                  AND so.rating IS NOT NULL
                  AND so.created_at >= NOW() - INTERVAL '6 months'
            ) satisfaction ON TRUE
            WHERE p.status NOT IN ('archived', 'cancelled')
        ");
        DB::statement("CREATE UNIQUE INDEX idx_mv_project_health_pk ON mv_project_health (project_id)");
        DB::statement("CREATE INDEX idx_mv_project_health_score ON mv_project_health (health_score, health_color)");

        // ===== 4. RFM: 改用 receivables =====
        DB::statement("
            CREATE MATERIALIZED VIEW mv_customer_rfm AS
            WITH rfm_raw AS (
                SELECT
                    c.id                                            AS customer_id,
                    c.name                                          AS customer_name,
                    c.industry                                      AS industry,
                    c.category                                      AS customer_level,
                    c.assigned_user_id                              AS sales_id,
                    MAX(r.due_date)                                 AS last_purchase_date,
                    COUNT(r.id)                                     AS frequency,
                    COALESCE(SUM(r.received_amount), 0)             AS monetary
                FROM customers c
                LEFT JOIN receivables r ON r.customer_id = c.id
                    AND r.status IN ('paid', 'partial')
                    AND r.due_date >= NOW() - INTERVAL '12 months'
                WHERE c.status = 'active'
                GROUP BY c.id, c.name, c.industry, c.category, c.assigned_user_id
            ),
            rfm_scored AS (
                SELECT
                    *,
                    CASE
                        WHEN last_purchase_date IS NULL THEN 1
                        WHEN last_purchase_date >= NOW() - INTERVAL '30 days' THEN 5
                        WHEN last_purchase_date >= NOW() - INTERVAL '90 days' THEN 4
                        WHEN last_purchase_date >= NOW() - INTERVAL '180 days' THEN 3
                        WHEN last_purchase_date >= NOW() - INTERVAL '365 days' THEN 2
                        ELSE 1
                    END AS r_score,
                    CASE
                        WHEN frequency >= 20 THEN 5
                        WHEN frequency >= 10 THEN 4
                        WHEN frequency >= 5  THEN 3
                        WHEN frequency >= 2  THEN 2
                        ELSE 1
                    END AS f_score,
                    CASE
                        WHEN monetary >= 1000000 THEN 5
                        WHEN monetary >= 500000  THEN 4
                        WHEN monetary >= 100000  THEN 3
                        WHEN monetary >= 10000   THEN 2
                        ELSE 1
                    END AS m_score
                FROM rfm_raw
            )
            SELECT
                *,
                (r_score + f_score + m_score)::numeric / 3 AS rfm_avg,
                CASE
                    WHEN r_score >= 4 AND f_score >= 4 AND m_score >= 4 THEN '重要价值客户'
                    WHEN r_score >= 4 AND f_score <= 2 THEN '重要发展客户'
                    WHEN r_score <= 2 AND f_score >= 4 THEN '重要保持客户'
                    WHEN r_score <= 2 AND f_score <= 2 AND m_score >= 3 THEN '重要挽留客户'
                    WHEN r_score >= 3 AND m_score >= 3 THEN '一般价值客户'
                    WHEN r_score <= 2 AND f_score <= 2 THEN '潜在客户'
                    ELSE '一般客户'
                END AS segment_label
            FROM rfm_scored
        ");
        DB::statement("CREATE UNIQUE INDEX idx_mv_customer_rfm_pk ON mv_customer_rfm (customer_id)");
        DB::statement("CREATE INDEX idx_mv_customer_rfm_segment ON mv_customer_rfm (segment_label, rfm_avg DESC)");

        // ===== 5. 库存周转: 保留原实现 (inventory_items/stock_records 正确) =====
        DB::statement("
            CREATE MATERIALIZED VIEW mv_inventory_aging AS
            SELECT
                i.id                                            AS item_id,
                i.code                                          AS item_code,
                i.name                                          AS item_name,
                i.category                                      AS category,
                i.unit                                          AS unit,
                COALESCE(i.current_stock, 0)                     AS current_stock,
                COALESCE(i.safety_stock, 0)                      AS safety_stock,
                COALESCE(i.min_stock, 0)                        AS min_stock,
                COALESCE(i.cost_price, 0)                       AS cost_price,
                COALESCE(i.current_stock, 0) * COALESCE(i.cost_price, 0) AS stock_value,
                last_in.last_inbound_date                       AS last_inbound_date,
                last_out.last_outbound_date                     AS last_outbound_date,
                COALESCE(inbound_90d.qty, 0)                    AS inbound_90d,
                COALESCE(outbound_90d.qty, 0)                   AS outbound_90d,
                CASE
                    WHEN last_out.last_outbound_date IS NULL THEN 999
                    ELSE (CURRENT_DATE - last_out.last_outbound_date)::int
                END                                             AS aging_days,
                CASE
                    WHEN COALESCE(i.current_stock, 0) <= 0 THEN 'stockout'
                    WHEN COALESCE(i.current_stock, 0) <= COALESCE(i.safety_stock, 0) THEN 'shortage'
                    WHEN last_out.last_outbound_date IS NULL
                         OR (NOW() - last_out.last_outbound_date::timestamp) > INTERVAL '90 days' THEN 'stagnant'
                    WHEN COALESCE(i.current_stock, 0) > COALESCE(i.safety_stock, 0) * 3 THEN 'overstock'
                    ELSE 'normal'
                END                                             AS status,
                NOW()                                           AS refreshed_at
            FROM inventory_items i
            LEFT JOIN LATERAL (
                SELECT MAX(created_at)::date AS last_inbound_date
                FROM stock_records
                WHERE inventory_item_id = i.id AND type = 'in'
            ) last_in ON TRUE
            LEFT JOIN LATERAL (
                SELECT MAX(created_at)::date AS last_outbound_date
                FROM stock_records
                WHERE inventory_item_id = i.id AND type = 'out'
            ) last_out ON TRUE
            LEFT JOIN LATERAL (
                SELECT SUM(quantity) AS qty
                FROM stock_records
                WHERE inventory_item_id = i.id AND type = 'in'
                  AND created_at >= NOW() - INTERVAL '90 days'
            ) inbound_90d ON TRUE
            LEFT JOIN LATERAL (
                SELECT SUM(quantity) AS qty
                FROM stock_records
                WHERE inventory_item_id = i.id AND type = 'out'
                  AND created_at >= NOW() - INTERVAL '90 days'
            ) outbound_90d ON TRUE
            WHERE COALESCE(i.status, 'active') = 'active'
        ");
        DB::statement("CREATE UNIQUE INDEX idx_mv_inventory_aging_pk ON mv_inventory_aging (item_id)");
        DB::statement("CREATE INDEX idx_mv_inventory_aging_status ON mv_inventory_aging (status, aging_days)");

        // ===== 6. 利润表: 收入=receivables, 成本=payables(paid/fully_paid), 费用=finance_payments(receivable_id IS NULL) =====
        DB::statement("
            CREATE MATERIALIZED VIEW mv_finance_pnl AS
            WITH revenue AS (
                SELECT
                    to_char(date_trunc('month', COALESCE(due_date::timestamp, created_at)), 'YYYY-MM') AS period_key,
                    COALESCE(SUM(received_amount), 0)                        AS total_revenue,
                    COALESCE(SUM(GREATEST(amount - received_amount, 0)), 0) AS accounts_receivable
                FROM receivables
                WHERE status IN ('paid', 'partial')
                GROUP BY 1
            ),
            cost AS (
                -- 已结算的应付 (材料/外协/分包成本)
                SELECT
                    to_char(date_trunc('month', COALESCE(due_date::timestamp, created_at)), 'YYYY-MM') AS period_key,
                    COALESCE(SUM(amount), 0)                              AS total_cost
                FROM payables
                WHERE status IN ('paid', 'fully_paid', 'partial')
                GROUP BY 1
            ),
            expense AS (
                -- finance_payments (营业外 / 期间费用)
                SELECT
                    to_char(date_trunc('month', COALESCE(payment_date::timestamp, created_at)), 'YYYY-MM') AS period_key,
                    COALESCE(SUM(amount), 0)                              AS total_expense
                FROM finance_payments
                WHERE payment_date IS NOT NULL
                GROUP BY 1
            )
            SELECT
                COALESCE(r.period_key, c.period_key, e.period_key) AS period_key,
                COALESCE(r.total_revenue, 0)                        AS total_revenue,
                COALESCE(r.accounts_receivable, 0)                  AS accounts_receivable,
                COALESCE(c.total_cost, 0)                           AS total_cost,
                COALESCE(e.total_expense, 0)                        AS total_expense,
                COALESCE(r.total_revenue, 0)
                  - COALESCE(c.total_cost, 0)
                  - COALESCE(e.total_expense, 0)                    AS net_profit,
                CASE
                    WHEN COALESCE(r.total_revenue, 0) = 0 THEN 0
                    ELSE ROUND(
                        (COALESCE(r.total_revenue, 0) - COALESCE(c.total_cost, 0) - COALESCE(e.total_expense, 0))::numeric
                        / r.total_revenue * 100, 2
                    )
                END                                                 AS profit_margin
            FROM revenue r
            FULL OUTER JOIN cost c     ON c.period_key = r.period_key
            FULL OUTER JOIN expense e  ON e.period_key = COALESCE(r.period_key, c.period_key)
        ");
        DB::statement("CREATE UNIQUE INDEX idx_mv_finance_pnl_pk ON mv_finance_pnl (period_key)");
        DB::statement("CREATE INDEX idx_mv_finance_pnl_period ON mv_finance_pnl (period_key DESC)");
    }

    public function down(): void
    {
        foreach (self::VIEWS as $v) {
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS {$v} CASCADE");
        }
    }
};
