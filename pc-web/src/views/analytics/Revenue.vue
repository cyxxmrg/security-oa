<template>
  <div class="analytics-page revenue-page">
    <div class="page-header">
      <h2>💰 营收分析</h2>
      <div class="page-actions">
        <el-date-picker v-model="dateRange" type="monthrange" range-separator="至" start-placeholder="开始月" end-placeholder="结束月" format="YYYY-MM" value-format="YYYY-MM" :clearable="false" />
        <el-select v-model="industry" placeholder="行业" clearable style="width: 140px">
          <el-option v-for="i in industryOptions" :key="i" :label="i" :value="i" />
        </el-select>
        <el-button :icon="Refresh" @click="reload">刷新</el-button>
        <el-button type="primary" :icon="Download" @click="exportPdf">导出 PDF</el-button>
      </div>
    </div>

    <!-- KPI 概览 -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-label">毛收入合计</div>
        <div class="kpi-value">¥{{ formatNumber(summary.total_gross) }}</div>
        <div class="kpi-delta up">↑ 本月营收</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">已回款</div>
        <div class="kpi-value green">¥{{ formatNumber(summary.total_received) }}</div>
        <div class="kpi-delta up">实收</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">待回款</div>
        <div class="kpi-value red">¥{{ formatNumber(summary.total_pending) }}</div>
        <div class="kpi-delta down">应收</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">回款率</div>
        <div class="kpi-value">{{ summary.collection_rate }}%</div>
        <div class="kpi-delta">{{ summary.collection_rate >= 80 ? '健康' : '需关注' }}</div>
      </div>
    </div>

    <!-- 主图: 12 月营收趋势 -->
    <el-card class="chart-card">
      <template #header>
        <div class="chart-header">
          <span>📈 月度营收趋势 (按月)</span>
          <span class="updated-at">数据更新于: {{ refreshedAt || '加载中' }}</span>
        </div>
      </template>
      <v-chart v-if="chartOption" :option="chartOption" :autoresize="true" style="height: 380px" />
    </el-card>

    <!-- 副图: 行业占比饼图 -->
    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="12">
        <el-card>
          <template #header>行业营收占比</template>
          <v-chart v-if="pieOption" :option="pieOption" :autoresize="true" style="height: 320px" />
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card>
          <template #header>订单数 vs 回款额</template>
          <v-chart v-if="scatterOption" :option="scatterOption" :autoresize="true" style="height: 320px" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 详细表格 -->
    <el-card style="margin-top: 16px">
      <template #header>月度营收明细</template>
      <el-table :data="monthRows" stripe>
        <el-table-column prop="period_key" label="月份" width="100" />
        <el-table-column prop="orders" label="订单数" align="right" width="100" />
        <el-table-column prop="customers" label="客户数" align="right" width="100" />
        <el-table-column prop="gross" label="毛收入" align="right" :formatter="(r) => '¥' + formatNumber(r.gross)" />
        <el-table-column prop="received" label="已回款" align="right" :formatter="(r) => '¥' + formatNumber(r.received)" />
        <el-table-column prop="pending" label="待回款" align="right" :formatter="(r) => '¥' + formatNumber(r.pending)" />
        <el-table-column label="回款率" align="right" :formatter="(r) => r.gross > 0 ? ((r.received / r.gross) * 100).toFixed(1) + '%' : '-'" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { use } from 'echarts/core'
import VChart from 'vue-echarts'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart, BarChart, PieChart, ScatterChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, LegendComponent, GridComponent, ToolboxComponent, DataZoomComponent } from 'echarts/components'
import { Refresh, Download } from '@element-plus/icons-vue'
import { getRevenue, getPdfUrl, getRefreshStatus } from '@/api/analytics'
import { ElMessage } from 'element-plus'

use([CanvasRenderer, LineChart, BarChart, PieChart, ScatterChart, TitleComponent, TooltipComponent, LegendComponent, GridComponent, ToolboxComponent, DataZoomComponent])

const dateRange = ref<[string, string]>([
  new Date().toISOString().slice(0, 7),
  new Date(Date.now() - 365 * 86400e3).toISOString().slice(0, 7),
].reverse() as [string, string])
const industry = ref<string>('')
const rows = ref<Record<string, unknown>[]>([])
const summary = ref<Record<string, unknown>>({ total_gross: 0, total_received: 0, total_pending: 0, collection_rate: 0 })
const refreshedAt = ref<string>('')

// 提取行业列表
const industryOptions = computed(() => Array.from(new Set(rows.value.map(r => r.industry).filter(Boolean))))

