<template>
  <div class="analytics-page">
    <div class="page-header">
      <h2>👥 客户 RFM 价值分析</h2>
      <div class="page-actions">
        <el-select v-model="segment" placeholder="客户段位" clearable @change="load" style="width: 180px">
          <el-option v-for="s in segmentOptions" :key="s" :label="s" :value="s" />
        </el-select>
        <el-button :icon="Refresh" @click="load">刷新</el-button>
        <el-button type="primary" :icon="Download" @click="exportPdf">导出 PDF</el-button>
      </div>
    </div>

    <!-- 段位分布 -->
    <el-card>
      <template #header>客户段位分布</template>
      <v-chart v-if="segmentOption" :option="segmentOption" :autoresize="true" style="height: 300px" />
    </el-card>

    <!-- 9 宫格 -->
    <el-card style="margin-top: 16px">
      <template #header>RFM 9 宫格 (R × F)</template>
      <div class="rfm-matrix">
        <div class="matrix-label axis-top">高 F (高消费频次) →</div>
        <div class="matrix-content">
          <div class="matrix-row" v-for="r in ['high', 'mid', 'low']" :key="r">
            <div class="matrix-label y-axis">{{ rLabel(r) }}</div>
            <div v-for="f in ['low', 'mid', 'high']" :key="r + f" class="matrix-cell" :class="'cell-' + getCellType(r, f)">
              <div class="cell-count">{{ matrix[`${r}_${f}`]?.count || 0 }}</div>
              <div class="cell-money">¥{{ formatNumber(matrix[`${r}_${f}`]?.monetary || 0) }}</div>
            </div>
          </div>
          <div class="axis-bottom">低 F (低消费频次) →</div>
        </div>
      </div>
    </el-card>

    <!-- 客户列表 -->
    <el-card style="margin-top: 16px">
      <template #header>客户列表 (按 RFM 评分排序)</template>
      <el-table :data="rows" stripe>
        <el-table-column prop="customer_name" label="客户" min-width="160" />
        <el-table-column prop="industry" label="行业" width="120" />
        <el-table-column prop="customer_level" label="类型" width="100" />
        <el-table-column label="R (近度)" align="center" width="80">
          <template #default="{ row }">
            <el-rate v-model="row.r_score" disabled :max="5" />
          </template>
        </el-table-column>
        <el-table-column label="F (频次)" align="center" width="80">
          <template #default="{ row }">
            <el-rate v-model="row.f_score" disabled :max="5" />
          </template>
        </el-table-column>
        <el-table-column label="M (金额)" align="center" width="80">
          <template #default="{ row }">
            <el-rate v-model="row.m_score" disabled :max="5" />
          </template>
        </el-table-column>
        <el-table-column prop="frequency" label="消费次数" align="right" width="100" />
        <el-table-column prop="monetary" label="总消费" align="right" :formatter="(r) => '¥' + Number(r.monetary).toLocaleString()" />
        <el-table-column prop="segment_label" label="段位">
          <template #default="{ row }">
            <el-tag :type="segmentType(row.segment_label)">{{ row.segment_label }}</el-tag>
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
import { BarChart, PieChart, HeatmapChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, LegendComponent, GridComponent, VisualMapComponent } from 'echarts/components'
import { Refresh, Download } from '@element-plus/icons-vue'
import { getCustomerRfm, getPdfUrl } from '@/api/analytics'
import { ElMessage } from 'element-plus'

use([CanvasRenderer, BarChart, PieChart, HeatmapChart, TitleComponent, TooltipComponent, LegendComponent, GridComponent, VisualMapComponent])

const segment = ref<string>('')
const rows = ref<Record<string, unknown>[]>([])
const matrix = ref<Record<string, unknown>>({})
const segments = ref<Record<string, unknown>[]>([])
const segmentOptions = computed(() => segments.value.map((s: Record<string, unknown>) => s.segment_label))

const segmentOption = computed(() => {
  const data = segments.value.map((s: Record<string, unknown>) => ({ name: s.segment_label, value: Number(s.cnt) }))
  return {
    tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
    legend: { bottom: 0, type: 'scroll' },
    series: [{ type: 'pie', radius: ['40%', '70%'], data }],
  }
})

function rLabel(r: string) {
  return { high: '高 R (近)', mid: '中 R', low: '低 R (远)' }[r] || r
}

function getCellType(r: string, f: string) {
  if (r === 'high' && f === 'high') return 'gold'
  if (r === 'high' && f === 'mid') return 'good'
  if (r === 'high' && f === 'low') return 'develop'
  if (r === 'mid' && f === 'high') return 'keep'
  if (r === 'mid' && f === 'mid') return 'normal'
  if (r === 'mid' && f === 'low') return 'normal'
  if (r === 'low' && f === 'high') return 'rescue'
  if (r === 'low' && f === 'mid') return 'potential'
  return 'dormant'
}

function segmentType(seg: string) {
  if (seg.includes('重要价值')) return 'success'
  if (seg.includes('发展')) return 'primary'
  if (seg.includes('保持')) return 'warning'
  if (seg.includes('挽留')) return 'danger'
  if (seg.includes('潜在')) return 'info'
  return ''
}

function formatNumber(n: number) {
  return (Number(n) || 0).toLocaleString('zh-CN', { maximumFractionDigits: 0 })
}

async function load() {
  try {
    const resp = await getCustomerRfm({ segment: segment.value || undefined, limit: 200 })
    const data = resp?.data ?? resp
    rows.value = data.rows || []
    matrix.value = data.matrix || {}
    segments.value = data.segments || []
  } catch (e: unknown) {
    ElMessage.error('加载客户 RFM 失败: ' + (e.message || '未知错误'))
  }
}

function exportPdf() { window.open(getPdfUrl('rfm', 'full'), '_blank') }
onMounted(load)
</script>

<style scoped>
.analytics-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.page-actions { display: flex; gap: 8px; }
.rfm-matrix { display: flex; padding: 20px; }
.matrix-label { font-size: 12px; color: #6b7280; }
.matrix-label.axis-top { text-align: center; margin-bottom: 8px; }
.axis-bottom { text-align: center; font-size: 12px; color: #6b7280; margin-top: 8px; }
.matrix-content { flex: 1; }
.matrix-row { display: flex; align-items: center; }
.matrix-label.y-axis { width: 80px; text-align: right; padding-right: 12px; }
.matrix-cell { flex: 1; height: 100px; margin: 4px; border-radius: 6px; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #fff; font-weight: 600; }
.cell-count { font-size: 28px; }
.cell-money { font-size: 12px; opacity: 0.9; margin-top: 4px; }
.cell-gold     { background: linear-gradient(135deg, #f59e0b, #d97706); }
.cell-good     { background: linear-gradient(135deg, #10b981, #059669); }
.cell-develop  { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.cell-keep     { background: linear-gradient(135deg, #6366f1, #4f46e5); }
.cell-rescue   { background: linear-gradient(135deg, #f97316, #ea580c); }
.cell-potential{ background: linear-gradient(135deg, #06b6d4, #0891b2); }
.cell-normal   { background: linear-gradient(135deg, #9ca3af, #6b7280); }
.cell-dormant  { background: linear-gradient(135deg, #d1d5db, #9ca3af); color: #374151; }
</style>
