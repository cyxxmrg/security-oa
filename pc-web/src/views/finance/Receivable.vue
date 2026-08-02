<template>
  <div class="page-container">
    <StatCards :totals="totals" />

    <div class="page-header">
      <div class="page-title-wrap">
        <span class="page-title">应收账款</span>
        <span class="page-subtitle">管理客户应收款及回款记录</span>
      </div>
      <div>
        <el-button @click="handleExport"><el-icon><Download /></el-icon>导出报表</el-button>
        <el-button type="primary" @click="handleCreate"><el-icon><Plus /></el-icon>新增应收</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-input v-model="filters.keyword" placeholder="客户名称 / 项目" clearable style="width:240px" @keyup.enter="loadList">
        <template #prefix><el-icon><Search /></el-icon></template>
      </el-input>
      <el-select v-model="filters.status" placeholder="全部状态" clearable style="width:140px" @change="loadList">
        <el-option label="待收" value="pending" />
        <el-option label="部分收" value="partial" />
        <el-option label="已收完" value="fully_paid" />
        <el-option label="已逾期" value="overdue" />
      </el-select>
      <el-date-picker v-model="filters.dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width:260px" @change="loadList" />
      <el-button type="primary" @click="loadList"><el-icon><Search /></el-icon>查询</el-button>
      <el-button @click="resetSearch">重置</el-button>
      <div class="filter-spacer" />
      <el-button-group>
        <el-button :type="viewMode === 'card' ? 'primary' : 'default'" @click="viewMode = 'card'"><el-icon><Grid /></el-icon>卡片</el-button>
        <el-button :type="viewMode === 'table' ? 'primary' : 'default'" @click="viewMode = 'table'"><el-icon><List /></el-icon>列表</el-button>
      </el-button-group>
    </div>

    <ReceivableList
      :list="list"
      :loading="loading"
      :view-mode="viewMode"
      @pay="openReceive"
      @edit="handleEdit"
      @detail="openDetail"
      @delete="handleDelete"
    />

    <FormDialog
      v-model="createDialogVisible"
      :mode="editingId ? 'edit' : 'create'"
      :form="form"
      :loading="submitting"
      :suppliers="customers"
      :projects="projects"
      @submit="handleSubmit"
    />

    <RegisterDialog
      v-model="receiveDialogVisible"
      :loading="receiving"
      :form="receiveForm"
      :row="receiveRow"
      :accounts="accounts"
      @submit="confirmReceive"
    />

    <DetailDrawer
      v-model="detailVisible"
      :row="detailRow"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Download, Search, Grid, List } from '@element-plus/icons-vue'
import { get, post, put, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import { exportExcelLike, printTable } from '@/utils/exporter'

import StatCards from './components/receivable/StatCards.vue'
import ReceivableList from './components/receivable/List.vue'
import FormDialog from './components/receivable/FormDialog.vue'
import RegisterDialog from './components/receivable/RegisterDialog.vue'
import DetailDrawer from './components/receivable/DetailDrawer.vue'

import type { Receivable, ReceivableTotals, ReceivableFilters } from './components/receivable/types'
import { isOverdue, emptyTotals } from './components/receivable/types'

// v0.3.25 拆 Receivable.vue 517→210 (-59%)
// 子组件: StatCards / List(双视图) / FormDialog / RegisterDialog / DetailDrawer

const filters = ref<ReceivableFilters>({ keyword: '', status: '', dateRange: [] })
const list = ref<Receivable[]>([])
const loading = ref(false)
const customers = ref<{ id: number; name: string }[]>([])
const projects = ref<{ id: number; name: string }[]>([])
const accounts = ref<{ id: number; name: string; bank?: string; type?: string }[]>([])
const viewMode = ref<'card' | 'table'>('card')

const totals = computed<ReceivableTotals>(() => {
  const t = emptyTotals()
  t.count = list.value.length
  list.value.forEach((r) => {
    t.amount += Number(r.amount) || 0
    t.received += Number(r.received_amount) || 0
    t.remaining += Number(r.remaining_amount) || 0
    if (isOverdue(r)) {
      t.overdue += Number(r.remaining_amount) || 0
      t.overdueCount++
    }
  })
  t.receivedRate = t.amount > 0 ? Math.round((t.received / t.amount) * 100) : 0
  return t
})

const loadList = async () => {
  loading.value = true
  try {
    const res = await get('/finance/receivables', { keyword: filters.value.keyword, status: filters.value.status })
    // V0.6.3: request.ts 不解包, res = {code, data: paginator}
    list.value = unwrapList(res)
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '加载应收列表失败')
  } finally { loading.value = false }
}

