<template>
  <div class="analytics-page">
    <div class="page-header">
      <h2>💵 财务利润表</h2>
      <div class="page-actions">
        <el-date-picker v-model="dateRange" type="monthrange" range-separator="至" start-placeholder="开始月" end-placeholder="结束月" format="YYYY-MM" value-format="YYYY-MM" :clearable="false" />
        <el-button :icon="Refresh" @click="load">刷新</el-button>
        <el-button type="primary" :icon="Download" @click="exportPdf">导出 PDF</el-button>
      </div>
    </div>

    <!-- KPI 概览 -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-label">总营收</div>
        <div class="kpi-value">¥{{ formatNumber(summary.total_revenue) }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">总成本</div>
        <div class="kpi-value red">¥{{ formatNumber(summary.total_cost) }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">总费用</div>
        <div class="kpi-value yellow">¥{{ formatNumber(summary.total_expense) }}</div>
      </div>
      <div class="kpi-card" :class="{ 'green-card': summary.total_profit > 0, 'red-card': summary.total_profit < 0 }">
        <div class="kpi-label">净利润</div>
        <div class="kpi-value" :class="summary.total_profit > 0 ? 'green' : 'red'">
          ¥{{ formatNumber(summary.total_profit) }}
        </div>
        <div class="kpi-delta">净利率: {{ summary.avg_margin }}%</div>
      </div>
    </div>

    <!-- 利润趋势图 -->
    <el-card>
      <template #header>月度利润趋势</template>
      <v-chart v-if="trendOption" :option="trendOption" :autoresize="true" style="height: 380px" />
    </el-card>

    <!-- 成本结构饼图 -->
    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="12">
        <el-card>
          <template #header>成本结构 (累计)</template>
          <v-chart v-if="costOption" :option="costOption" :autoresize="true" style="height: 320px" />
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card>
          <template #header>营收 vs 利润</template>
          <v-chart v-if="profitOption" :option="profitOption" :autoresize="true" style="height: 320px" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 明细表 -->
    <el-card style="margin-top: 16px">
      <template #header>月度利润明细</template>
      <el-table :data="rows" stripe>
        <el-table-column prop="period_key" label="月份" width="100" />
        <el-table-column label="营收" align="right" :formatter="(r) => '¥' + formatNumber(r.total_revenue)" />
        <el-table-column label="成本" align="right" :formatter="(r) => '¥' + formatNumber(r.total_cost)" />
        <el-table-column label="费用" align="right" :formatter="(r) => '¥' + formatNumber(r.total_expense)" />
        <el-table-column label="净利润" align="right">
          <template #default="{ row }">
            <span :class="Number(row.net_profit) >= 0 ? 'green' : 'red'">
              ¥{{ formatNumber(row.net_profit) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="profit_margin" label="净利率" align="right" :formatter="(r) => r.profit_margin + '%'" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { use } from 'echarts/core'
import VChart from 'vue-echarts'
import { CanvasRenderer } from 'echarts/renderers'
import { BarChart, LineChart, PieChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, LegendComponent, GridComponent } from 'echarts/components'
import { Refresh, Download } from '@element-plus/icons-vue'
import { getFinancePnl, exportAnalyticsPdf } from '@/api/analytics'
import { ElMessage } from 'element-plus'

use([CanvasRenderer, BarChart, LineChart, PieChart, TitleComponent, TooltipComponent, LegendComponent, GridComponent])

const dateRange = ref<[string, string]>([
  new Date(Date.now() - 365 * 86400e3).toISOString().slice(0, 7),
  new Date().toISOString().slice(0, 7),
])
const rows = ref<Record<string, unknown>[]>([])
const summary = ref<Record<string, unknown>>({ total_revenue: 0, total_cost: 0, total_expense: 0, total_profit: 0, avg_margin: 0 })

const trendOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  legend: { data: ['营收', '成本', '费用', '净利润'], top: 10 },
  grid: { left: 60, right: 30, top: 50, bottom: 30 },
  xAxis: { type: 'category', data: rows.value.map(r => r.period_key) },
  yAxis: { type: 'value', axisLabel: { formatter: (v: number) => '¥' + (v / 1000).toFixed(0) + 'k' } },
  series: [
    { name: '营收', type: 'bar', data: rows.value.map(r => Number(r.total_revenue)), itemStyle: { color: '#1e3a8a' } },
    { name: '成本', type: 'bar', data: rows.value.map(r => Number(r.total_cost)), itemStyle: { color: '#ef4444' } },
    { name: '费用', type: 'bar', data: rows.value.map(r => Number(r.total_expense)), itemStyle: { color: '#f59e0b' } },
    { name: '净利润', type: 'line', smooth: true, data: rows.value.map(r => Number(r.net_profit)), itemStyle: { color: '#10b981' } },
  ],
}))

const costOption = computed(() => ({
  tooltip: { trigger: 'item', formatter: '{b}: ¥{c} ({d}%)' },
  legend: { bottom: 0 },
  series: [{
    type: 'pie', radius: '70%',
    data: [
      { name: '成本', value: Number(summary.value.total_cost) || 0, itemStyle: { color: '#ef4444' } },
      { name: '费用', value: Number(summary.value.total_expense) || 0, itemStyle: { color: '#f59e0b' } },
      { name: '净利润', value: Math.max(Number(summary.value.total_profit), 0), itemStyle: { color: '#10b981' } },
    ],
  }],
}))

const profitOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  grid: { left: 60, right: 30, top: 30, bottom: 30 },
  xAxis: { type: 'category', data: rows.value.map(r => r.period_key) },
  yAxis: [
    { type: 'value', name: '营收', axisLabel: { formatter: (v: number) => '¥' + (v / 1000).toFixed(0) + 'k' } },
    { type: 'value', name: '净利润', position: 'right', axisLabel: { formatter: (v: number) => '¥' + (v / 1000).toFixed(0) + 'k' } },
  ],
  series: [
    { name: '营收', type: 'bar', data: rows.value.map(r => Number(r.total_revenue)), itemStyle: { color: '#1e3a8a' } },
    { name: '净利润', type: 'line', yAxisIndex: 1, smooth: true, data: rows.value.map(r => Number(r.net_profit)), itemStyle: { color: '#10b981' } },
  ],
}))

