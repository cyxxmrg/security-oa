<template>
  <el-card shadow="never">
    <div ref="chartRef" style="width:100%;height:300px" />
  </el-card>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import * as echarts from 'echarts/core'
import { BarChart, LineChart } from 'echarts/charts'
import {
  TitleComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent,
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([
  BarChart, LineChart,
  TitleComponent, TooltipComponent, GridComponent, LegendComponent,
  CanvasRenderer,
])

const props = defineProps<{
  data: Array<{ month: string; amount: number; paid?: number; received?: number }>
  kind?: 'supplier' | 'customer'  // supplier=已付 paid；customer=已收 received
}>()

const chartRef = ref<HTMLDivElement>()
let chart: echarts.ECharts | null = null

const buildOption = (): echarts.EChartsCoreOption => {
  const months = props.data.map(d => d.month || '-')
  const amounts = props.data.map(d => d.amount || 0)
  const secondaryName = props.kind === 'customer' ? '已收款' : '已付款'
  const secondary = props.data.map(d => d.paid ?? d.received ?? 0)
  return {
    tooltip: { trigger: 'axis' },
    legend: { data: ['发生额', secondaryName] },
    grid: { left: 50, right: 20, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: months },
    yAxis: { type: 'value' },
    series: [
      { name: '发生额', type: 'bar', data: amounts, itemStyle: { color: '#0C447C' } },
      { name: secondaryName, type: 'line', data: secondary, itemStyle: { color: '#67c23a' } },
    ],
  }
}

const init = () => {
  if (!chartRef.value) return
  chart = echarts.init(chartRef.value)
  chart.setOption(buildOption())
}

const resize = () => chart?.resize()

onMounted(() => {
  init()
  window.addEventListener('resize', resize)
})
onBeforeUnmount(() => {
  window.removeEventListener('resize', resize)
  chart?.dispose()
})
watch(() => props.data, () => chart?.setOption(buildOption(), true), { deep: true })
</script>
