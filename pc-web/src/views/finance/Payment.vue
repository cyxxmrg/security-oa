<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">付款单管理</span>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleCreate">新增付款单</el-button>
      </div>
    </div>

    <el-row :gutter="16" class="kpi-row">
      <el-col :xs="12" :sm="6">
        <div class="kpi-card danger">
          <div class="kpi-label">付款总额</div>
          <div class="kpi-value">¥{{ formatMoney(stats.totalAmount) }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6">
        <div class="kpi-card success">
          <div class="kpi-label">已付款单数</div>
          <div class="kpi-value">{{ stats.paidCount }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6">
        <div class="kpi-card warning">
          <div class="kpi-label">本月付款</div>
          <div class="kpi-value">¥{{ formatMoney(stats.monthAmount) }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6">
        <div class="kpi-card">
          <div class="kpi-label">总笔数</div>
          <div class="kpi-value">{{ list.length }}</div>
        </div>
      </el-col>
    </el-row>

    <div class="filter-bar">
      <el-input v-model="filter.keyword" placeholder="搜索单号/收款方" clearable style="width: 220px" @keyup.enter="loadList(1)" @clear="loadList(1)" />
      <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width:240px" @change="loadList(1)" />
      <el-button type="primary" :icon="Search" @click="loadList(1)">查询</el-button>
    </div>

    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column type="index" label="#" width="50" align="center" />
        <el-table-column prop="voucher_no" label="付款单号" width="170" />
        <el-table-column label="收款方" min-width="160">
          <template #default="{ row }">{{ extractPayee(row) }}</template>
        </el-table-column>
        <el-table-column label="关联项目" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.project_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="金额" width="140" align="right">
          <template #default="{ row }"><span style="font-weight:600;color:#A32D2D">-¥{{ formatMoney(row.amount) }}</span></template>
        </el-table-column>
        <el-table-column label="资金账户" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ row.account?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="付款方式" width="100" align="center">
          <template #default="{ row }"><el-tag size="small">{{ methodLabel(row.method) }}</el-tag></template>
        </el-table-column>
        <el-table-column label="付款日期" width="160">
          <template #default="{ row }">{{ fmtDateTime(row.payment_date) }}</template>
        </el-table-column>
        <el-table-column label="经办人" width="100">
          <template #default="{ row }">{{ row.operator || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="70" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination background layout="total,prev,pager,next" :total="pagination.total" :current-page="pagination.page" :page-size="pagination.per_page" @current-change="(p: number) => loadList(p)" />
      </div>
    </div>

    <!-- 新增付款单 dialog -->
    <el-dialog v-model="showForm" title="新增付款单" width="1200px" :close-on-click-modal="false">
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
        <el-form-item label="付款单号" prop="payment_no">
          <el-input v-model="form.payment_no" placeholder="自动生成">
            <template #prefix><el-icon><Document /></el-icon></template>
          </el-input>
        </el-form-item>
        <el-form-item label="收款方" prop="payee">
          <el-select v-model="form.supplier_id" filterable clearable placeholder="选择供应商" style="width:100%" @change="onSupplierChange">
            <el-option v-for="s in supplierOptions" :key="s.id" :label="`${s.name}${s.balance != null ? '（欠款 ¥' + formatAccountBalance(s.balance) + '）' : ''}`" :value="s.id">
              <span>{{ s.name }}</span>
              <span v-if="s.balance != null" style="float:right;color:#BA7517;font-size:12px">欠款 ¥{{ formatAccountBalance(s.balance) }}</span>
            </el-option>
          </el-select>
          <div v-if="!form.supplier_id" style="margin-top:4px">
            <el-input v-model="form.payee_other" placeholder="或手动输入其他收款方名称" @input="formRef?.validateField('payee')" />
          </div>
        </el-form-item>
        <el-form-item label="付款金额" prop="amount">
          <el-input-number v-model="form.amount" :precision="2" :min="0.01" :step="100" style="width:100%" />
        </el-form-item>
        <el-form-item label="付款方式" prop="method">
          <el-select v-model="form.method" style="width:100%">
            <el-option label="银行转账" value="银行转账" />
            <el-option label="现金" value="现金" />
            <el-option label="支票" value="支票" />
            <el-option label="承兑汇票" value="承兑汇票" />
            <el-option label="其他" value="其他" />
          </el-select>
        </el-form-item>
        <el-form-item label="资金账户" prop="account_id">
          <el-select v-model="form.account_id" filterable clearable placeholder="选择付款账户" style="width:100%">
            <el-option v-for="a in accountOptions" :key="a.id" :label="`${a.name}（余额 ¥${formatAccountBalance(a.balance)}）`" :value="a.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="关联项目">
          <el-select v-model="form.project_id" filterable clearable placeholder="选择项目" style="width:100%">
            <el-option v-for="p in projectOptions" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="关联合同">
          <el-select v-model="form.contract_id" filterable clearable placeholder="选择采购合同" style="width:100%" :disabled="!form.supplier_id">
            <el-option v-for="c in filteredContracts" :key="c.id" :label="`${c.code} - ${c.title || c.code}`" :value="c.id" />
          </el-select>
          <div v-if="!form.supplier_id" class="hint-text">请先选择供应商</div>
        </el-form-item>
        <el-form-item label="付款日期" prop="payment_date">
          <el-date-picker v-model="form.payment_date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" style="width:100%" />
        </el-form-item>
        <el-form-item label="经办人">
          <el-input v-model="form.handler" disabled />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showForm = false">取消</el-button>
        <el-button type="danger" :loading="submitting" @click="handleSubmit">确认付款</el-button>
      </template>
    </el-dialog>

    <!-- 详情 dialog -->
    <el-dialog v-model="showDetail" title="付款单详情" width="1440px" :close-on-click-modal="false">
      <template v-if="detailItem">
        <el-descriptions :column="3" border>
          <el-descriptions-item label="付款单号" :span="2">{{ detailItem.voucher_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag type="success" size="small">已付款</el-tag></el-descriptions-item>
          <el-descriptions-item label="收款方" :span="3">{{ extractPayee(detailItem) }}</el-descriptions-item>
          <el-descriptions-item label="付款金额" :span="2">
            <span style="font-weight:700;color:#A32D2D;font-size:16px">-¥{{ formatMoney(detailItem.amount) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="付款方式">{{ methodLabel(detailItem.method) }}</el-descriptions-item>
          <el-descriptions-item label="资金账户" :span="3">{{ detailItem.account?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="关联项目">{{ detailItem.project_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="付款日期">{{ fmtDateTime(detailItem.payment_date) }}</el-descriptions-item>
          <el-descriptions-item label="经办人">{{ detailItem.operator || '-' }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="3">{{ detailItem.remark || '无' }}</el-descriptions-item>
        </el-descriptions>
      </template>
      <template #footer>
        <el-button @click="showDetail = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Plus, Refresh, Search, Document } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { getPayments, createPayment } from '@/api/modules'
import { paymentMethodLabel } from '@/utils/labels'

const methodLabel = (m: string) => paymentMethodLabel(m)
const currentUserName = ref('')

interface AccountOption { id: number; name: string; balance: number }
interface ProjectOption { id: number; name: string }
interface SupplierOption { id: number; name: string; code?: string; balance?: number }
interface ContractOption { id: number; code: string; title?: string; supplier_id: number; total_amount?: number }
interface PaymentRow {
  id: number
  voucher_no?: string
  amount?: number | string
  payment_date?: string
  method?: string
  operator?: string
  payee?: string
  remark?: string
  account?: { id: number; name: string } | null
  project_name?: string
  payable?: { supplier?: { name?: string } } | null
  receivable?: { customer?: { name?: string } } | null
  [k: string]: unknown
}

const loading = ref(false)
const submitting = ref(false)
const list = ref<PaymentRow[]>([])
const pagination = reactive({ page: 1, per_page: 15, total: 0 })
const dateRange = ref<[string, string] | null>(null)
const filter = reactive({ keyword: '' })

const accountOptions = ref<AccountOption[]>([])
const projectOptions = ref<ProjectOption[]>([])
const supplierOptions = ref<SupplierOption[]>([])
const contractOptions = ref<ContractOption[]>([])
const filteredContracts = computed(() => {
  if (!form.supplier_id) return []
  return contractOptions.value.filter(c => c.supplier_id === form.supplier_id)
})

// 详情
const showDetail = ref(false)
const detailItem = ref<PaymentRow | null>(null)

// 表单
const showForm = ref(false)
const formRef = ref<FormInstance | null>(null)
const form = reactive({
  payment_no: '', payee: '', amount: 0, method: '银行转账',
  account_id: null as number | null,
  project_id: null as number | null,
  supplier_id: null as number | null,
  contract_id: null as number | null,
  payee_other: '',
  payment_date: new Date().toISOString().slice(0, 10),
  handler: '', notes: '',
})
const formRules: FormRules = {
  payee: [{
    validator: (_rule: unknown, _value: string, callback: (e?: Error) => void) => {
      if (!form.supplier_id && !form.payee_other) callback(new Error('请选择供应商或输入收款方'))
      else callback()
    }, trigger: 'change',
  }],
  amount: [{ required: true, message: '请输入金额', trigger: 'blur' }],
  payment_date: [{ required: true, message: '请选择付款日期', trigger: 'change' }],
}

function formatMoney(n: number | string | undefined | null): string {
  if (n == null) return '0.00'
  return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

function formatAccountBalance(n: number | string | undefined | null): string {
  if (n == null) return '0.00'
  return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

function fmtDateTime(d: string | undefined | null): string {
  if (!d) return '-'
  return d.replace('T', ' ').slice(0, 16)
}

function extractPayee(row: PaymentRow): string {
  return row.payee || row.payable?.supplier?.name || row.receivable?.customer?.name || '-'
}

const stats = computed(() => {
  const total = list.value.reduce((s, r) => s + Number(r.amount || 0), 0)
  const paidCount = list.value.length
  const now = new Date()
  const monthStart = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`
  const monthAmount = list.value.filter(r => (r.payment_date || '') >= monthStart).reduce((s, r) => s + Number(r.amount || 0), 0)
  return { totalAmount: total, paidCount, monthAmount }
})

async function loadList(page = 1) {
  pagination.page = page
  loading.value = true
  try {
    const params: Record<string, unknown> = { page, per_page: pagination.per_page }
    if (dateRange.value) { params.date_from = dateRange.value[0]; params.date_to = dateRange.value[1] }
    const res = await getPayments(params)
    const data = (res as { data?: unknown })?.data ?? res
    let items: PaymentRow[] = []
    if (Array.isArray(data)) items = data
    else if (Array.isArray((data as { data?: PaymentRow[] })?.data)) items = (data as { data: PaymentRow[] }).data

    // 筛选 + 提取项目名
    if (filter.keyword) {
      const kw = filter.keyword.toLowerCase()
      items = items.filter(r => (r.voucher_no || '').toLowerCase().includes(kw) || extractPayee(r).toLowerCase().includes(kw))
    }
    items = items.map(r => ({
      ...r,
      project_name: extractProjectName(r),
    }))
    list.value = items
    pagination.total = (data as { total?: number })?.total || items.length
  } catch (e) {
    console.error('[loadList]', e)
    list.value = []
    pagination.total = 0
  } finally { loading.value = false }
}

function extractProjectName(row: PaymentRow): string {
  // 从 remark 里提取项目ID或名称
  const r = row.remark || ''
  const m = r.match(/项目ID:\s*(\d+)/)
  if (m) return `项目#${m[1]}`
  return '-'
}

async function loadAccounts() {
  try {
    const r = await get('/finance/accounts', { per_page: 200 })
    const data = (r as { data?: unknown })?.data ?? r
    const inner = (data as { data?: AccountOption[] })?.data ?? (Array.isArray(data) ? data : [])
    accountOptions.value = inner as AccountOption[]
  } catch { accountOptions.value = [] }
}

async function loadProjects() {
  try {
    const r = await get('/projects', { pageSize: 500 })
    const d = (r as { data?: { items?: ProjectOption[]; data?: ProjectOption[] } })?.data ?? {}
    projectOptions.value = (d as { items?: ProjectOption[] })?.items ?? (d as { data?: ProjectOption[] })?.data ?? []
  } catch { projectOptions.value = [] }
}

function onSupplierChange(id: number) {
  const s = supplierOptions.value.find(x => x.id === id)
  form.payee = s?.name || ''
  form.contract_id = null // 换供应商后清空合同选择
}
async function loadSuppliers() {
  try {
    const r = await get('/suppliers', { pageSize: 500 })
    const d = (r as { data?: unknown })?.data ?? r
    const items = (d as { items?: SupplierOption[] })?.items ?? (d as { data?: SupplierOption[] })?.data ?? (Array.isArray(d) ? d : [])
    supplierOptions.value = Array.isArray(items) ? items : []
    // 从 /finance/payables 获取供应商欠款 (跟应付账款页同源)
    try {
      const payR = await get('/finance/payables', { per_page: 500 })
      const pd = (payR as { data?: unknown })?.data ?? payR
      const payItems = (pd as { data?: { supplier_id: number; remaining_amount: string }[] })?.data ?? []
      if (Array.isArray(payItems)) {
        const balanceMap: Record<number, number> = {}
        payItems.forEach((p: { supplier_id: number; remaining_amount: string }) => {
          const sid = p.supplier_id
          balanceMap[sid] = (balanceMap[sid] || 0) + Number(p.remaining_amount || 0)
        })
        supplierOptions.value.forEach(s => { s.balance = balanceMap[s.id] ?? 0 })
      }
    } catch { /* 余额加载失败不影响主体功能 */ }
  } catch { supplierOptions.value = [] }
}
async function loadContracts() {
  try {
    const r = await get('/purchase/contracts', { per_page: 500 })
    const data = (r as { data?: unknown })?.data ?? r
    const items = (data as { data?: ContractOption[] })?.data ?? (Array.isArray(data) ? data : [])
    contractOptions.value = Array.isArray(items) ? items : []
  } catch { contractOptions.value = [] }
}

function handleCreate() {
  Object.assign(form, {
    payment_no: `FK-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 999) + 1).padStart(3, '0')}`,
    payee: '', amount: 0, method: '银行转账',
    account_id: null, project_id: null,
    supplier_id: null, payee_other: '', contract_id: null,
    payment_date: new Date().toISOString().slice(0, 10),
    handler: currentUserName.value, notes: '',
  })
  showForm.value = true
}

async function handleSubmit() {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  submitting.value = true
  try {
    const payee = form.supplier_id ? form.payee : (form.payee_other || '未知')
    await post('/finance/payments', {
      payee,
      amount: form.amount,
      payment_date: form.payment_date,
      method: form.method,
      payment_no: form.payment_no,
      account_id: form.account_id || undefined,
      project_id: form.project_id || undefined,
      supplier_id: form.supplier_id || undefined,
      contract_id: form.contract_id || undefined,
      handler: form.handler || currentUserName.value || undefined,
      notes: form.notes || undefined,
    })
    ElMessage.success('付款单已创建')
    showForm.value = false
    loadList(1)
    loadAccounts()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '保存失败')
  } finally { submitting.value = false }
}

function openDetail(row: PaymentRow) {
  detailItem.value = row
  showDetail.value = true
}

onMounted(async () => {
  // 加载当前用户
  try {
    const stored = localStorage.getItem('oa_user_info')
    if (stored) {
      const u = JSON.parse(stored) as { name?: string; username?: string }
      currentUserName.value = u.name || u.username || ''
    }
    const r = await get('/auth/me')
    const u = ((r as { data?: { user?: { name?: string } } })?.data?.user || (r as { data?: { name?: string } })?.data) as { name?: string } | null
    if (u?.name) currentUserName.value = u.name
  } catch { }
  loadAccounts()
  loadProjects()
  loadSuppliers()
  loadContracts()
  loadList(1)
})
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: linear-gradient(180deg, #f0f4fa 0%, #f5f7fa 100%); min-height: 100vh; }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 12px; margin-bottom: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.kpi-row { margin-bottom: 16px; }
.kpi-card {
  background: #fff; border-radius: 8px; padding: 18px;
  border-left: 4px solid #909399;
}
.kpi-card.danger { border-left-color: #A32D2D; }
.kpi-card.success { border-left-color: #1D9E75; }
.kpi-card.warning { border-left-color: #BA7517; }
.kpi-label { font-size: 13px; color: #909399; margin-bottom: 6px; }
.kpi-value { font-size: 22px; font-weight: 700; color: #303133; }
.filter-bar {
  display: flex; gap: 12px; align-items: center;
  background: #fff; padding: 14px 20px; border-radius: 8px;
  margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.content-card { background: #fff; padding: 16px 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.pagination-wrap { margin-top: 12px; display: flex; justify-content: flex-end; }
</style>