// 按月聚合
const monthRows = computed(() => {
  const map = new Map<string, Record<string, unknown>>()
  for (const r of rows.value) {
    const k = r.period_key
    if (!map.has(k)) {
      map.set(k, { period_key: k, orders: 0, customers: 0, gross: 0, received: 0, pending: 0 })
    }
    const m = map.get(k)!
    m.orders += Number(r.order_count) || 0
    m.customers = Math.max(m.customers, Number(r.customer_count) || 0)
    m.gross += Number(r.gross_revenue) || 0
    m.received += Number(r.received_amount) || 0
    m.pending += Number(r.pending_amount) || 0
  }
  return Array.from(map.values()).sort((a, b) => a.period_key.localeCompare(b.period_key))
})

const chartOption = computed(() => {
  const data = monthRows.value
  return {
    tooltip: { trigger: 'axis' },
    legend: { data: ['毛收入', '已回款', '待回款'], top: 10 },
    grid: { left: 60, right: 30, top: 50, bottom: 60 },
    toolbox: { feature: { dataZoom: {}, restore: {} } },
    xAxis: { type: 'category', data: data.map(d => d.period_key) },
    yAxis: { type: 'value', axisLabel: { formatter: v => '¥' + (v / 1000).toFixed(0) + 'k' } },
    series: [
      { name: '毛收入', type: 'line', smooth: true, data: data.map(d => d.gross), itemStyle: { color: '#1e3a8a' }, areaStyle: { opacity: 0.2 } },
      { name: '已回款', type: 'bar', data: data.map(d => d.received), itemStyle: { color: '#10b981' } },
      { name: '待回款', type: 'bar', data: data.map(d => d.pending), itemStyle: { color: '#ef4444' } },
    ],
  }
})

const pieOption = computed(() => {
  const map = new Map<string, number>()
  for (const r of rows.value) {
    map.set(r.industry, (map.get(r.industry) || 0) + Number(r.gross_revenue || 0))
  }
  return {
    tooltip: { trigger: 'item', formatter: '{b}<br/>¥{c} ({d}%)' },
    legend: { bottom: 0 },
    series: [{
      type: 'pie', radius: ['40%', '70%'],
      data: Array.from(map.entries()).map(([name, value]) => ({ name, value })),
    }],
  }
})

const scatterOption = computed(() => {
  const data = monthRows.value.map(d => [d.orders, d.received, d.period_key])
  return {
    tooltip: { trigger: 'item', formatter: (p: Record<string, unknown>) => `${p.data[2]}<br/>订单 ${p.data[0]}<br/>回款 ¥${p.data[1]}` },
    grid: { left: 50, right: 30, top: 30, bottom: 40 },
    xAxis: { name: '订单数' },
    yAxis: { name: '回款额', axisLabel: { formatter: (v: number) => '¥' + (v / 1000).toFixed(0) + 'k' } },
    series: [{ type: 'scatter', data, symbolSize: 18, itemStyle: { color: '#3b82f6' } }],
  }
})

function formatNumber(n: number | string) {
  const num = Number(n) || 0
  return num.toLocaleString('zh-CN', { maximumFractionDigits: 0 })
}

async function load() {
  const params: Record<string, unknown> = {}
  if (dateRange.value?.[0]) params.start = dateRange.value[0]
  if (dateRange.value?.[1]) params.end = dateRange.value[1]
  if (industry.value) params.industry = industry.value
  try {
    const resp = await getRevenue(params)
    const data = resp?.data ?? resp
    rows.value = data.rows || []
    summary.value = data.summary || summary.value
    refreshedAt.value = new Date().toLocaleString('zh-CN')
  } catch (e: unknown) {
    ElMessage.error('加载营收数据失败: ' + (e.message || '未知错误'))
  }
}

function reload() { load() }

function exportPdf() {
  window.open(getPdfUrl('revenue', 'full'), '_blank')
}

onMounted(async () => {
  await load()
  try {
    const status = await getRefreshStatus()
    const views = status?.data ?? status
    if (Array.isArray(views) && views[0]?.refreshed_at) refreshedAt.value = views[0].refreshed_at
  } catch { /* ignore */ }
})
watch([dateRange, industry], () => load())
</script>

<style scoped>
.analytics-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.page-actions { display: flex; gap: 8px; }
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.kpi-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px; }
.kpi-label { font-size: 12px; color: #6b7280; }
.kpi-value { font-size: 22px; font-weight: 700; color: #1e3a8a; margin: 4px 0; }
.kpi-value.green { color: #059669; }
.kpi-value.red { color: #dc2626; }
.kpi-delta { font-size: 11px; color: #6b7280; }
.kpi-delta.up { color: #059669; }
.kpi-delta.down { color: #dc2626; }
.chart-card { margin-bottom: 16px; }
.chart-header { display: flex; justify-content: space-between; align-items: center; }
.updated-at { font-size: 12px; color: #9ca3af; }
</style>
