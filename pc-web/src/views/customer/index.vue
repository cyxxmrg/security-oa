<template>
  <div class="customer-page">
    <CustomerStatsCards :stats="stats" :icon-map="iconMap" />

    <el-card shadow="never" class="table-card">
      <div class="card-header">
        <ScopeToggle @change="loadList" />
      </div>
      <CustomerSearchBar
        :industries="industries"
        :categories="categories"
        @search="onSearch"
        @reset="onReset"
        @add="handleAdd"
        @import="handleImport"
        @export="handleExport"
      />

      <!-- 批量操作工具条（v0.3.14 C2） -->
      <div v-if="selected.length" class="batch-bar">
        <div class="bb-info">
          <el-icon><InfoFilled /></el-icon>
          已选 <b>{{ selected.length }}</b> 个客户
        </div>
        <div class="bb-actions">
          <el-button :icon="Connection" size="small" plain @click="handleBatchAddFollow">批量添加跟进</el-button>
          <el-button :icon="Delete" size="small" plain type="danger" @click="handleBatchDelete">批量删除</el-button>
          <el-button size="small" @click="selected = []">取消</el-button>
        </div>
      </div>

      <CustomerTable
        :data="list"
        :loading="loading"
        :selection="selected"
        @view="goDetail"
        @edit="handleEdit"
        @delete="handleDelete"
        @follow="handleFollow"
        @selection-change="(rows: Customer[]) => selected = rows"
      />

      <el-pagination
        class="pager"
        :current-page="page"
        :page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @current-change="loadList"
        @size-change="(s: number) => { pageSize = s; page = 1; loadList() }"
      />
    </el-card>

    <CustomerFormDialog
      v-model:visible="showFormDialog"
      :mode="formMode"
      :submitting="submitting"
      :industries="industries"
      :model-value="formData"
      @submit="handleSave"
    />

    <FollowDialog
      v-model:visible="showFollowDialog"
      :target-name="followTarget?.name"
      :submitting="submitting"
      @submit="submitFollow"
    />

    <ImportDialog
      v-model:visible="showImportDialog"
      :importing="importing"
      :result="importResult"
      @confirm="handleImportConfirm"
      @download-template="downloadTemplate"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * Customer 列表页 — v0.3.14 C2 拆分版
 *
 * 子组件:
 *  - CustomerStatsCards.vue  4 张统计卡
 *  - CustomerSearchBar.vue   筛选条
 *  - CustomerTable.vue       客户表格（含健康度 chip）
 *  - CustomerFormDialog.vue  新增/编辑 dialog
 *  - FollowDialog.vue        跟进 dialog
 *  - ImportDialog.vue        批量导入 dialog
 */
import { ref, reactive, onMounted, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  OfficeBuilding, Money, User, TrendCharts, Connection, Delete, InfoFilled,
} from '@element-plus/icons-vue'
import { get, post, put, del } from '@/utils/request'
import { exportExcelLike, exportCsv } from '@/utils/exporter'
import ScopeToggle from '@/components/ScopeToggle.vue'
import CustomerStatsCards from './components/CustomerStatsCards.vue'
import CustomerSearchBar from './components/CustomerSearchBar.vue'
import CustomerTable, { type Customer } from './components/CustomerTable.vue'
import CustomerFormDialog from './components/CustomerFormDialog.vue'
import FollowDialog from './components/FollowDialog.vue'
import ImportDialog from './components/ImportDialog.vue'

const router = useRouter()

// icons (markRaw 跳过 reactive proxy)
const iconMap = {
  OfficeBuilding: markRaw(OfficeBuilding),
  User: markRaw(User),
  Money: markRaw(Money),
  TrendCharts: markRaw(TrendCharts),
}

// 状态
const loading = ref(false)
const submitting = ref(false)
const importing = ref(false)
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)

const list = ref<Customer[]>([])
const stats = ref({ total: 0, vip: 0, project_total: 0, new_this_month: 0 })
const selected = ref<Customer[]>([])

const industries = ref<string[]>([])
const categories = ref<string[]>(['普通', 'VIP', '潜在'])

const colors = ['#0C447C', '#1D9E75', '#BA7517', '#534AB7', '#A32D2D']
const avatarColor = (id: number) => colors[id % colors.length]

