<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>OA BI 报表 - 执行摘要</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #1f2937; margin: 24px; }
        h1 { font-size: 20pt; color: #1e3a8a; border-bottom: 3px solid #1e3a8a; padding-bottom: 6px; }
        h2 { font-size: 14pt; color: #1e3a8a; margin-top: 18px; }
        .meta { color: #6b7280; font-size: 9pt; margin-bottom: 16px; }
        .kpi-grid { display: table; width: 100%; margin-bottom: 12px; }
        .kpi { display: table-cell; width: 33%; padding: 10px; border: 1px solid #e5e7eb; }
        .kpi .label { font-size: 8pt; color: #6b7280; text-transform: uppercase; }
        .kpi .value { font-size: 16pt; font-weight: bold; color: #1e3a8a; margin: 4px 0; }
        .kpi .delta { font-size: 9pt; }
        .delta.up { color: #059669; }
        .delta.down { color: #dc2626; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin: 8px 0; }
        th { background: #f3f4f6; padding: 6px; text-align: left; border-bottom: 2px solid #d1d5db; }
        td { padding: 5px; border-bottom: 1px solid #e5e7eb; }
        .red { color: #dc2626; font-weight: bold; }
        .yellow { color: #d97706; font-weight: bold; }
        .green { color: #059669; font-weight: bold; }
        .risk-box { background: #fef2f2; border-left: 4px solid #dc2626; padding: 8px; margin: 8px 0; }
        .alert-box { background: #fffbeb; border-left: 4px solid #d97706; padding: 8px; margin: 8px 0; }
        .ok-box { background: #f0fdf4; border-left: 4px solid #059669; padding: 8px; margin: 8px 0; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8pt; color: #9ca3af; }
    </style>
</head>
<body>
    <h1>📊 OA 业务报表 - 执行摘要</h1>
    <div class="meta">
        生成时间: {{ $generated_at }} | 操作人: {{ $generated_by }} | 报表范围: {{ $report }} | 数据延迟: 24h (凌晨 02:30 刷新)
    </div>

    {{-- ===== KPI 概览 ===== --}}
    @if(isset($data['revenue']))
    <h2>💰 营收概览</h2>
    <div class="kpi-grid">
        <div class="kpi">
            <div class="label">本月营收</div>
            <div class="value">¥{{ number_format($data['revenue']['summary']['total_gross'] ?? 0, 0) }}</div>
        </div>
        <div class="kpi">
            <div class="label">已回款</div>
            <div class="value">¥{{ number_format($data['revenue']['summary']['total_received'] ?? 0, 0) }}</div>
        </div>
        <div class="kpi">
            <div class="label">回款率</div>
            <div class="value">{{ $data['revenue']['summary']['collection_rate'] ?? 0 }}%</div>
        </div>
    </div>
    @endif

    {{-- ===== 风险预警 ===== --}}
    @php
        $alerts = [];
        if (isset($data['projects'])) {
            $redProjects = collect($data['projects']['rows'])->where('health_color', 'red');
            foreach ($redProjects as $p) {
                $alerts[] = ['level' => 'red', 'msg' => "项目 [{$p->project_code}] {$p->project_name} 健康度 {$p->health_score}/100 红色告警"];
            }
        }
        if (isset($data['inventory'])) {
            $shortages = collect($data['inventory']['rows'])->whereIn('status', ['shortage', 'stockout']);
            foreach ($shortages as $i) {
                $alerts[] = ['level' => 'red', 'msg' => "物料 [{$i->item_code}] {$i->item_name} 库存 {$i->current_stock} 状态 {$i->status}"];
            }
        }
        if (isset($data['funnel']['bottleneck']) && $data['funnel']['bottleneck']['severity'] === 'high') {
            $b = $data['funnel']['bottleneck'];
            $alerts[] = ['level' => 'yellow', 'msg' => "销售漏斗瓶颈: {$b['from']} → {$b['to']} 转化率仅 {$b['rate']}%"];
        }
    @endphp
    @if(count($alerts) > 0)
    <h2>⚠️ 风险预警清单</h2>
    @foreach($alerts as $a)
    <div class="{{ $a['level'] === 'red' ? 'risk-box' : 'alert-box' }}">
        🔴 {{ $a['msg'] }}
    </div>
    @endforeach
    @else
    <div class="ok-box">✅ 当前无重大风险</div>
    @endif

    {{-- ===== 项目健康 ===== --}}
    @if(isset($data['projects']))
    <h2>📋 项目健康度 (Top 10 差)</h2>
    <table>
        <thead>
            <tr><th>项目</th><th>阶段</th><th>经理</th><th>健康度</th><th>状态</th></tr>
        </thead>
        <tbody>
            @foreach(array_slice($data['projects']['rows'], 0, 10) as $p)
            <tr>
                <td>[{{ $p->project_code }}] {{ $p->project_name }}</td>
                <td>{{ $p->stage }}</td>
                <td>{{ $p->manager_name ?? '未分配' }}</td>
                <td class="{{ $p->health_color }}">{{ $p->health_score }}/100</td>
                <td class="{{ $p->health_color }}">{{ strtoupper($p->health_color) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ===== 库存 Top 10 ===== --}}
    @if(isset($data['inventory']))
    <h2>📦 库存价值 Top 10</h2>
    <table>
        <thead>
            <tr><th>物料</th><th>分类</th><th>当前</th><th>价值</th><th>状态</th></tr>
        </thead>
        <tbody>
            @foreach(array_slice($data['inventory']['rows'], 0, 10) as $i)
            <tr>
                <td>[{{ $i->item_code }}] {{ $i->item_name }}</td>
                <td>{{ $i->category }}</td>
                <td>{{ $i->current_stock }} {{ $i->unit }}</td>
                <td>¥{{ number_format($i->stock_value, 0) }}</td>
                <td class="{{ $i->status === 'shortage' || $i->status === 'stockout' ? 'red' : '' }}">
                    {{ $i->status }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ===== 财务摘要 ===== --}}
    @if(isset($data['pnl']))
    <h2>💵 财务摘要</h2>
    <div class="kpi-grid">
        <div class="kpi">
            <div class="label">总营收</div>
            <div class="value">¥{{ number_format($data['pnl']['summary']['total_revenue'] ?? 0, 0) }}</div>
        </div>
        <div class="kpi">
            <div class="label">总成本</div>
            <div class="value">¥{{ number_format($data['pnl']['summary']['total_cost'] ?? 0, 0) }}</div>
        </div>
        <div class="kpi">
            <div class="label">净利润</div>
            <div class="value">¥{{ number_format($data['pnl']['summary']['total_profit'] ?? 0, 0) }}</div>
        </div>
    </div>
    @endif

    <div class="footer">
        本报告基于 PostgreSQL 物化视图自动生成 | 数据来源: mv_revenue_monthly / mv_project_health / mv_inventory_aging / mv_finance_pnl 等 | 报告 ID: {{ md5($generated_at . $report) }}
    </div>
</body>
</html>
