<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="预算详情"
    width="1100px"
    :close-on-click-modal="false"
    @open="loadDetail"
    @close="handleClose"
  >
    <div v-loading="loading">
      <template v-if="detail">
        <!-- 基础信息 -->
        <el-descriptions title="基础信息" :column="3" border size="default" style="margin-bottom: 16px">
          <el-descriptions-item label="预算编号">{{ detail.code || '-' }}</el-descriptions-item>
          <el-descriptions-item label="版本">v{{ detail.version || 1 }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTagType(detail.status)" effect="plain" size="small">
              {{ statusLabel(detail.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="项目">
            {{ detail.project?.name || detail.project?.code || detail.project_id || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="总预算">
            <span style="color:#0C447C;font-weight:600">¥ {{ formatMoney(detail.total_amount) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="实际成本">
            <span>¥ {{ formatMoney(detail.actual_amount) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="使用率" :span="3">
            <el-progress
              :percentage="ratePercent(detail.usage_rate)"
              :color="getRateColor(detail.usage_rate)"
              :stroke-width="12"
              :format="(p: number) => `${p}%`"
              style="width: 300px; max-width: 100%"
            />
          </el-descriptions-item>
          <el-descriptions-item label="创建人">{{ detail.creator?.name || detail.created_by || '-' }}</el-descriptions-item>
          <el-descriptions-item label="创建时间" :span="2">{{ detail.created_at || '-' }}</el-descriptions-item>
          <el-descriptions-item v-if="detail.remark" label="备注" :span="3">{{ detail.remark }}</el-descriptions-item>
        </el-descriptions>

        <!-- 4 大类对比 -->
        <el-card shadow="never" header="4 大类对比" style="margin-bottom: 16px" :body-style="{ padding: 0 }">
          <el-table :data="categoryRows" border size="small" :show-header="true">
            <el-table-column prop="label" label="类别" width="120" align="center">
              <template #default="{ row }">
                <span :style="{ color: row.color, fontWeight: 600 }">{{ row.label }}</span>
              </template>
            </el-table-column>
            <el-table-column label="预算" align="right">
              <template #default="{ row }">¥ {{ formatMoney(row.budget) }}</template>
            </el-table-column>
            <el-table-column label="实际" align="right">
              <template #default="{ row }">¥ {{ formatMoney(row.actual) }}</template>
            </el-table-column>
            <el-table-column label="余额" align="right">
              <template #default="{ row }">
                <span :style="{ color: row.balance < 0 ? '#f56c6c' : '#67c23a' }">¥ {{ formatMoney(row.balance) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="使用率" width="220" align="center">
              <template #default="{ row }">
                <el-progress
                  :percentage="ratePercent(row.usage_rate)"
                  :color="getRateColor(row.usage_rate)"
                  :stroke-width="8"
                  :format="(p: number) => `${p}%`"
                  style="width: 160px"
                />
              </template>
            </el-table-column>
          </el-table>
        </el-card>

        <!-- 明细列表 -->
        <el-card v-if="detail.items && detail.items.length" shadow="never" header="预算明细" style="margin-bottom: 16px" :body-style="{ padding: 0 }">
          <el-table :data="detail.items" border size="small">
            <el-table-column label="类别" width="100" align="center">
              <template #default="{ row }">{{ categoryLabel(row.category) }}</template>
            </el-table-column>
            <el-table-column prop="name" label="名称" min-width="160" show-overflow-tooltip />
            <el-table-column prop="spec" label="规格" width="100" />
            <el-table-column prop="unit" label="单位" width="70" align="center" />
            <el-table-column prop="qty" label="数量" width="80" align="right" />
            <el-table-column label="单价" width="110" align="right">
              <template #default="{ row }">¥ {{ formatMoney(row.unit_price) }}</template>
            </el-table-column>
            <el-table-column label="金额" width="130" align="right">
              <template #default="{ row }">
                <span style="color:#0C447C;font-weight:600">¥ {{ formatMoney(row.amount) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
          </el-table>
        </el-card>

        <!-- 实际成本流水 -->
        <el-card shadow="never" :body-style="{ padding: 0 }">
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <span style="font-weight:600">实际成本流水（前 10 条）</span>
              <el-tag v-if="!costFlows || costFlows.length === 0" type="info" size="small">暂无</el-tag>
            </div>
          </template>
          <el-table v-if="costFlows && costFlows.length" :data="costFlows" border size="small">
            <el-table-column prop="date" label="日期" width="160" align="center" />
            <el-table-column label="来源" width="140" align="center">
              <template #default="{ row }">
                <el-tag size="small" effect="plain">{{ sourceLabel(row.source) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="关联单据" min-width="180" show-overflow-tooltip>
              <template #default="{ row }">{{ row.ref_code || row.ref_id || '-' }}</template>
            </el-table-column>
            <el-table-column label="摘要" min-width="200" show-overflow-tooltip>
              <template #default="{ row }">{{ row.summary || row.remark || '-' }}</template>
            </el-table-column>
            <el-table-column label="金额" width="130" align="right">
              <template #default="{ row }">
                <span style="color:#A32D2D;font-weight:600">¥ {{ formatMoney(row.amount) }}</span>
              </template>
            </el-table-column>
          </el-table>
          <div v-else class="empty-block">暂无实际成本流水</div>
        </el-card>
      </template>
      <el-empty v-else-if="!loading" description="未找到预算数据" />
    </div>

    <template #footer>
      <el-button @click="emit('update:visible', false)">关闭</el-button>
      <el-button
        v-if="detail && detail.status === 'approved'"
        type="primary"
        :icon="Refresh"
        @click="handleRevise"
      >修订</el-button>
      <el-button :icon="Printer" @click="handlePrint">打印</el-button>
      <el-button type="success" :icon="Download" @click="handleExport">导出 Excel</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Printer, Download } from '@element-plus/icons-vue'
import { construction } from '@/api/construction'
import { exportExcelLike, printTable } from '@/utils/exporter'
import { unwrapItem } from '@/utils/response'
import { Budget, CostFlow, BudgetItem, CategoryAmount, TagType } from '../types'

interface CategoryRow {
  key: string
  label: string
  color: string
  budget: number
  actual: number
  balance: number
  usage_rate: number
}

const props = defineProps<{
  visible: boolean
  budgetId?: number
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'revise', budget: Budget): void
  (e: 'print', budget: Budget): void
  (e: 'export', budget: Budget): void
}>()

const loading = ref(false)
const detail = ref<Budget | null>(null)
const costFlows = ref<CostFlow[]>([])

const STATUS_MAP: Record<string, { label: string; tagType: TagType }> = {
  draft:    { label: '草稿',   tagType: 'info' },
  approved: { label: '已审批', tagType: 'success' },
  revised:  { label: '已修订', tagType: 'warning' },
  voided:   { label: '已作废', tagType: 'danger' },
}
const statusLabel = (s?: string) => STATUS_MAP[s || '']?.label || s || '-'
const statusTagType = (s?: string): TagType => STATUS_MAP[s || '']?.tagType || 'info'

const CATEGORY_MAP: Record<string, { label: string; color: string }> = {
  material:  { label: '材料费', color: '#0C447C' },
  labor:     { label: '人工费', color: '#1D9E75' },
  outsource: { label: '外包费', color: '#E6A23C' },
  other:     { label: '其他费', color: '#909399' },
}
const categoryLabel = (k: string) => CATEGORY_MAP[k]?.label || k || '-'

const SOURCE_MAP: Record<string, string> = {
  purchase_in:       '采购入库',
  stock_out:         '领料出库',
  expense_labor:     '报销-人工',
  expense_outsource: '报销-外包',
  expense_other:     '报销-其他',
}
const sourceLabel = (k: string) => SOURCE_MAP[k] || k || '-'

const formatMoney = (n?: number | string | null) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const ratePercent = (rate?: number | string | null) => {
  const r = Number(rate || 0)
  return Math.min(Math.max(Math.round(r * 100), 0), 100)
}
const getRateColor = (rate?: number | string | null): string => {
  const r = Number(rate || 0)
  if (r >= 1.0) return '#f56c6c'
  if (r >= 0.9) return '#e6a23c'
  return '#67c23a'
}

const categoryRows = ref<CategoryRow[]>([])
const buildCategoryRows = (d: Budget) => {
  const cats = (d?.categories ?? {
    material:  d?.material,
    labor:     d?.labor,
    outsource: d?.outsource,
    other:     d?.other,
  }) as Record<string, CategoryAmount>
  categoryRows.value = Object.keys(CATEGORY_MAP).map(k => {
    const c = cats[k] || {}
    const budget    = Number(c.budget    || c.budget_amount || 0)
    const actual    = Number(c.actual    || c.actual_amount || 0)
    const balance   = Number(c.balance   ?? (budget - actual))
    const usage_rate = Number(c.usage_rate ?? (budget > 0 ? actual / budget : 0))
    return { key: k, label: CATEGORY_MAP[k].label, color: CATEGORY_MAP[k].color, budget, actual, balance, usage_rate }
  })
}

const loadDetail = async () => {
  if (!props.budgetId) {
    detail.value = null
    costFlows.value = []
    categoryRows.value = []
    return
  }
  loading.value = true
  try {
    const res = await construction.getBudget(props.budgetId)
    const d = unwrapItem<Budget>(res)
    detail.value = d || null
    if (d) buildCategoryRows(d)
    // 实际成本流水 — 后端 summary 接口附带，或在 detail 中以 cost_flows / actual_flows 形式返回
    const flows = d?.cost_flows || d?.actual_flows || d?.flows || []
    costFlows.value = Array.isArray(flows) ? flows.slice(0, 10) : []
  } catch {
    detail.value = null
    costFlows.value = []
    categoryRows.value = []
  } finally {
    loading.value = false
  }
}

watch(() => props.budgetId, () => { if (props.visible) loadDetail() })
watch(() => props.visible, (v) => { if (v) loadDetail() })

const handleClose = () => {
  detail.value = null
  costFlows.value = []
  categoryRows.value = []
}

const handleRevise = () => {
  if (!detail.value) return
  emit('revise', detail.value)
}

const handlePrint = () => {
  if (!detail.value) return
  emit('print', detail.value)
  const d = detail.value
  const headers = ['科目', '预算金额', '实际金额', '差额', '执行率']
  const items = (d.items || []) as Array<BudgetItem & { budget?: number | string; actual?: number | string }>
  const rows = items.map((it) => [
    it.name || '-',
    Number(it.budget || 0).toFixed(2),
    Number(it.actual || 0).toFixed(2),
    (Number(it.budget || 0) - Number(it.actual || 0)).toFixed(2),
    it.budget ? ((Number(it.actual || 0) / Number(it.budget)) * 100).toFixed(1) + '%' : '-',
  ])
  if (rows.length === 0) rows.push(['暂无明细', '-', '-', '-', '-'])
  printTable(`项目预算 - ${d.code || d.id || ''}`, headers, rows, { orientation: 'landscape' })
}

const handleExport = () => {
  if (!detail.value) return
  emit('export', detail.value)
  const d = detail.value
  const headers = ['科目', '预算金额', '实际金额', '差额', '执行率']
  const items = (d.items || []) as Array<BudgetItem & { budget?: number | string; actual?: number | string }>
  const rows = items.map((it) => [
    it.name || '-',
    Number(it.budget || 0).toFixed(2),
    Number(it.actual || 0).toFixed(2),
    (Number(it.budget || 0) - Number(it.actual || 0)).toFixed(2),
    it.budget ? ((Number(it.actual || 0) / Number(it.budget)) * 100).toFixed(1) + '%' : '-',
  ])
  if (rows.length === 0) rows.push(['暂无明细', '-', '-', '-', '-'])
  exportExcelLike(headers, rows, `预算明细_${d.code || d.id}`, { title: `项目预算明细 - ${d.code || d.id}` })
}
</script>

<style lang="scss" scoped>
.empty-block { padding: 24px; text-align: center; color: #909399; }
</style>