// === 数据加载 ===
async function loadList() {
  loading.value = true
  try {
    const params = { page: page.value, per_page: pageSize.value }
    const data = await get('/customers', params)
    // V0.6.3: res = {code, data: paginator}
    const d = data?.data ?? {}
    let rows: Customer[] = Array.isArray(d?.data) ? d.data : []
    rows = rows.map((c: Customer) => ({ ...c, avatarColor: avatarColor(c.id) }))

    // 健康度兜底
    const needFallback = rows.length > 0 && rows.every((r) => r.health_score == null)
    if (needFallback) {
      try {
        const hdata = await get('/customers/health')
        const dd = hdata?.data ?? {}
        const healthList = (Array.isArray(dd?.list) ? dd.list : []) as Array<{
          customer_id?: number
          id?: number
          health_score?: number | null
          health_level?: string | null
          score_breakdown?: Record<string, number> | null
        }>
        const healthMap = new Map<number, {
          health_score?: number | null
          health_level?: string | null
          score_breakdown?: Record<string, number> | null
        }>()
        for (const h of healthList) {
          const hid = h.customer_id ?? h.id
          if (typeof hid === 'number') healthMap.set(hid, h)
        }
        rows = rows.map((c): Customer => {
          const h = healthMap.get(c.id)
          return h
            ? {
                ...c,
                health_score: h.health_score ?? undefined,
                health_level: h.health_level ?? undefined,
                score_breakdown: h.score_breakdown ?? undefined,
              }
            : c
        })
      } catch { /* silent */ }
    }

    list.value = rows
    total.value = (d?.total ?? d?.meta?.total ?? 0)
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    const data = await get('/customers/stats', { simple: 1 })
    // V0.6.3: res = {code, data: <stats>}
    const d = data?.data ?? data ?? {}
    stats.value = {
      total:          d.total          ?? list.value.length,
      vip:            d.vip            ?? list.value.filter((c) => c.category === 'vip').length,
      project_total:  d.project_total  ?? list.value.reduce((s, c) => s + (c.project_count || 0), 0),
      new_this_month: d.new_this_month ?? 0,
    }
  } catch {
    stats.value = {
      total:          list.value.length,
      vip:            list.value.filter((c) => c.category === 'vip').length,
      project_total:  list.value.reduce((s, c) => s + (c.project_count || 0), 0),
      new_this_month: 0,
    }
  }
}

async function loadIndustries() {
  try {
    const data = await get('/customers/industries')
    // V0.6.3: res = {code, data: [array]}
    const d = data?.data ?? data
    industries.value = Array.isArray(d) ? d : []
  } catch {
    industries.value = ['教育', '医疗', '金融', '地产', '互联网', '制造业', '零售', '政府']
  }
}

onMounted(async () => {
  await Promise.all([loadList(), loadIndustries()])
  loadStats()
})

// === 搜索 ===
const searchState = ref({ keyword: '', industry: '', category: '' })

const onSearch = (form: { keyword: string; industry: string; category: string }) => {
  searchState.value = { ...form }
  page.value = 1
  loadList()
}

const onReset = () => {
  searchState.value = { keyword: '', industry: '', category: '' }
  page.value = 1
  loadList()
}

// === 新增/编辑 ===
const showFormDialog = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const formData = reactive({
  id: 0, name: '', industry: '', contact: '', phone: '', category: 'normal', tags: [] as string[],
})

const handleAdd = () => {
  formMode.value = 'create'
  Object.assign(formData, { id: 0, name: '', industry: '', contact: '', phone: '', category: 'normal', tags: [] })
  showFormDialog.value = true
}

const handleEdit = (row: Customer) => {
  formMode.value = 'edit'
  Object.assign(formData, {
    id: row.id, name: row.name, industry: row.industry, contact: row.contact,
    phone: row.phone, category: row.category, tags: [...(row.tags || [])],
  })
  showFormDialog.value = true
}

async function handleSave(payload: typeof formData) {
  submitting.value = true
  try {
    const data = {
      name: payload.name, industry: payload.industry, category: payload.category,
      tags: payload.tags, contact: payload.contact, phone: payload.phone,
    }
    if (formMode.value === 'create') {
      await post('/customers', data)
      ElMessage.success('客户新增成功')
    } else {
      await put(`/customers/${payload.id}`, data)
      ElMessage.success('客户信息已更新')
    }
    showFormDialog.value = false
    page.value = 1
    await loadList()
    loadStats()
  } finally {
    submitting.value = false
  }
}

// === 跟进 ===
const showFollowDialog = ref(false)
const followTarget = ref<Customer | null>(null)

const handleFollow = (row: Customer) => {
  followTarget.value = row
  showFollowDialog.value = true
}