const loadCustomers = async () => {
  try {
    const r = await get('/customers', { pageSize: 500 })
    const d = r?.data ?? {}
    customers.value = Array.isArray(d?.items) ? d.items : (Array.isArray(d?.data) ? d.data : [])
  } catch { /* 静默 */ }
}
const loadProjects = async () => {
  try {
    const r = await get('/projects', { pageSize: 500 })
    const d = r?.data ?? {}
    projects.value = Array.isArray(d?.items) ? d.items : (Array.isArray(d?.data) ? d.data : [])
  } catch { /* 静默 */ }
}
const loadAccounts = async () => {
  accounts.value = [
    { id: 1, name: '招商银行基本户', bank: '招商银行深圳分行', type: '银行' },
    { id: 2, name: '工商银行一般户', bank: '工商银行南山支行', type: '银行' },
    { id: 3, name: '支付宝企业账户', bank: '', type: '支付宝' },
    { id: 4, name: '微信企业账户', bank: '', type: '微信' },
  ]
}

onMounted(() => { loadList(); loadCustomers(); loadProjects(); loadAccounts() })

const resetSearch = () => {
  filters.value = { keyword: '', status: '', dateRange: [] }
  loadList()
}

const createDialogVisible = ref(false)
const editingId = ref<number | null>(null)
const submitting = ref(false)
const form = reactive({
  customer_id: null as number | null, project_id: null as number | null,
  amount: 0, received_amount: 0, due_date: '', notes: '',
})

const handleCreate = () => {
  editingId.value = null
  Object.assign(form, { customer_id: null, project_id: null, amount: 0, received_amount: 0, due_date: '', notes: '' })
  createDialogVisible.value = true
}
const handleEdit = (row: Receivable) => {
  editingId.value = row.id
  Object.assign(form, {
    customer_id: row.customer_id, project_id: row.project_id,
    amount: row.amount, received_amount: row.received_amount,
    due_date: row.due_date, notes: row.notes || '',
  })
  createDialogVisible.value = true
}
const handleSubmit = async () => {
  submitting.value = true
  try {
    if (editingId.value) {
      await put(`/finance/receivables/${editingId.value}`, form)
      ElMessage.success('已更新')
    } else {
      await post('/finance/receivables', form)
      ElMessage.success('已创建')
    }
    createDialogVisible.value = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '保存失败')
  } finally { submitting.value = false }
}

const receiveDialogVisible = ref(false)
const receiving = ref(false)
const receiveRow = ref<Receivable | null>(null)
const receiveForm = reactive({ received_amount: 0, received_date: '', account_id: null as number | null })

const openReceive = (row: Receivable) => {
  receiveRow.value = row
  receiveForm.received_amount = Number(row.remaining_amount) || 0
  receiveForm.received_date = new Date().toISOString().slice(0, 10)
  receiveForm.account_id = null
  receiveDialogVisible.value = true
}
const confirmReceive = async () => {
  if (!receiveRow.value) return
  receiving.value = true
  try {
    const newReceived = Number(receiveRow.value.received_amount || 0) + Number(receiveForm.received_amount)
    await put(`/finance/receivables/${receiveRow.value.id}`, { received_amount: newReceived, received_date: receiveForm.received_date })
    ElMessage.success('收款已登记')
    receiveDialogVisible.value = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '登记失败')
  } finally { receiving.value = false }
}

const detailVisible = ref(false)
const detailRow = ref<Receivable | null>(null)
const openDetail = (row: Receivable) => { detailRow.value = row; detailVisible.value = true }

const handleDelete = async (row: Receivable) => {
  try { await ElMessageBox.confirm(`确定要删除「${row.customer?.name}」的应收单？`, '删除确认', { type: 'warning' }) } catch { return }
  try {
    await del(`/finance/receivables/${row.id}`)
    ElMessage.success('已删除')
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '删除失败')
  }
}

const handleExport = () => {
  const headers = ['应收单号', '客户', '关联项目', '应收金额', '已收金额', '未收金额', '应收日期', '到期日', '状态', '备注']
  const rows = list.value.map((r: Receivable) => [
    r.code || '-', r.customer?.name || '-', r.project?.name || '-',
    Number(r.amount || 0).toFixed(2), Number(r.received_amount || 0).toFixed(2),
    (Number(r.amount || 0) - Number(r.received_amount || 0)).toFixed(2),
    r.due_date?.slice(0, 10) || '-', r.payment_term || '-',
    ({ pending: '待收', partial: '部分收', received: '已收', overdue: '逾期' }[r.status as string] || r.status || '-'),
    r.notes || '',
  ])
  exportExcelLike(headers, rows, '应收账款报表', { title: '应收账款报表' })
}
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: linear-gradient(180deg, #f0f4fa 0%, #f5f7fa 100%); min-height: 100vh; }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 16px; padding: 18px 20px;
  background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title-wrap { display: flex; align-items: baseline; gap: 12px; }
  .page-title { font-size: 20px; color: #0C447C; font-weight: 600; }
  .page-subtitle { font-size: 13px; color: #6b7280; }
}
.filter-bar {
  display: flex; gap: 12px; align-items: center;
  margin-bottom: 16px; padding: 16px 20px;
  background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .filter-spacer { flex: 1; }
}
</style>
