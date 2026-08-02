<template>
  <div class="analytics-page">
    <div class="page-header">
      <h2>📋 项目健康度</h2>
      <div class="page-actions">
        <el-select v-model="color" placeholder="状态" clearable @change="load" style="width: 140px">
          <el-option label="🟢 健康" value="green" />
          <el-option label="🟡 关注" value="yellow" />
          <el-option label="🔴 告警" value="red" />
        </el-select>
        <el-button :icon="Refresh" @click="load">刷新</el-button>
        <el-button type="primary" :icon="Download" @click="exportPdf">导出 PDF</el-button>
      </div>
    </div>

    <!-- KPI 概览 -->
    <div class="kpi-grid">
      <div class="kpi-card" :class="{ 'border-green': true }">
        <div class="kpi-label">🟢 健康 (≥80)</div>
        <div class="kpi-value green">{{ stats.green || 0 }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">🟡 关注 (60-79)</div>
        <div class="kpi-value yellow">{{ stats.yellow || 0 }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">🔴 告警 (<60)</div>
        <div class="kpi-value red">{{ stats.red || 0 }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">合计</div>
        <div class="kpi-value">{{ stats.total || 0 }}</div>
      </div>
    </div>

    <!-- 健康度分布饼图 -->
    <el-row :gutter="16">
      <el-col :span="8">
        <el-card>
          <template #header>健康度分布</template>
          <v-chart v-if="pieOption" :option="pieOption" :autoresize="true" style="height: 320px" />
        </el-card>
      </el-col>
      <el-col :span="16">
        <el-card>
          <template #header>健康度评分 (Top 30)</template>
          <v-chart v-if="barOption" :option="barOption" :autoresize="true" style="height: 320px" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 详细表格 -->
    <el-card style="margin-top: 16px">
      <template #header>项目健康度详细 ({{ rows.length }})</template>
      <el-table :data="rows" stripe>
        <el-table-column label="项目" min-width="200">
          <template #default="{ row }">
            <strong>[{{ row.project_code }}]</strong> {{ row.project_name }}
          </template>
        </el-table-column>
        <el-table-column prop="project_type" label="类型" width="100" />
        <el-table-column prop="stage" label="阶段" width="100" />
        <el-table-column prop="manager_name" label="负责人" width="100" />
        <el-table-column label="进度" align="center" width="80">
          <template #default="{ row }">
            <el-progress :percentage="row.score_progress" :stroke-width="8" />
          </template>
        </el-table-column>
        <el-table-column label="成本" align="center" width="80">
          <template #default="{ row }">
            <el-progress :percentage="row.score_cost" status="success" :stroke-width="8" />
          </template>
        </el-table-column>
        <el-table-column label="质量" align="center" width="80">
          <template #default="{ row }">
            <el-progress :percentage="row.score_quality" status="warning" :stroke-width="8" />
          </template>
        </el-table-column>
        <el-table-column label="排班" align="center" width="80">
          <template #default="{ row }">
            <el-progress :percentage="row.score_schedule" :stroke-width="8" />
          </template>
        </el-table-column>
        <el-table-column label="综合" align="center" width="100" fixed="right">
          <template #default="{ row }">
            <el-tag :type="row.health_color === 'green' ? 'success' : row.health_color === 'yellow' ? 'warning' : 'danger'" size="large">
              {{ row.health_score }} / 100
            </el-tag>
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
import { BarChart, PieChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, LegendComponent, GridComponent } from 'echarts/components'
import { Refresh, Download } from '@element-plus/icons-vue'
import { getProjectHealth, getPdfUrl } from '@/api/analytics'
import { ElMessage } from 'element-plus'

use([CanvasRenderer, BarChart, PieChart, TitleComponent, TooltipComponent, LegendComponent, GridComponent])

const color = ref<string>('')
const rows = ref<Record<string, unknown>[]>([])
const stats = ref<Record<string, unknown>>({})

const pieOption = computed(() => ({
  tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
  legend: { bottom: 0 },
  series: [{
    type: 'pie', radius: ['50%', '70%'],
    data: [
      { name: '🟢 健康', value: stats.value.green || 0, itemStyle: { color: '#10b981' } },
      { name: '🟡 关注', value: stats.value.yellow || 0, itemStyle: { color: '#f59e0b' } },
      { name: '🔴 告警', value: stats.value.red || 0, itemStyle: { color: '#ef4444' } },
    ],
  }],
}))

const barOption = computed(() => ({
  tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
  grid: { left: 100, right: 30, top: 20, bottom: 30 },
  xAxis: { type: 'value', max: 100 },
  yAxis: { type: 'category', data: rows.value.slice(0, 30).map(r => r.project_code).reverse() },
  series: [{
    type: 'bar',
    data: rows.value.slice(0, 30).map(r => ({
      value: Number(r.health_score),
      itemStyle: { color: r.health_color === 'green' ? '#10b981' : r.health_color === 'yellow' ? '#f59e0b' : '#ef4444' },
    })).reverse(),
    label: { show: true, position: 'right', formatter: '{c}' },
  }],
}))

async function load() {
  try {
    const resp = await getProjectHealth({ color: color.value || undefined, limit: 50 })
    const data = resp?.data ?? resp
    rows.value = data.rows || []
    stats.value = data.stats || {}
  } catch (e: unknown) {
    ElMessage.error('加载项目数据失败: ' + (e.message || '未知错误'))
  }
}

function exportPdf() { window.open(getPdfUrl('projects', 'full'), '_blank') }
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
.kpi-value.yellow { color: #d97706; }
.kpi-value.red { color: #dc2626; }
</style>
