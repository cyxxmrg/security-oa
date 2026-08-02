<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>OA BI 报表 - 完整版</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1f2937; margin: 20px; }
        h1 { font-size: 18pt; color: #1e3a8a; border-bottom: 2px solid #1e3a8a; padding-bottom: 4px; page-break-before: always; }
        h1:first-of-type { page-break-before: avoid; }
        h2 { font-size: 13pt; color: #1e3a8a; margin-top: 14px; }
        .meta { color: #6b7280; font-size: 8pt; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin: 6px 0 14px; }
        th { background: #1e3a8a; color: #fff; padding: 5px; text-align: left; }
        td { padding: 4px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) { background: #f9fafb; }
        .red { color: #dc2626; font-weight: bold; }
        .yellow { color: #d97706; font-weight: bold; }
        .green { color: #059669; font-weight: bold; }
        .num { text-align: right; font-family: 'Consolas', monospace; }
        .bar { background: linear-gradient(to right, #1e3a8a 0%, #1e3a8a var(--w), #f3f4f6 var(--w), #f3f4f6 100%); height: 16px; border-radius: 2px; }
        .legend { font-size: 8pt; color: #6b7280; margin-bottom: 4px; }
        .footer { margin-top: 20px; padding-top: 6px; border-top: 1px solid #e5e7eb; font-size: 7.5pt; color: #9ca3af; }
    </style>
</head>
<body>
    <h1 style="page-break-before: avoid;">📊 OA 完整业务报表</h1>
    <div class="meta">生成: {{ $generated_at }} | {{ $generated_by }} | 范围: {{ $report }}</div>

    {{-- ===== 1. 营收 ===== --}}
    @if(isset($data['revenue']))
    <h1>1. 营收分析</h1>
    <h2>1.1 12 月营收趋势 (按月)</h2>
    <table>
        <thead>
            <tr><th>月份</th><th>订单数</th><th>客户数</th><th class="num">毛收入</th><th class="num">已回款</th><th class="num">待回款</th></tr>
        </thead>
        <tbody>
            @php
                $byMonth = [];
                foreach ($data['revenue']['rows'] as $r) {
                    $key = $r->period_key;
                    if (!isset($byMonth[$key])) {
                        $byMonth[$key] = (object)[
                            'period_key' => $key,
                            'orders' => 0, 'customers' => 0,
                            'gross' => 0, 'received' => 0, 'pending' => 0,
                        ];
                    }
                    $byMonth[$key]->orders += (int)$r->order_count;
                    $byMonth[$key]->customers = max($byMonth[$key]->customers, (int)$r->customer_count);
                    $byMonth[$key]->gross += (float)$r->gross_revenue;
                    $byMonth[$key]->received += (float)$r->received_amount;
                    $byMonth[$key]->pending += (float)$r->pending_amount;
                }
            @endphp
            @foreach($byMonth as $m)
            <tr>
                <td>{{ $m->period_key }}</td>
                <td class="num">{{ $m->orders }}</td>
                <td class="num">{{ $m->customers }}</td>
                <td class="num">¥{{ number_format($m->gross, 0) }}</td>
                <td class="num green">¥{{ number_format($m->received, 0) }}</td>
                <td class="num red">¥{{ number_format($m->pending, 0) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>1.2 行业分布 (近 12 月)</h2>
    <table>
        <thead><tr><th>行业</th><th class="num">订单数</th><th class="num">毛收入</th><th class="num">回款率</th></tr></thead>
        <tbody>
            @php
                $byIndustry = [];
                foreach ($data['revenue']['rows'] as $r) {
                    $k = $r->industry;
                    if (!isset($byIndustry[$k])) $byIndustry[$k] = ['orders' => 0, 'gross' => 0, 'received' => 0];
                    $byIndustry[$k]['orders'] += (int)$r->order_count;
                    $byIndustry[$k]['gross'] += (float)$r->gross_revenue;
                    $byIndustry[$k]['received'] += (float)$r->received_amount;
                }
            @endphp
            @foreach($byIndustry as $ind => $v)
            <tr>
                <td>{{ $ind }}</td>
                <td class="num">{{ $v['orders'] }}</td>
                <td class="num">¥{{ number_format($v['gross'], 0) }}</td>
                <td class="num">{{ $v['gross'] > 0 ? round($v['received'] / $v['gross'] * 100, 1) : 0 }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ===== 2. 销售漏斗 ===== --}}
    @if(isset($data['funnel']))
    <h1>2. 销售漏斗</h1>
    <h2>2.1 阶段转化 (近 {{ count($data['funnel']['rows']) }} 周)</h2>
    <table>
        <thead><tr><th>周</th><th>线索</th><th>审查</th><th>报价</th><th>谈判</th><th>合同</th><th>成交</th><th>丢单</th><th class="num">成交额</th><th class="num">转化率</th></tr></thead>
        <tbody>
            @foreach($data['funnel']['rows'] as $r)
            <tr>
                <td>{{ $r->period_key }}</td>
                <td>{{ $r->s_lead }}</td>
                <td>{{ $r->s_qualify }}</td>
                <td>{{ $r->s_proposal }}</td>
                <td>{{ $r->s_negotiation }}</td>
                <td>{{ $r->s_contract }}</td>
                <td class="green">{{ $r->s_won }}</td>
                <td class="red">{{ $r->s_lost }}</td>
                <td class="num">¥{{ number_format($r->won_amount, 0) }}</td>
                <td class="num">{{ $r->conversion_rate }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if(isset($data['funnel']['bottleneck']))
    <h2>2.2 瓶颈分析</h2>
    <div class="legend">转化率最低环节:</div>
    <p><strong>{{ $data['funnel']['bottleneck']['from'] }} → {{ $data['funnel']['bottleneck']['to'] }}</strong> 仅 <strong class="red">{{ $data['funnel']['bottleneck']['rate'] }}%</strong> 通过 (严重度: {{ $data['funnel']['bottleneck']['severity'] }})</p>
    @endif
    @endif

    {{-- ===== 3. 项目健康 ===== --}}
    @if(isset($data['projects']))
    <h1>3. 项目健康度</h1>
    <h2>3.1 汇总统计</h2>
    <table>
        <thead><tr><th>状态</th><th class="num">项目数</th></tr></thead>
        <tbody>
            <tr><td class="green">🟢 健康 (>=80)</td><td class="num">{{ $data['projects']['stats']['green'] ?? 0 }}</td></tr>
            <tr><td class="yellow">🟡 关注 (60-79)</td><td class="num">{{ $data['projects']['stats']['yellow'] ?? 0 }}</td></tr>
            <tr><td class="red">🔴 告警 (<60)</td><td class="num">{{ $data['projects']['stats']['red'] ?? 0 }}</td></tr>
            <tr><td><strong>合计</strong></td><td class="num"><strong>{{ $data['projects']['stats']['total'] ?? 0 }}</strong></td></tr>
        </tbody>
    </table>

    <h2>3.2 详细列表</h2>
    <table>
        <thead><tr><th>项目</th><th>类型</th><th>阶段</th><th>经理</th><th>进度</th><th>成本</th><th>质量</th><th>排班</th><th>综合</th></tr></thead>
        <tbody>
            @foreach($data['projects']['rows'] as $p)
            <tr>
                <td>[{{ $p->project_code }}] {{ $p->project_name }}</td>
                <td>{{ $p->project_type }}</td>
                <td>{{ $p->stage }}</td>
                <td>{{ $p->manager_name ?? '未分配' }}</td>
                <td class="num">{{ $p->score_progress }}</td>
                <td class="num">{{ $p->score_cost }}</td>
                <td class="num">{{ $p->score_quality }}</td>
                <td class="num">{{ $p->score_schedule }}</td>
                <td class="num {{ $p->health_color }}"><strong>{{ $p->health_score }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ===== 4. RFM ===== --}}
    @if(isset($data['rfm']))
    <h1>4. 客户 RFM 价值分析</h1>
    <h2>4.1 段位分布</h2>
    <table>
        <thead><tr><th>段位</th><th class="num">客户数</th><th class="num">总消费</th><th class="num">占比</th></tr></thead>
        <tbody>
            @php $totalMoney = array_sum(array_column($data['rfm']['segments'], 'total')); @endphp
            @foreach($data['rfm']['segments'] as $s)
            <tr>
                <td>{{ $s->segment_label }}</td>
                <td class="num">{{ $s->cnt }}</td>
                <td class="num">¥{{ number_format($s->total, 0) }}</td>
                <td class="num">{{ $totalMoney > 0 ? round($s->total / $totalMoney * 100, 1) : 0 }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>4.2 Top 30 客户</h2>
    <table>
        <thead><tr><th>客户</th><th>行业</th><th>R</th><th>F</th><th>M</th><th class="num">频次</th><th class="num">消费</th><th>段位</th></tr></thead>
        <tbody>
            @foreach(array_slice($data['rfm']['rows'], 0, 30) as $c)
            <tr>
                <td>{{ $c->customer_name }}</td>
                <td>{{ $c->industry }}</td>
                <td class="num">{{ $c->r_score }}</td>
                <td class="num">{{ $c->f_score }}</td>
                <td class="num">{{ $c->m_score }}</td>
                <td class="num">{{ $c->frequency }}</td>
                <td class="num">¥{{ number_format($c->monetary, 0) }}</td>
                <td>{{ $c->segment_label }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ===== 5. 库存 ===== --}}
    @if(isset($data['inventory']))
    <h1>5. 库存周转</h1>
    <h2>5.1 状态汇总</h2>
    <table>
        <thead><tr><th>状态</th><th class="num">SKU 数</th></tr></thead>
        <tbody>
            <tr><td>缺货 (shortage)</td><td class="num red">{{ $data['inventory']['stats']['shortage'] ?? 0 }}</td></tr>
            <tr><td>断货 (stockout)</td><td class="num red">{{ $data['inventory']['stats']['stockout'] ?? 0 }}</td></tr>
            <tr><td>呆滞 90 天+ (stagnant)</td><td class="num yellow">{{ $data['inventory']['stats']['stagnant'] ?? 0 }}</td></tr>
            <tr><td>超储 (overstock)</td><td class="num yellow">{{ $data['inventory']['stats']['overstock'] ?? 0 }}</td></tr>
            <tr><td>正常 (normal)</td><td class="num green">{{ $data['inventory']['stats']['normal'] ?? 0 }}</td></tr>
        </tbody>
    </table>
    <p><strong>库存总价值: </strong>¥{{ number_format($data['inventory']['stats']['total_value'] ?? 0, 0) }}</p>

    <h2>5.2 重点关注物料 (按价值排序 Top 30)</h2>
    <table>
        <thead><tr><th>物料</th><th>分类</th><th class="num">当前</th><th class="num">90天出</th><th class="num">库龄</th><th class="num">价值</th><th>状态</th></tr></thead>
        <tbody>
            @foreach(array_slice($data['inventory']['rows'], 0, 30) as $i)
            <tr>
                <td>[{{ $i->item_code }}] {{ $i->item_name }}</td>
                <td>{{ $i->category }}</td>
                <td class="num">{{ $i->current_stock }}</td>
                <td class="num">{{ $i->outbound_90d ?? 0 }}</td>
                <td class="num">{{ $i->aging_days == 999 ? '从未出' : $i->aging_days . '天' }}</td>
                <td class="num">¥{{ number_format($i->stock_value, 0) }}</td>
                <td class="{{ $i->status === 'shortage' || $i->status === 'stockout' ? 'red' : '' }}">{{ $i->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ===== 6. 利润表 ===== --}}
    @if(isset($data['pnl']))
    <h1>6. 财务利润表</h1>
    <table>
        <thead><tr><th>月份</th><th class="num">营收</th><th class="num">成本</th><th class="num">费用</th><th class="num">净利润</th><th class="num">净利率</th></tr></thead>
        <tbody>
            @foreach($data['pnl']['rows'] as $r)
            <tr>
                <td>{{ $r->period_key }}</td>
                <td class="num">¥{{ number_format($r->total_revenue, 0) }}</td>
                <td class="num">¥{{ number_format($r->total_cost, 0) }}</td>
                <td class="num">¥{{ number_format($r->total_expense, 0) }}</td>
                <td class="num {{ $r->net_profit >= 0 ? 'green' : 'red' }}">¥{{ number_format($r->net_profit, 0) }}</td>
                <td class="num">{{ $r->profit_margin }}%</td>
            </tr>
            @endforeach
            <tr style="background: #f3f4f6; font-weight: bold;">
                <td>合计</td>
                <td class="num">¥{{ number_format($data['pnl']['summary']['total_revenue'], 0) }}</td>
                <td class="num">¥{{ number_format($data['pnl']['summary']['total_cost'], 0) }}</td>
                <td class="num">¥{{ number_format($data['pnl']['summary']['total_expense'], 0) }}</td>
                <td class="num green">¥{{ number_format($data['pnl']['summary']['total_profit'], 0) }}</td>
                <td class="num">{{ $data['pnl']['summary']['avg_margin'] }}%</td>
            </tr>
        </tbody>
    </table>
    @endif

    <div class="footer">
        OA 完整 BI 报表 | 基于 PostgreSQL 物化视图 (凌晨 02:30 自动刷新) | 报告 ID: {{ md5($generated_at . $report) }}<br>
        物化视图源: mv_revenue_monthly / mv_sales_funnel / mv_project_health / mv_customer_rfm / mv_inventory_aging / mv_finance_pnl
    </div>
</body>
</html>
