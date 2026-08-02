<template>
  <div class="page-container">
    <div class="page-header">
      <h2>发票管理</h2>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="openApplyDialog">申请发票</el-button>
      </div>
    </div>

    <el-tabs v-model="activeTab" @tab-change="onTabChange">
      <el-tab-pane label="销售发票" name="sales" />
      <el-tab-pane label="采购发票" name="purchase" />
    </el-tabs>

    <div class="stat-cards">
      <div class="stat-card"><div class="stat-value primary">{{ stats.totalCount }}</div><div class="stat-label">发票总数</div></div>
      <div class="stat-card"><div class="stat-value success">¥{{ stats.totalAmount }}</div><div class="stat-label">开票总额(元)</div></div>
      <div class="stat-card"><div class="stat-value warning">{{ stats.pendingCount }}</div><div class="stat-label">待审批</div></div>
      <div class="stat-card"><div class="stat-value info">{{ stats.deliveredCount }}</div><div class="stat-label">已交付</div></div>
    </div>

    <el-card shadow="never" class="content-card">
      <div class="filter-bar">
        <el-input v-model="searchKey" placeholder="发票号/客户名称" clearable style="width: 220px" @keyup.enter="loadList" />
        <el-select v-model="filterStatus" placeholder="状态" clearable style="width: 140px" @change="loadList">
          <el-option label="待审批" value="requested" />
          <el-option label="审批中" value="pending_approval" />
          <el-option label="已开具" value="issued" />
          <el-option label="已交付" value="delivered" />
          <el-option label="草稿" value="draft" />
          <el-option label="已取消" value="cancelled" />
        </el-select>
        <el-button type="primary" :icon="Search" @click="loadList">查询</el-button>
      </div>

      <el-table :data="list" stripe v-loading="loading" style="width: 100%">
        <el-table-column prop="invoice_no" label="发票号" width="160" />
        <el-table-column v-if="activeTab === 'sales'" label="客户" min-width="120" show-overflow-tooltip>
          <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
        </el-table-column>
        <el-table-column v-else label="供应商" min-width="120" show-overflow-tooltip>
          <template #default="{ row }">{{ row.supplier?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="关联合同" min-width="120" show-overflow-tooltip>
          <template #default="{ row }">{{ row.contract?.contract_no || '-' }}</template>
        </el-table-column>
        <el-table-column label="类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag size="small" :type="invoiceTypeStyle(row.invoice_type)">{{ invoiceTypeLabel(row.invoice_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="total_amount" label="价税合计" width="120" align="right">
          <template #default="{ row }">¥{{ Number(row.total_amount || 0).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column prop="issue_date" label="开票日期" width="110" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" align="center" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'requested'" link type="primary" size="small" @click="changeStatus(row, 'pending_approval')">提交审批</el-button>
            <el-button v-if="row.status === 'pending_approval'" link type="success" size="small" @click="changeStatus(row, 'issued')">开具发票</el-button>
            <el-button v-if="row.status === 'issued'" link type="warning" size="small" @click="changeStatus(row, 'delivered')">确认交付</el-button>
            <el-button v-if="['requested','pending_approval'].includes(row.status)" link type="danger" size="small" @click="changeStatus(row, 'cancelled')">取消</el-button>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无发票数据" /></template>
      </el-table>

      <div class="pagination-bar" v-if="total > 0">
        <el-pagination v-model:current-page="page" :page-size="perPage" :total="total" layout="total, prev, pager, next" @current-change="loadList" />
      </div>
    </el-card>

    <!-- 申请发票对话框 -->
    <el-dialog v-model="dialogVisible" title="申请发票" width="520px">
      <el-form :model="form" label-width="90px">
        <el-form-item v-if="activeTab === 'sales'" label="客户" required>
          <el-select v-model="form.customer_id" filterable placeholder="选择客户" style="width: 100%" @change="loadContracts">
            <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-else label="供应商" required>
          <el-select v-model="form.supplier_id" filterable placeholder="选择供应商" style="width: 100%">
            <el-option v-for="s in suppliers" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="activeTab === 'sales'" label="关联合同">
          <el-select v-model="form.contract_id" filterable placeholder="选择合同(可选)" style="width: 100%">
            <el-option v-for="c in contracts" :key="c.id" :label="c.contract_no" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="发票类型" required>
          <el-radio-group v-model="form.invoice_type">
            <el-radio value="ordinary">普票</el-radio>
            <el-radio value="special">专票</el-radio>
            <el-radio value="electronic">电子发票</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="金额(元)" required>
          <el-input-number v-model="form.amount" :min="0" :precision="2" style="width: 200px" />
        </el-form-item>
        <el-form-item label="税率(%)">
          <el-input-number v-model="form.tax_rate" :min="0" :max="100" :precision="2" style="width: 200px" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitApply">提交申请</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Plus, Search } from '@element-plus/icons-vue'
import { get, post, put } from '@/utils/request'
import { unwrapPaginate, unwrapList } from '@/utils/response'
import type { FinanceInvoice, BizContact } from './types'

interface InvoiceItem extends FinanceInvoice {
  id: number
  invoice_no: string
  customer?: { name: string }
  contract?: { contract_no: string }
}

const list = ref<InvoiceItem[]>([])
const loading = ref(false)
const searchKey = ref('')
const filterStatus = ref('')
const page = ref(1)
const perPage = 15
const total = ref(0)
const activeTab = ref<'sales' | 'purchase'>('sales')

