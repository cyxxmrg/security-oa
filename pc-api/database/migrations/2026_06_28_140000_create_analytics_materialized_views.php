<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.2.7 E1: 6 大报表物化视图
 *
 * 背景: 报表查询涉及 opportunities/customer_receivables/projects/finance_payments
 *       等多张大表 join + 聚合, 实时查询慢 (5-30s)
 *       用 PostgreSQL MATERIALIZED VIEW 物化到物理存储, 查询 < 100ms
 *
 * 刷新策略:
 *   - 凌晨 02:00 用 RefreshMaterializedView Job (CONCURRENTLY 不锁表)
 *   - 实时数据用缓存 5min 兜底 (但 data 延迟 24h)
 *   - 手动触发: php artisan analytics:refresh {view}
 *
 * 注意: MATERIALIZED VIEW 需要 unique index 才能 REFRESH CONCURRENTLY
 *       用 (period_key, dimension) 做 unique
 *
 * 6 个视图:
 *   1. mv_revenue_monthly        - 月度营收 (按月 + 业务条线)
 *   2. mv_sales_funnel           - 销售漏斗 (阶段 + 时间)
 *   3. mv_project_health         - 项目健康度 (项目 + 5 维评分)
 *   4. mv_customer_rfm           - RFM 客户价值 (R × F × M)
 *   5. mv_inventory_aging        - 库存周转 (SKU + 库龄)
 *   6. mv_finance_pnl            - 月度利润表
 */