function formatNumber(n: number) {
  return (Number(n) || 0).toLocaleString('zh-CN', { maximumFractionDigits: 0 })
}

async function load() {
  const params: Record<string, unknown> = {}
  if (dateRange.value?.[0]) params.start = dateRange.value[0]
  if (dateRange.value?.[1]) params.end = dateRange.value[1]
  try {
    const resp = await getFinancePnl(params)
    const data = resp?.data ?? resp
    rows.value = data.rows || []
    summary.value = data.summary || summary.value
  } catch (e: unknown) {
    ElMessage.error('加载财务数据失败: ' + (e.message || '未知错误'))
  }
}

function exportPdf() { void exportAnalyticsPdf('pnl', 'full') }
onMounted(load)
watch(dateRange, () => load())
</script>

<style scoped>
.analytics-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.page-actions { display: flex; gap: 8px; }
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.kpi-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px; }
.kpi-card.green-card { border-left: 4px solid #10b981; }
.kpi-card.red-card { border-left: 4px solid #ef4444; }
.kpi-label { font-size: 12px; color: #6b7280; }
.kpi-value { font-size: 22px; font-weight: 700; color: #1e3a8a; margin: 4px 0; }
.kpi-value.green { color: #059669; }
.kpi-value.red { color: #dc2626; }
.kpi-value.yellow { color: #d97706; }
.kpi-delta { font-size: 11px; color: #6b7280; margin-top: 4px; }
.green { color: #059669; font-weight: 600; }
.red { color: #dc2626; font-weight: 600; }
</style>