const onTabChange = () => { page.value = 1; loadList() }

const statusMap: Record<string, { label: string; type: 'info' | 'warning' | 'success' | 'danger' | '' }> = {
  draft: { label: '草稿', type: 'info' },
  requested: { label: '待审批', type: 'warning' },
  pending_approval: { label: '审批中', type: 'warning' },
  issued: { label: '已开具', type: 'success' },
  delivered: { label: '已交付', type: '' },
  cancelled: { label: '已取消', type: 'danger' },
}
const statusLabel = (s: string) => statusMap[s]?.label || s
const statusType = (s: string) => statusMap[s]?.type || 'info'

const invoiceTypeMap: Record<string, { label: string; type: 'info' | 'warning' | 'success' }> = {
  special: { label: '专票', type: 'warning' },
  ordinary: { label: '普票', type: 'info' },
  electronic: { label: '电子发票', type: 'success' },
}
const invoiceTypeLabel = (t: string) => invoiceTypeMap[t]?.label || t || '-'
const invoiceTypeStyle = (t: string) => invoiceTypeMap[t]?.type || 'info'

const stats = computed(() => {
  const totalCount = total.value
  const totalAmount = list.value.filter(r => r.status === 'issued' || r.status === 'delivered').reduce((s, r) => s + Number(r.total_amount || 0), 0).toFixed(2)
  const pendingCount = list.value.filter(r => r.status === 'requested' || r.status === 'pending_approval').length
  const deliveredCount = list.value.filter(r => r.status === 'delivered').length
  return { totalCount, totalAmount, pendingCount, deliveredCount }
})

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: page.value, per_page: perPage, direction: activeTab.value }
    if (filterStatus.value) params.status = filterStatus.value
    if (searchKey.value) params.keyword = searchKey.value
    const r = await get('/finance/invoices', params)
    const pag = unwrapPaginate(r)
    list.value = pag.list as InvoiceItem[]
    total.value = pag.total
  } catch { list.value = []; total.value = 0 } finally { loading.value = false }
}

// 申请对话框
const dialogVisible = ref(false)
const submitting = ref(false)
const customers = ref<BizContact[]>([])
const suppliers = ref<BizContact[]>([])
const contracts = ref<BizContact[]>([])
const form = reactive({ customer_id: null as number | null, supplier_id: null as number | null, contract_id: null as number | null, invoice_type: 'ordinary', amount: 0, tax_rate: 0, remark: '' })

const openApplyDialog = async () => {
  form.customer_id = null; form.supplier_id = null; form.contract_id = null; form.invoice_type = 'ordinary'; form.amount = 0; form.tax_rate = 0; form.remark = ''
  dialogVisible.value = true
  if (activeTab.value === 'sales') {
    if (!customers.value.length) { try { const r = await get('/customers', { per_page: 500 }); customers.value = unwrapList(r) } catch {} }
  } else {
    if (!suppliers.value.length) { try { const r = await get('/suppliers', { per_page: 500 }); suppliers.value = unwrapList(r) } catch {} }
  }
}

const loadContracts = async () => {
  if (!form.customer_id) { contracts.value = []; return }
  try {
    const r = await get('/projects/contracts', { customer_id: form.customer_id })
    contracts.value = unwrapList(r)
  } catch { contracts.value = [] }
}

const submitApply = async () => {
  if (activeTab.value === 'sales' && !form.customer_id) { ElMessage.warning('请选择客户'); return }
  if (activeTab.value === 'purchase' && !form.supplier_id) { ElMessage.warning('请选择供应商'); return }
  if (!form.amount || form.amount <= 0) { ElMessage.warning('请输入金额'); return }
  submitting.value = true
  try {
    const payload: Record<string, unknown> = {
      direction: activeTab.value,
      invoice_type: form.invoice_type,
      amount: form.amount,
      tax_rate: form.tax_rate,
      remark: form.remark,
      status: 'requested',
    }
    if (activeTab.value === 'sales') {
      payload.customer_id = form.customer_id
      payload.contract_id = form.contract_id
    } else {
      payload.supplier_id = form.supplier_id
    }
    await post('/finance/invoices', payload)
    ElMessage.success('发票申请已提交')
    dialogVisible.value = false
    loadList()
  } catch (e: unknown) {
    const err = e as { message?: string }
    ElMessage.error(err?.message || '提交失败')
  } finally { submitting.value = false }
}

const changeStatus = async (row: InvoiceItem, status: string) => {
  try {
    await put(`/finance/invoices/${row.id}`, { status })
    ElMessage.success('状态已更新')
    loadList()
  } catch (e: unknown) {
    const err = e as { message?: string }
    ElMessage.error(err?.message || '操作失败')
  }
}

onMounted(loadList)
</script>

<style scoped>
.page-container { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.stat-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.stat-card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); text-align: center; }
.stat-value { font-size: 22px; font-weight: 600; }
.stat-value.primary { color: #0C447C; } .stat-value.success { color: #3B6D11; }
.stat-value.warning { color: #BA7517; } .stat-value.info { color: #888; }
.stat-label { color: #999; font-size: 12px; margin-top: 4px; }
.content-card { border: none; }
.filter-bar { display: flex; gap: 8px; margin-bottom: 12px; }
.pagination-bar { margin-top: 12px; display: flex; justify-content: flex-end; }
</style>
