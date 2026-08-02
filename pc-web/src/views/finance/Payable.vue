<template>
  <div class="page-container">
    <PayableStatCards :totals="totals" />

    <div class="page-header">
      <div class="page-title-wrap">
        <span class="page-title">应付账款</span>
        <span class="page-subtitle">管理供应商应付款及付款记录</span>
      </div>
      <div>
        <el-button @click="handleExport"><el-icon><Download /></el-icon>导出报表</el-button>
        <el-button type="primary" @click="handleCreate"><el-icon><Plus /></el-icon>新增应付</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-input v-model="filters.keyword" placeholder="供应商 / 项目" clearable style="width:240px" @keyup.enter="loadList">
        <template #prefix><el-icon><Search /></el-icon></template>
      </el-input>
      <el-select v-model="filters.status" placeholder="全部状态" clearable style="width:140px" @change="loadList">
        <el-option label="待付" value="pending" />
        <el-option label="部分付" value="partial" />
        <el-option label="已付完" value="fully_paid" />
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

    <PayableList
      :list="list"
      :loading="loading"
      :view-mode="viewMode"
      @pay="openPay"
      @edit="handleEdit"
      @detail="openDetail"
      @delete="handleDelete"
    />

    <PayableFormDialog
      v-model="createDialogVisible"
      :mode="editingId ? 'edit' : 'create'"
      :form="form"
      :loading="submitting"
      :suppliers="suppliers"
      :projects="projects"
      @submit="handleSubmit"
    />

    <PayRegisterDialog
      v-model="payDialogVisible"
      :loading="paying"
      :form="payForm"
      :row="payRow"
      :accounts="accounts"
      @submit="confirmPay"
    />

    <PayableDetailDrawer
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
import { unwrapPaginate } from '@/utils/response'
import { exportExcelLike, printTable } from '@/utils/exporter'

import PayableStatCards from './components/payable/PayableStatCards.vue'
import PayableList from './components/payable/PayableList.vue'
import PayableFormDialog from './components/payable/PayableFormDialog.vue'
import PayRegisterDialog from './components/payable/PayRegisterDialog.vue'
import PayableDetailDrawer from './components/payable/PayableDetailDrawer.vue'

import type { Payable, PayableTotals, PayableFilters } from './components/payable/types'
import { isOverdue, emptyTotals } from './components/payable/types'
import type { FinanceAccount } from './types'

// v0.3.25 拆 Payable.vue 528→220 (-58%)
// 子组件: StatCards / List(双视图) / FormDialog / PayRegisterDialog / DetailDrawer

const filters = ref<PayableFilters>({ keyword: '', status: '', dateRange: [] })
const list = ref<Payable[]>([])
const loading = ref(false)
const suppliers = ref<{ id: number; name: string }[]>([])
const projects = ref<{ id: number; name: string }[]>([])
const accounts = ref<{ id: number; name: string; bank?: string; type?: string }[]>([])
const viewMode = ref<'card' | 'table'>('card')

const totals = computed<PayableTotals>(() => {
  const t = emptyTotals()
  t.count = list.value.length
  list.value.forEach((r) => {
    t.amount += Number(r.amount) || 0
    t.paid += Number(r.paid_amount) || 0
    t.remaining += Number(r.remaining_amount) || 0
    if (isOverdue(r)) {
      t.overdue += Number(r.remaining_amount) || 0
      t.overdueCount++
    }
  })
  t.paidRate = t.amount > 0 ? Math.round((t.paid / t.amount) * 100) : 0
  return t
})

const loadList = async () => {
  loading.value = true
  try {
    const res = await get('/finance/payables', { keyword: filters.value.keyword, status: filters.value.status })
    // V0.6.3: request.ts 不解包, res = {code, data: paginator}
    const pag = unwrapPaginate(res)
    list.value = pag.list
    totals.value.count = pag.total
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '加载应付列表失败')
  } finally { loading.value = false }
}

const loadSuppliers = async () => {
  try {
    const r = await get('/suppliers', { pageSize: 500 })
    const d = r?.data ?? {}
    suppliers.value = Array.isArray(d?.items) ? d.items : (Array.isArray(d?.data) ? d.data : [])
  } catch {
    suppliers.value = [
      { id: 1, name: '海康威视数字技术股份有限公司' },
      { id: 2, name: '华为技术有限公司' },
      { id: 3, name: '大华技术股份有限公司' },
      { id: 4, name: '浙江宇视科技有限公司' },
      { id: 5, name: '天地伟业技术有限公司' },
    ]
  }
}
const loadProjects = async () => {
  try {
    const r = await get('/projects', { pageSize: 500 })
    const d = r?.data ?? {}
    projects.value = Array.isArray(d?.items) ? d.items : (Array.isArray(d?.data) ? d.data : [])
  } catch {
    /* 静默 */
  }
}
const loadAccounts = async () => {
  // V1.2.9: 从 /api/finance/accounts 拉真实数据
  try {
    const r = await get('/finance/accounts', { per_page: 100, status: 'active' })
    const list = r?.data?.data ?? r?.data ?? []
    accounts.value = (Array.isArray(list) ? list : []).map((a: FinanceAccount) => ({
      id: a.id ?? 0,
      name: a.name ?? '',
      bank: a.bank_name ?? '',
      type: a.type,
    }))
  } catch (e) {
    console.warn('loadAccounts failed:', (e as { message?: string })?.message || e)
    accounts.value = []
  }
}

