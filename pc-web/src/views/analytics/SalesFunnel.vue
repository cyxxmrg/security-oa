<template>
  <div class="analytics-page">
    <div class="page-header">
      <h2>📊 销售漏斗</h2>
      <div class="page-actions">
        <el-select v-model="weeks" :width="120" @change="load" style="width: 140px">
          <el-option label="近 4 周" :value="4" />
          <el-option label="近 8 周" :value="8" />
          <el-option label="近 12 周" :value="12" />
          <el-option label="近 26 周" :value="26" />
        </el-select>
        <el-button :icon="Refresh" @click="load">刷新</el-button>
        <el-button type="primary" :icon="Download" @click="exportPdf">导出 PDF</el-button>
      </div>
    </div>

    <!-- 瓶颈预警 -->
    <el-alert v-if="bottleneck" :type="bottleneck.severity === 'high' ? 'error' : bottleneck.severity === 'medium' ? 'warning' : 'info'" :closable="false" style="margin-bottom: 16px">
      <template #title>
        🚨 漏斗瓶颈: <strong>{{ bottleneck.from }} → {{ bottleneck.to }}</strong>
        转化率仅 <strong class="bad">{{ bottleneck.rate }}%</strong> (严重度: {{ bottleneck.severity }})
        <span style="margin-left: 12px; color: #6b7280;">健康线: ≥50%</span>
      </template>
    </el-alert>

    <!-- KPI 概览 -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <div class="kpi-label">本期线索</div>
        <div class="kpi-value">{{ totals.lead }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">成交</div>
        <div class="kpi-value green">{{ totals.won }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">丢单</div>
        <div class="kpi-value red">{{ totals.lost }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">综合转化率</div>
        <div class="kpi-value">{{ conversionRate }}%</div>
      </div>
    </div>

    <!-- 主图: 漏斗图 -->
    <el-card class="chart-card">
      <template #header>阶段漏斗 (近 {{ weeks }} 周累计)</template>
      <v-chart v-if="funnelOption" :option="funnelOption" :autoresize="true" style="height: 420px" />
    </el-card>

    <!-- 周趋势 -->
    <el-card style="margin-top: 16px">
      <template #header>每周转化详情</template>
      <el-table :data="rows" stripe>
        <el-table-column prop="period_key" label="周" width="100" />
        <el-table-column v-for="(label, key) in stageLabels" :key="key" :prop="key" :label="label" align="right" width="80" />
        <el-table-column prop="won_amount" label="成交额" align="right" :formatter="(r) => '¥' + Number(r.won_amount).toLocaleString()" />
        <el-table-column prop="conversion_rate" label="转化率" align="right">
          <template #default="{ row }">
            <span :class="Number(row.conversion_rate) >= 30 ? 'green' : Number(row.conversion_rate) >= 15 ? 'yellow' : 'red'">
              {{ row.conversion_rate }}%
            </span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { use } from 'echarts/core'
import VChart from 'vue-echarts'
import { CanvasRenderer } from 'echarts/renderers'
import { FunnelChart, BarChart, LineChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, LegendComponent, GridComponent } from 'echarts/components'
import { Refresh, Download } from '@element-plus/icons-vue'
import { getSalesFunnel, getPdfUrl } from '@/api/analytics'
import { ElMessage } from 'element-plus'

use([CanvasRenderer, FunnelChart, BarChart, LineChart, TitleComponent, TooltipComponent, LegendComponent, GridComponent])

const weeks = ref(12)
const rows = ref<Record<string, unknown>[]>([])
const stageLabels: Record<string, string> = {
  s_lead: '线索', s_qualify: '审查', s_proposal: '报价',
  s_negotiation: '谈判', s_contract: '合同', s_won: '成交', s_lost: '丢单',
}
const bottleneck = ref<Record<string, unknown> | null>(null)

const totals = computed(() => ({
  lead: rows.value.reduce((s, r) => s + Number(r.s_lead), 0),
  won: rows.value.reduce((s, r) => s + Number(r.s_won), 0),
  lost: rows.value.reduce((s, r) => s + Number(r.s_lost), 0),
}))

const conversionRate = computed(() => {
  const t = totals.value
  return t.lead > 0 ? ((t.won / t.lead) * 100).toFixed(1) : '0'
})

const funnelOption = computed(() => {
  const t = totals.value
  // 漏斗图按阶段顺序
  const data = [
    { name: '线索', value: t.lead },
    { name: '审查', value: rows.value.reduce((s, r) => s + Number(r.s_qualify), 0) },
    { name: '报价', value: rows.value.reduce((s, r) => s + Number(r.s_proposal), 0) },
    { name: '谈判', value: rows.value.reduce((s, r) => s + Number(r.s_negotiation), 0) },
    { name: '合同', value: rows.value.reduce((s, r) => s + Number(r.s_contract), 0) },
    { name: '成交', value: t.won },
  ]
  return {
    tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
    legend: { bottom: 0 },
    series: [{
      type: 'funnel', left: '15%', right: '15%', top: 20, bottom: 40,
      min: 0, max: Math.max(...data.map(d => d.value), 1),
      sort: 'descending', gap: 4,
      label: { show: true, position: 'inside', formatter: '{b}\n{c}' },
      data,
    }],
  }
})

async function load() {
  try {
    const resp = await getSalesFunnel({ weeks: weeks.value })
    const data = resp?.data ?? resp
    rows.value = data.rows || []
    bottleneck.value = data.bottleneck || null
  } catch (e: unknown) {
    ElMessage.error('加载漏斗数据失败: ' + (e.message || '未知错误'))
  }
}

function exportPdf() { window.open(getPdfUrl('funnel', 'full'), '_blank') }
onMounted(load)
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
.bad { color: #dc2626; }
.green { color: #059669; }
.yellow { color: #d97706; }
.red { color: #dc2626; }
</style>