async function submitFollow(payload: {
  type: string; content: string; next_follow_up_date: string | null; next_follow_up_note: string
}) {
  if (!followTarget.value) return
  submitting.value = true
  try {
    const data: Record<string, unknown> = { type: payload.type, content: payload.content }
    if (payload.next_follow_up_date) data.next_follow_up_date = payload.next_follow_up_date
    if (payload.next_follow_up_note)   data.next_follow_up_note   = payload.next_follow_up_note
    await post(`/customers/${followTarget.value.id}/follow-ups`, data)
    ElMessage.success('跟进记录已添加')
    showFollowDialog.value = false
    loadList()
  } finally {
    submitting.value = false
  }
}

// === 导入 ===
const showImportDialog = ref(false)
const importResult = ref<{ success: number; failed: number } | null>(null)

const handleImport = () => {
  importResult.value = null
  showImportDialog.value = true
}

async function handleImportConfirm(file: File) {
  importing.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    const data = await post('/customers/import', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    importResult.value = { success: data.success || 0, failed: data.failed || 0 }
    ElMessage.success(`导入完成: 成功 ${importResult.value!.success} 条，失败 ${importResult.value!.failed} 条`)
    loadList()
    loadStats()
  } finally {
    importing.value = false
  }
}

function downloadTemplate() {
  const headers = ['客户名称', '所属行业', '联系人', '联系电话', '客户分类', '标签(用;分隔)']
  const sample  = ['北京示例公司', '互联网科技', '张三', '13800000000', '普通', '重点客户;新客户']
  const csv = '\uFEFF' + [headers.join(','), sample.map((v) => `"${v}"`).join(',')].join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = '客户导入模板.csv'
  a.click()
  URL.revokeObjectURL(url)
}

// === 导出 ===
const handleExport = async () => {
  try {
    const data = await get('/customers', {
      page: 1, per_page: 1000, ...searchState.value,
    })
    const rows = ((data?.data || data || [])).map((c: Customer) => [
      c.name, c.industry || '', c.contact || '', c.phone || '',
      c.category || '', (c.tags || []).join(';'), c.project_count || 0, c.last_follow_at || '',
    ])
    const headers = ['客户名称', '所属行业', '联系人', '联系电话', '客户分类', '标签', '项目数', '最后跟进']
    exportExcelLike(headers, rows, '客户列表', { title: '客户档案导出' })
  } catch { /* toast */ }
}

// === 详情 / 删除 / 批量 ===
const goDetail = (row: Customer) => router.push({ path: `/customer/${row.id}` })

async function handleDelete(row: Customer) {
  try {
    await ElMessageBox.confirm(`确定要删除客户「${row.name}」吗？`, '删除确认', {
      type: 'warning', confirmButtonText: '确定', cancelButtonText: '取消',
    })
  } catch { return }
  try {
    await del(`/customers/${row.id}`)
    ElMessage.success('客户已删除')
    if (list.value.length === 1 && page.value > 1) page.value -= 1
    loadList()
    loadStats()
  } catch { /* toast */ }
}

async function handleBatchDelete() {
  if (!selected.value.length) return
  try {
    await ElMessageBox.confirm(
      `确定要删除选中的 ${selected.value.length} 个客户？此操作不可恢复。`,
      '批量删除',
      { type: 'warning', confirmButtonText: '确定删除', cancelButtonText: '取消' },
    )
  } catch { return }
  let success = 0; let failed = 0
  for (const c of selected.value) {
    try { await del(`/customers/${c.id}`); success++ } catch { failed++ }
  }
  ElMessage.success(`已删除 ${success} 个客户${failed ? `，失败 ${failed}` : ''}`)
  selected.value = []
  loadList()
  loadStats()
}

function handleBatchAddFollow() {
  ElMessage.info(`批量跟进功能：即将为 ${selected.value.length} 个客户添加跟进记录（开发中）`)
}
</script>

<style lang="scss" scoped>
.customer-page { padding: 16px 20px; background: #f5f7fa; min-height: 100%; }
.table-card {
  border-radius: 8px;
  border: none;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.card-header {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  margin-bottom: 12px;
  padding: 4px 0;
}
.batch-bar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 12px; margin-bottom: 12px;
  background: #E6F1FB; border-radius: 6px;
  border-left: 3px solid #185FA5;
}
.bb-info { font-size: 13px; color: #0C447C; display: flex; align-items: center; gap: 6px; }
.bb-info b { color: #185FA5; }
.bb-actions { display: flex; gap: 8px; }
.pager { margin-top: 16px; text-align: right; }
</style>