onMounted(() => { loadList(); loadSuppliers(); loadProjects(); loadAccounts() })

const resetSearch = () => {
  filters.value = { keyword: '', status: '', dateRange: [] }
  loadList()
}

const createDialogVisible = ref(false)
const editingId = ref<number | null>(null)
const submitting = ref(false)
const form = reactive({
  supplier_id: null as number | null, project_id: null as number | null,
  amount: 0, paid_amount: 0, due_date: '', payment_term: '', notes: '',
})

const handleCreate = () => {
  editingId.value = null
  Object.assign(form, { supplier_id: null, project_id: null, amount: 0, paid_amount: 0, due_date: '', payment_term: '', notes: '' })
  createDialogVisible.value = true
}

const handleEdit = (row: Payable) => {
  editingId.value = row.id
  Object.assign(form, {
    supplier_id: row.supplier_id, project_id: row.project_id,
    amount: row.amount, paid_amount: row.paid_amount,
    due_date: row.due_date, payment_term: row.payment_term || '', notes: row.notes || '',
  })
  createDialogVisible.value = true
}

const handleSubmit = async () => {
  submitting.value = true
  try {
    if (editingId.value) {
      await put(`/finance/payables/${editingId.value}`, form)
      ElMessage.success('已更新')
    } else {
      await post('/finance/payables', form)
      ElMessage.success('已创建')
    }
    createDialogVisible.value = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '保存失败')
  } finally { submitting.value = false }
}

const payDialogVisible = ref(false)
const paying = ref(false)
const payRow = ref<Payable | null>(null)
const payForm = reactive({ paid_amount: 0, paid_date: '', account_id: null as number | null })

const openPay = (row: Payable) => {
  payRow.value = row
  payForm.paid_amount = Number(row.remaining_amount) || 0
  payForm.paid_date = new Date().toISOString().slice(0, 10)
  payForm.account_id = null
  payDialogVisible.value = true
}

const confirmPay = async () => {
  if (!payRow.value) return
  // V1.2.9: 账户必填校验
  if (!payForm.account_id) {
    ElMessage.error('请选择付款账户')
    return
  }
  if (!payForm.paid_amount || Number(payForm.paid_amount) <= 0) {
    ElMessage.error('付款金额必须大于 0')
    return
  }
  paying.value = true
  try {
    // 1. 写入 finance_payments (含 account_id)
    await post(`/finance/payables/${payRow.value.id}/payments`, {
      amount: Number(payForm.paid_amount),
      payment_date: payForm.paid_date,
      account_id: payForm.account_id,
      method: 'bank', // 默认银行转账 (前端 select 简化后固定)
    })
    // 2. 后端会自动累加 paid_amount + 更新状态, 不用手动 put
    ElMessage.success(`付款已登记 (账户#${payForm.account_id})`)
    payDialogVisible.value = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '登记失败')
  } finally { paying.value = false }
}

const detailVisible = ref(false)
const detailRow = ref<Payable | null>(null)
const openDetail = (row: Payable) => { detailRow.value = row; detailVisible.value = true }

const handleDelete = async (row: Payable) => {
  try { await ElMessageBox.confirm(`确定要删除「${row.supplier?.name}」的应付单？`, '删除确认', { type: 'warning' }) } catch { return }
  try {
    await del(`/finance/payables/${row.id}`)
    ElMessage.success('已删除')
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '删除失败')
  }
}

const handleExport = () => {
  const headers = ['应付单号', '供应商', '关联项目', '应付金额', '已付金额', '未付金额', '应付日期', '到期日', '状态', '备注']
  const rows = list.value.map((r: Payable) => [
    r.code || '-', r.supplier?.name || '-', r.project?.name || '-',
    Number(r.amount || 0).toFixed(2), Number(r.paid_amount || 0).toFixed(2),
    (Number(r.amount || 0) - Number(r.paid_amount || 0)).toFixed(2),
    r.due_date?.slice(0, 10) || '-', r.payment_term || '-',
    ({ pending: '待付', partial: '部分付', paid: '已付', overdue: '逾期' }[r.status as string] || r.status || '-'),
    r.notes || '',
  ])
  exportExcelLike(headers, rows, '应付账款报表', { title: '应付账款报表' })
}
</script>

<style lang="scss" scoped>
.page-container {
  padding: 20px;
  background: linear-gradient(180deg, #f0f4fa 0%, #f5f7fa 100%);
  min-height: 100vh;
}
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 16px; padding: 18px 20px;
  background: #fff; border-radius: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title-wrap { display: flex; align-items: baseline; gap: 12px; }
  .page-title { font-size: 20px; color: #0C447C; font-weight: 600; }
  .page-subtitle { font-size: 13px; color: #6b7280; }
}
.filter-bar {
  display: flex; gap: 12px; align-items: center;
  margin-bottom: 16px; padding: 16px 20px;
  background: #fff; border-radius: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .filter-spacer { flex: 1; }
}
</style>
