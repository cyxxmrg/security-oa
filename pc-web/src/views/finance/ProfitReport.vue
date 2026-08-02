<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">📊 项目利润表</span>
      <div class="header-actions">
        <el-button :icon="Download" @click="handleExport">导出Excel</el-button>
        <el-button :icon="Refresh" @click="loadData">刷新</el-button>
      </div>
    </div>

    <el-row :gutter="16" class="kpi-row">
      <el-col :xs="8" :sm="8">
        <div class="kpi-card income">
          <div class="kpi-label">总进项（收入）</div>
          <div class="kpi-value">¥{{ fmt(summary.income) }}</div>
        </div>
      </el-col>
      <el-col :xs="8" :sm="8">
        <div class="kpi-card expense">
          <div class="kpi-label">总出项（成本）</div>
          <div class="kpi-value">¥{{ fmt(summary.expense) }}</div>
        </div>
      </el-col>
      <el-col :xs="8" :sm="8">
        <div class="kpi-card" :class="summary.profit >= 0 ? 'profit' : 'loss'">
          <div class="kpi-label">净利润</div>
          <div class="kpi-value">¥{{ fmt(summary.profit) }}</div>
        </div>
      </el-col>
    </el-row>

    <div class="filter-bar">
      <el-input v-model="filter.keyword" placeholder="搜索项目名称" clearable style="width:240px" @input="doFilter" />
      <span class="hint">共 {{ filtered.length }} 个项目</span>
    </div>

    <div class="content-card">
      <el-table :data="filtered" stripe border style="width:100%" v-loading="loading"
        @row-click="(r: ProfitRow) => { detailItem = r; showDetail = true }" row-class-name="clickable-row">
        <el-table-column type="index" label="#" width="50" align="center" />
        <el-table-column prop="project_name" label="项目名称" min-width="200" />
        <el-table-column label="进项（收入）" width="160" align="right">
          <template #default="{ row }"><span style="color:#1D9E75;font-weight:600">¥{{ fmt(row.income) }}</span></template>
        </el-table-column>
        <el-table-column label="出项（成本）" width="160" align="right">
          <template #default="{ row }"><span style="color:#A32D2D;font-weight:600">¥{{ fmt(row.expense) }}</span></template>
        </el-table-column>
        <el-table-column label="利润" width="160" align="right">
          <template #default="{ row }">
            <span :style="{ color: row.profit >= 0 ? '#1D9E75' : '#A32D2D', fontWeight: 700 }">
              ¥{{ fmt(row.profit) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="利润率" width="120" align="right">
          <template #default="{ row }">
            <span v-if="row.income > 0" :style="{ color: row.profit >= 0 ? '#1D9E75' : '#A32D2D' }">
              {{ (row.profit / row.income * 100).toFixed(1) }}%
            </span>
            <span v-else class="hint">-</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="80" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click.stop="openDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 项目详情 dialog -->
    <el-dialog v-model="showDetail" :title="`${detailItem?.project_name || ''} 利润明细`" width="900px" :close-on-click-modal="false">
      <template v-if="detailItem">
        <div class="detail-summary">
          <div class="ds-item income">进项 ¥{{ fmt(detailItem.income) }}</div>
          <div class="ds-arrow">−</div>
          <div class="ds-item expense">出项 ¥{{ fmt(detailItem.expense) }}</div>
          <div class="ds-arrow">=</div>
          <div class="ds-item" :class="detailItem.profit >= 0 ? 'profit' : 'loss'">
            利润 ¥{{ fmt(detailItem.profit) }}
          </div>
        </div>

        <el-descriptions :column="2" border class="detail-table">
          <el-descriptions-item v-for="(val, key) in detailItem._detail" :key="key" :label="key" :span="1">
            <span :style="{ fontWeight: 600, color: key.includes('收入') || key.includes('应收') ? '#1D9E75' : '#A32D2D' }">
              ¥{{ fmt(val) }}
            </span>
          </el-descriptions-item>
        </el-descriptions>
      </template>
      <template #footer><el-button @click="showDetail = false">关闭</el-button></template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, toRaw } from 'vue'
import { ElMessage } from 'element-plus'
import { Download, Refresh } from '@element-plus/icons-vue'
import { get } from '@/utils/request'

interface DetailMap { [category: string]: number }
interface ProfitRow {
  project_id: number; project_name: string
  income: number; expense: number; profit: number
  _detail: DetailMap
}

const loading = ref(false)
const allRows = ref<ProfitRow[]>([])
const summary = reactive({ income: 0, expense: 0, profit: 0 })
const filter = reactive({ keyword: '' })
const showDetail = ref(false)
const detailItem = ref<ProfitRow | null>(null)

const filtered = computed(() => {
  if (!filter.keyword) return allRows.value
  const kw = filter.keyword.toLowerCase()
  return allRows.value.filter(r => r.project_name.toLowerCase().includes(kw))
})

function fmt(n: number | string | undefined | null): string {
  if (n == null) return '0.00'
  return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

function doFilter() { /* computed handles it */ }

function openDetail(row: ProfitRow) {
  detailItem.value = row
  showDetail.value = true
}

async function loadData() {
  loading.value = true
  try {
    const r = await get('/finance/project-profit')
    const d = (r as { data?: unknown })?.data ?? r
    const items = (d as { items?: ProfitRow[] })?.items ?? []
    const total = (d as { total?: { income: number; expense: number; profit: number } })?.total
    allRows.value = items as ProfitRow[]
    if (total) Object.assign(summary, total)
  } catch {
    allRows.value = []
    Object.assign(summary, { income: 0, expense: 0, profit: 0 })
  } finally { loading.value = false }
}

function handleExport() {
  const rows = toRaw(allRows.value)
  if (!rows.length) { ElMessage.warning('暂无数据'); return }
  // Simple CSV export
  let csv = '\uFEFF项目名称,进项(收入),出项(成本),利润,利润率%\n'
  rows.forEach(r => {
    const rate = r.income > 0 ? (r.profit / r.income * 100).toFixed(1) : '0'
    csv += `${r.project_name},${r.income},${r.expense},${r.profit},${rate}\n`
    // Add detail rows
    if (r._detail) {
      Object.entries(r._detail).forEach(([k, v]) => {
        csv += `  -${k},${v},,,,\n`
      })
    }
  })
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = `利润表_${new Date().toISOString().slice(0, 10)}.csv`
  a.click(); URL.revokeObjectURL(url)
  ElMessage.success('已导出')
}

onMounted(loadData)
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: linear-gradient(180deg, #f0f4fa 0%, #f5f7fa 100%); min-height: 100vh; }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 12px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.kpi-row { margin-bottom: 16px; }
.kpi-card { background: #fff; border-radius: 8px; padding: 18px; border-left: 4px solid #909399; }
.kpi-card.income { border-left-color: #1D9E75; }
.kpi-card.expense { border-left-color: #A32D2D; }
.kpi-card.profit { border-left-color: #0C447C; }
.kpi-card.loss { border-left-color: #A32D2D; }
.kpi-label { font-size: 13px; color: #909399; margin-bottom: 6px; }
.kpi-value { font-size: 22px; font-weight: 700; color: #303133; }
.filter-bar { display: flex; gap: 12px; align-items: center; background: #fff; padding: 14px 20px; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.hint { font-size: 13px; color: #909399; }
.content-card { background: #fff; padding: 16px 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
:deep(.clickable-row) { cursor: pointer; }
:deep(.clickable-row:hover) { background-color: #ecf5ff !important; }
.detail-summary { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 16px; background: #f5f7fa; border-radius: 8px; }
.ds-item { font-size: 16px; font-weight: 700; padding: 8px 16px; border-radius: 6px; }
.ds-item.income { background: #e8f5e9; color: #1D9E75; }
.ds-item.expense { background: #fbe9e7; color: #A32D2D; }
.ds-item.profit { background: #e3f2fd; color: #0C447C; }
.ds-item.loss { background: #fbe9e7; color: #A32D2D; }
.ds-arrow { font-size: 22px; color: #909399; font-weight: 700; }
.detail-table { :deep(.el-descriptions__label) { background: #f5f7fa; font-weight: 600; } }
</style>