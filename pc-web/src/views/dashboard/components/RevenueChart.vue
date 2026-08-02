<script setup lang="ts">
/**
 * RevenueChart — 营收趋势双柱图 (v0.3.21 升级到 ECharts)
 * v0.3.14 原生 CSS 实现 → v0.3.21 ECharts 升级
 * 支持 合同额/回款额 切换 + tooltip + 数值格式化
 */
import { ref, computed, shallowRef } from 'vue'
import VChart from 'vue-echarts'
import * as echarts from 'echarts/core'
import { BarChart, LineChart } from 'echarts/charts'
import {
  GridComponent,
  TooltipComponent,
  LegendComponent,
  TitleComponent,
  DataZoomComponent,
  MarkLineComponent,
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([
  BarChart, LineChart,
  GridComponent, TooltipComponent, LegendComponent, TitleComponent, DataZoomComponent, MarkLineComponent,
  CanvasRenderer,
])

interface MonthDatum {
  month: string
  contract: number
  payment: number
}

const props = defineProps<{
  data: MonthDatum[]
}>()

const revenueType = ref<'contract' | 'payment' | 'both'>('both')

// ECharts option - 双柱图
const chartOption = computed(() => {
  const months = props.data.map((d) => d.month)
  const contracts = props.data.map((d) => d.contract)
  const payments = props.data.map((d) => d.payment)

  // 根据模式决定 series
  const series: Record<string, unknown>[] = []
  if (revenueType.value === 'contract' || revenueType.value === 'both') {
    series.push({
      name: '合同额',
      type: 'bar',
      data: contracts,
      itemStyle: {
        borderRadius: [4, 4, 0, 0],
        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
          { offset: 0, color: '#0C447C' },
          { offset: 1, color: '#185FA5' },
        ]),
      },
      barWidth: revenueType.value === 'both' ? '30%' : '50%',
      label: {
        show: true,
        position: 'top',
        formatter: (p: Record<string, unknown>) => formatMoney(p.value),
        fontSize: 10,
        color: '#606266',
      },
    })
  }
  if (revenueType.value === 'payment' || revenueType.value === 'both') {
    series.push({
      name: '回款额',
      type: 'bar',
      data: payments,
      itemStyle: {
        borderRadius: [4, 4, 0, 0],
        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
          { offset: 0, color: '#1D9E75' },
          { offset: 1, color: '#58C499' },
        ]),
      },
      barWidth: revenueType.value === 'both' ? '30%' : '50%',
      label: {
        show: true,
        position: 'top',
        formatter: (p: Record<string, unknown>) => formatMoney(p.value),
        fontSize: 10,
        color: '#606266',
      },
    })
  }

  return {
    grid: { top: 30, right: 20, bottom: 30, left: 50, containLabel: true },
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow' },
      backgroundColor: 'rgba(50, 50, 50, 0.92)',
      borderColor: 'transparent',
      textStyle: { color: '#fff', fontSize: 12 },
      formatter: (params: Record<string, unknown>) => {
        if (!Array.isArray(params)) return ''
        const month = params[0]?.axisValue || ''
        let html = `<div style="font-weight:600;margin-bottom:6px">${month}</div>`
        params.forEach((p: Record<string, unknown>) => {
          html += `<div style="display:flex;align-items:center;gap:6px;margin:2px 0">
            <i style="display:inline-block;width:8px;height:8px;border-radius:2px;background:${p.color}"></i>
            <span style="flex:1">${p.seriesName}</span>
            <span style="font-weight:600">¥${(p.value || 0).toLocaleString()}</span>
          </div>`
        })
        return html
      },
    },
    legend: {
      show: revenueType.value === 'both',
      data: ['合同额', '回款额'],
      bottom: 0,
      textStyle: { color: '#606266', fontSize: 12 },
      icon: 'roundRect',
      itemWidth: 12,
      itemHeight: 12,
    },
    xAxis: {
      type: 'category',
      data: months,
      axisTick: { show: false },
      axisLine: { lineStyle: { color: '#DCDFE6' } },
      axisLabel: { color: '#909399', fontSize: 11 },
    },
    yAxis: {
      type: 'value',
      axisLine: { show: false },
      axisTick: { show: false },
      splitLine: { lineStyle: { color: '#F0F2F5', type: 'dashed' } },
      axisLabel: {
        color: '#909399',
        fontSize: 11,
        formatter: (v: number) => formatMoney(v),
      },
    },
    series,
    animationDuration: 800,
    animationEasing: 'cubicOut',
  }
})

const formatMoney = (n: number): string => {
  if (n >= 100000000) return `${(n / 100000000).toFixed(1)}亿`
  if (n >= 10000) return `${(n / 10000).toFixed(1)}万`
  return String(n)
}

// 汇总数据
const totals = computed(() => {
  const contract = props.data.reduce((s, d) => s + d.contract, 0)
  const payment = props.data.reduce((s, d) => s + d.payment, 0)
  return { contract, payment, rate: contract > 0 ? Math.round((payment / contract) * 100) : 0 }
})

const chartRef = shallowRef()
</script>

<template>
  <el-card shadow="never">
    <template #header>
      <div class="card-header">
        <div class="header-left">
          <span class="card-title">营收趋势（近 {{ data.length }} 个月）</span>
          <span class="totals">
            <span class="total-item">
              <i class="dot" style="background:#0C447C" />合同:
              <strong>¥{{ formatMoney(totals.contract) }}</strong>
            </span>
            <span class="total-item">
              <i class="dot" style="background:#1D9E75" />回款:
              <strong>¥{{ formatMoney(totals.payment) }}</strong>
            </span>
            <span class="total-item">
              回款率: <strong :class="totals.rate >= 80 ? 'rate-good' : totals.rate >= 50 ? 'rate-ok' : 'rate-bad'">
                {{ totals.rate }}%
              </strong>
            </span>
          </span>
        </div>
        <el-radio-group size="small" v-model="revenueType">
          <el-radio-button value="both">双柱</el-radio-button>
          <el-radio-button value="contract">合同</el-radio-button>
          <el-radio-button value="payment">回款</el-radio-button>
        </el-radio-group>
      </div>
    </template>
    <div class="revenue-chart">
      <v-chart
        ref="chartRef"
        :option="chartOption"
        :update-options="{ notMerge: true }"
        autoresize
        style="height: 280px; width: 100%"
      />
    </div>
  </el-card>
</template>

<style scoped>
.card-header {
  display: flex; justify-content: space-between; align-items: center;
  width: 100%;
  flex-wrap: wrap;
  gap: 12px;
}
.header-left {
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.card-title {
  font-size: 15px; font-weight: 600; color: #2c3e50;
  display: flex; align-items: center; gap: 8px;
}
.card-title::before {
  content: ''; display: inline-block;
  width: 3px; height: 14px;
  background: linear-gradient(180deg, #0C447C 0%, #1D9E75 100%);
  border-radius: 2px; flex-shrink: 0;
}
.totals {
  display: flex; gap: 16px; font-size: 12px; color: #606266;
}
.total-item {
  display: inline-flex; align-items: center; gap: 4px;
}
.total-item .dot {
  display: inline-block; width: 8px; height: 8px;
  border-radius: 2px; margin-right: 2px;
}
.total-item strong { color: #303133; font-weight: 600; }
.rate-good { color: #1D9E75; }
.rate-ok { color: #BA7517; }
.rate-bad { color: #A32D2D; }
.revenue-chart { padding: 8px 0; }
</style>
