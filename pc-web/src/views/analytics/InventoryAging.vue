<template>
  <div class="analytics-page">
    <div class="page-header">
      <h2>📦 库存周转</h2>
      <div class="page-actions">
        <el-select v-model="status" placeholder="状态" clearable @change="load" style="width: 160px">
          <el-option label="🔴 缺货" value="shortage" />
          <el-option label="🔴 断货" value="stockout" />
          <el-option label="🟡 呆滞 90 天+" value="stagnant" />
          <el-option label="🟡 超储" value="overstock" />
          <el-option label="🟢 正常" value="normal" />
        </el-select>
        <el-button :icon="Refresh" @click="load">刷新</el-button>
        <el-button type="primary" :icon="Download" @click="exportPdf">导出 PDF</el-button>
      </div>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="kpi-card red-card">
        <div class="kpi-label">🔴 缺货 SKU</div>
        <div class="kpi-value red">{{ stats.shortage || 0 }}</div>
      </div>
      <div class="kpi-card red-card">
        <div class="kpi-label">🔴 断货 SKU</div>
        <div class="kpi-value red">{{ stats.stockout || 0 }}</div>
      </div>
      <div class="kpi-card yellow-card">
        <div class="kpi-label">🟡 呆滞 SKU</div>
        <div class="kpi-value yellow">{{ stats.stagnant || 0 }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">库存总价值</div>
        <div class="kpi-value">¥{{ formatNumber(stats.total_value) }}</div>
      </div>
    </div>

    <!-- 状态分布 -->
    <el-card>
      <template #header>库存状态分布</template>
      <v-chart v-if="statusOption" :option="statusOption" :autoresize="true" style="height: 280px" />
    </el-card>

    <!-- 价值 Top 30 表格 -->
    <el-card style="margin-top: 16px">
      <template #header>重点关注物料 (按价值排序 Top 30)</template>
      <el-table :data="rows" stripe>
        <el-table-column label="物料" min-width="220">
          <template #default="{ row }">
            <strong>[{{ row.item_code }}]</strong> {{ row.item_name }}
          </template>
        </el-table-column>
        <el-table-column prop="category" label="分类" width="100" />
        <el-table-column label="当前库存" align="right" width="110">
          <template #default="{ row }">{{ row.current_stock }} {{ row.unit }}</template>
        </el-table-column>
        <el-table-column prop="safety_stock" label="安全库存" align="right" width="100" />
        <el-table-column prop="outbound_90d" label="90天出库" align="right" width="100" />
        <el-table-column label="库龄" align="center" width="100">
          <template #default="{ row }">
            <span :class="row.aging_days > 90 ? 'red' : row.aging_days > 30 ? 'yellow' : 'green'">
              {{ row.aging_days === 999 ? '从未出' : row.aging_days + '天' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="stock_value" label="库存价值" align="right" :formatter="(r) => '¥' + formatNumber(r.stock_value)" />
        <el-table-column label="状态" align="center" width="100" fixed="right">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
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
import { getInventoryAging, getPdfUrl } from '@/api/analytics'
import { ElMessage } from 'element-plus'

use([CanvasRenderer, BarChart, PieChart, TitleComponent, TooltipComponent, LegendComponent, GridComponent])

const status = ref<string>('')
const rows = ref<Record<string, unknown>[]>([])
const stats = ref<Record<string, unknown>>({})

const statusOption = computed(() => ({
  tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
  grid: { left: 60, right: 30, top: 30, bottom: 30 },
  xAxis: { type: 'category', data: ['缺货', '断货', '呆滞', '超储', '正常'] },
  yAxis: { type: 'value' },
  series: [{
    type: 'bar', barWidth: 40,
    data: [
      { value: stats.value.shortage || 0, itemStyle: { color: '#ef4444' } },
      { value: stats.value.stockout || 0, itemStyle: { color: '#dc2626' } },
      { value: stats.value.stagnant || 0, itemStyle: { color: '#f59e0b' } },
      { value: stats.value.overstock || 0, itemStyle: { color: '#eab308' } },
      { value: stats.value.normal || 0, itemStyle: { color: '#10b981' } },
    ],
    label: { show: true, position: 'top' },
  }],
}))

function statusLabel(s: string) {
  return { shortage: '缺货', stockout: '断货', stagnant: '呆滞', overstock: '超储', normal: '正常' }[s] || s
}
function statusType(s: string) {
  if (s === 'shortage' || s === 'stockout') return 'danger'
  if (s === 'stagnant' || s === 'overstock') return 'warning'
  return 'success'
}
function formatNumber(n: number) {
  return (Number(n) || 0).toLocaleString('zh-CN', { maximumFractionDigits: 0 })
}

async function load() {
  try {
    const resp = await getInventoryAging({ status: status.value || undefined })
    const data = resp?.data ?? resp
    rows.value = data.rows || []
    stats.value = data.stats || {}
  } catch (e: unknown) {
    ElMessage.error('加载库存数据失败: ' + (e.message || '未知错误'))
  }
}

function exportPdf() { window.open(getPdfUrl('inventory', 'full'), '_blank') }
onMounted(load)
</script>

<style scoped>
.analytics-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.page-actions { display: flex; gap: 8px; }
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.kpi-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px; }
.kpi-card.red-card { border-left: 4px solid #ef4444; }
.kpi-card.yellow-card { border-left: 4px solid #f59e0b; }
.kpi-label { font-size: 12px; color: #6b7280; }
.kpi-value { font-size: 22px; font-weight: 700; color: #1e3a8a; margin: 4px 0; }
.kpi-value.red { color: #dc2626; }
.kpi-value.yellow { color: #d97706; }
.red { color: #dc2626; font-weight: 600; }
.yellow { color: #d97706; font-weight: 600; }
.green { color: #059669; }
</style>