return new class extends Migration
{
    public function up(): void
    {
        // ===== 1. 月度营收 (含同比) =====
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_revenue_monthly AS
            SELECT
                to_char(date_trunc('month', COALESCE(cr.due_date::timestamp, cr.created_at)), 'YYYY-MM') AS period_key,
                COALESCE(c.industry, 'unknown')                            AS industry,
                COALESCE(s.department_id::text, '0')                        AS department_id,
                COUNT(DISTINCT cr.id)                                       AS order_count,
                COUNT(DISTINCT cr.customer_id)                              AS customer_count,
                COALESCE(SUM(cr.amount), 0)                                 AS gross_revenue,
                COALESCE(SUM(cr.received_amount), 0)                        AS received_amount,
                COALESCE(SUM(cr.amount - cr.received_amount), 0)           AS pending_amount
            FROM customer_receivables cr
            LEFT JOIN customers c        ON c.id = cr.customer_id
            LEFT JOIN users s             ON s.id = cr.created_by
            WHERE cr.status IN ('pending', 'partial', 'received', 'overdue')
            GROUP BY 1, 2, 3
        ");
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_revenue_monthly_pk
            ON mv_revenue_monthly (period_key, industry, department_id)
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_mv_revenue_period ON mv_revenue_monthly (period_key DESC)");

        // ===== 2. 销售漏斗 (按周聚合, 6 个阶段) =====
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_sales_funnel AS
            SELECT
                to_char(date_trunc('week', COALESCE(o.created_at, NOW())), 'IYYY-IW') AS period_key,
                COALESCE(o.sales_id::text, 'unassigned')                                AS sales_id,
                COUNT(*) FILTER (WHERE o.stage IN ('lead', 'contact'))                  AS s_lead,
                COUNT(*) FILTER (WHERE o.stage = 'qualify')                             AS s_qualify,
                COUNT(*) FILTER (WHERE o.stage = 'proposal')                            AS s_proposal,
                COUNT(*) FILTER (WHERE o.stage = 'negotiation')                         AS s_negotiation,
                COUNT(*) FILTER (WHERE o.stage = 'contract')                            AS s_contract,
                COUNT(*) FILTER (WHERE o.stage IN ('won', 'signed'))                    AS s_won,
                COUNT(*) FILTER (WHERE o.stage = 'lost')                                AS s_lost,
                COALESCE(SUM(o.estimated_amount) FILTER (WHERE o.stage IN ('won', 'signed')), 0) AS won_amount
            FROM opportunities o
            WHERE o.created_at >= NOW() - INTERVAL '12 months'
            GROUP BY 1, 2
        ");
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_sales_funnel_pk
            ON mv_sales_funnel (period_key, sales_id)
        ");

        // ===== 3. 项目健康度 (5 维评分) =====
        // 真实字段: project_no / name / customer_id / type / stage / status / progress / 5个 budget / start_date / end_date / actual_end_date
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_project_health AS
            WITH project_total_budget AS (
                SELECT
                    id AS project_id,
                    budget_device + budget_material + budget_labor + budget_outsource + budget_other AS total_budget
                FROM projects
            ),
            project_actual_cost AS (
                SELECT project_id, COALESCE(SUM(amount), 0) AS total_actual
                FROM project_actual_costs
                GROUP BY project_id
            )
            SELECT
                p.id                                        AS project_id,
                p.project_no                               AS project_code,
                p.name                                      AS project_name,
                p.type                                      AS project_type,
                p.stage                                     AS stage,
                p.status                                    AS status,
                p.manager_id                                AS manager_id,
                COALESCE(um.name, '未分配')                  AS manager_name,
                -- 进度评分: actual progress 本身 (0-100)
                LEAST(100, GREATEST(0, p.progress))         AS score_progress,
                -- 成本评分: 预算 / 实际 (实际 0 → 100, 实际 = 预算 → 100, 实际 > 预算 → 衰减)
                CASE
                    WHEN COALESCE(pac.total_actual, 0) = 0 THEN 100
                    WHEN COALESCE(ptb.total_budget, 0) = 0 THEN 50
                    WHEN pac.total_actual <= ptb.total_budget THEN 100
                    ELSE GREATEST(0, (ptb.total_budget::numeric / pac.total_actual * 100)::int)
                END                                          AS score_cost,
                -- 客户满意度评分 (从 service_orders.rating 平均, 0-5 → 0-100)
                LEAST(100, COALESCE(satisfaction.avg_score, 4) * 20) AS score_quality,
                -- 排班覆盖率 (placeholder: 用 start_date/end_date 算出天数 vs today)
                CASE
                    WHEN p.end_date IS NULL THEN 50
                    WHEN p.end_date < CURRENT_DATE THEN 100  -- 已结束 → 健康
                    WHEN p.start_date IS NULL THEN 50
                    ELSE LEAST(100, GREATEST(0, ((CURRENT_DATE - p.start_date)::numeric / NULLIF((p.end_date - p.start_date), 0) * 100)::int))
                END                                          AS score_schedule,
                -- 整体健康度 (权重: 进度 30% + 成本 25% + 质量 25% + 排班 20%)
                (
                    LEAST(100, GREATEST(0, p.progress)) * 0.30 +
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
                        LEAST(100, GREATEST(0, p.progress)) * 0.30 +
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
                        LEAST(100, GREATEST(0, p.progress)) * 0.30 +
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
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_project_health_pk
            ON mv_project_health (project_id)
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_mv_project_health_score ON mv_project_health (health_score, health_color)");

        // ===== 4. RFM 客户价值 (最近一次消费 + 频次 + 金额) =====
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_customer_rfm AS
            WITH rfm_raw AS (
                SELECT
                    c.id                                            AS customer_id,
                    c.name                                          AS customer_name,
                    c.industry                                      AS industry,
                    c.category                                      AS customer_level,
                    c.assigned_user_id                              AS sales_id,
                    MAX(cr.due_date)                                AS last_purchase_date,
                    COUNT(cr.id)                                    AS frequency,
                    COALESCE(SUM(cr.received_amount), 0)            AS monetary
                FROM customers c
                LEFT JOIN customer_receivables cr ON cr.customer_id = c.id
                    AND cr.status IN ('received', 'partial')
                    AND cr.due_date >= NOW() - INTERVAL '12 months'
                WHERE c.status = 'active'
                GROUP BY c.id, c.name, c.industry, c.category, c.assigned_user_id
            ),
            rfm_scored AS (
                SELECT
                    *,
                    -- R 评分 (1-5, 越近越高)
                    CASE
                        WHEN last_purchase_date IS NULL THEN 1
                        WHEN last_purchase_date >= NOW() - INTERVAL '30 days' THEN 5
                        WHEN last_purchase_date >= NOW() - INTERVAL '90 days' THEN 4
                        WHEN last_purchase_date >= NOW() - INTERVAL '180 days' THEN 3
                        WHEN last_purchase_date >= NOW() - INTERVAL '365 days' THEN 2
                        ELSE 1
                    END AS r_score,
                    -- F 评分 (1-5, 越多越高)
                    CASE
                        WHEN frequency >= 20 THEN 5
                        WHEN frequency >= 10 THEN 4
                        WHEN frequency >= 5 THEN 3
                        WHEN frequency >= 2 THEN 2
                        ELSE 1
                    END AS f_score,
                    -- M 评分 (1-5, 越多越高)
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
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_customer_rfm_pk
            ON mv_customer_rfm (customer_id)
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_mv_customer_rfm_segment ON mv_customer_rfm (segment_label, rfm_avg DESC)");

        // ===== 5. 库存周转 (库龄分析 + 周转率) =====
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_inventory_aging AS
            SELECT
                i.id                                            AS item_id,
                i.code                                          AS item_code,
                i.name                                          AS item_name,
                i.category                                      AS category,
                i.unit                                          AS unit,
                i.current_stock                                 AS current_stock,
                i.safety_stock                                  AS safety_stock,
                i.min_stock                                     AS min_stock,
                i.cost_price                                    AS cost_price,
                -- 价值
                i.current_stock * COALESCE(i.cost_price, 0)     AS stock_value,
                -- 最近一次入库日期
                last_in.last_inbound_date                       AS last_inbound_date,
                -- 最近一次出库日期
                last_out.last_outbound_date                     AS last_outbound_date,
                -- 90 天入库数
                COALESCE(inbound_90d.qty, 0)                    AS inbound_90d,
                -- 90 天出库数
                COALESCE(outbound_90d.qty, 0)                   AS outbound_90d,
                -- 库龄 (距今多少天无出库)
                CASE
                    WHEN last_out.last_outbound_date IS NULL THEN 999
                    ELSE (CURRENT_DATE - last_out.last_outbound_date)::int
                END                                             AS aging_days,
                -- 状态: 缺货/呆滞/正常
                CASE
                    WHEN i.current_stock <= i.safety_stock THEN 'shortage'
                    WHEN i.current_stock <= 0 THEN 'stockout'
                    WHEN last_out.last_outbound_date IS NULL
                         OR (NOW() - last_out.last_outbound_date) > INTERVAL '90 days' THEN 'stagnant'
                    WHEN i.current_stock > i.safety_stock * 3 THEN 'overstock'
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
                WHERE inventory_item_id = i.id
                  AND type = 'in'
                  AND created_at >= NOW() - INTERVAL '90 days'
            ) inbound_90d ON TRUE
            LEFT JOIN LATERAL (
                SELECT SUM(quantity) AS qty
                FROM stock_records
                WHERE inventory_item_id = i.id
                  AND type = 'out'
                  AND created_at >= NOW() - INTERVAL '90 days'
            ) outbound_90d ON TRUE
            WHERE i.status = 'active'
        ");
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_inventory_aging_pk
            ON mv_inventory_aging (item_id)
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_mv_inventory_aging_status ON mv_inventory_aging (status, aging_days)");

        // ===== 6. 月度利润表 =====
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS mv_finance_pnl AS
            WITH             revenue AS (
                SELECT
                    to_char(date_trunc('month', COALESCE(due_date::timestamp, created_at)), 'YYYY-MM') AS period_key,
                    COALESCE(SUM(received_amount), 0)                        AS total_revenue,
                    COALESCE(SUM(amount - received_amount), 0)               AS accounts_receivable
                FROM customer_receivables
                WHERE status IN ('received', 'partial')
                GROUP BY 1
            ),
            cost AS (
                SELECT
                    to_char(date_trunc('month', payment_date::timestamp), 'YYYY-MM') AS period_key,
                    COALESCE(SUM(amount), 0)                              AS total_cost
                FROM finance_payments
                WHERE payment_date IS NOT NULL
                  AND receivable_id IS NOT NULL  -- V1.2.7 E1 fix: 关联 receivable 的付款 = 销售成本
                GROUP BY 1
            ),
            expense AS (
                -- V1.2.7 E1 fix: 现实没 expenses 表, 用 finance_payments 不关联 receivable 的 = 期间费用
                SELECT
                    to_char(date_trunc('month', payment_date::timestamp), 'YYYY-MM') AS period_key,
                    COALESCE(SUM(amount), 0)                              AS total_expense
                FROM finance_payments
                WHERE payment_date IS NOT NULL
                  AND receivable_id IS NULL
                GROUP BY 1
            )
            SELECT
                COALESCE(r.period_key, c.period_key, e.period_key) AS period_key,
                COALESCE(r.total_revenue, 0)                        AS total_revenue,
                COALESCE(r.accounts_receivable, 0)                  AS accounts_receivable,
                COALESCE(c.total_cost, 0)                           AS total_cost,
                COALESCE(e.total_expense, 0)                        AS total_expense,
                -- 净利润 = 收入 - 成本 - 费用
                COALESCE(r.total_revenue, 0)
                  - COALESCE(c.total_cost, 0)
                  - COALESCE(e.total_expense, 0)                    AS net_profit,
                -- 净利率
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
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_finance_pnl_pk
            ON mv_finance_pnl (period_key)
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_mv_finance_pnl_period ON mv_finance_pnl (period_key DESC)");
    }

    public function down(): void
    {
        $views = [
            'mv_revenue_monthly',
            'mv_sales_funnel',
            'mv_project_health',
            'mv_customer_rfm',
            'mv_inventory_aging',
            'mv_finance_pnl',
        ];
        foreach ($views as $v) {
            DB::statement("DROP MATERIALIZED VIEW IF EXISTS {$v} CASCADE");
        }
    }
};
