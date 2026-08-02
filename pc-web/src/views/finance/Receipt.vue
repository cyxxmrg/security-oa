<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">收款单管理</span>
      <el-button type="primary" @click="handleCreate"><el-icon><Plus /></el-icon>新增收款单</el-button>
    </div>

    <div class="filter-bar">
      <el-input v-model="searchForm.keyword" placeholder="搜索单号/客户/项目" clearable style="width: 220px" @keyup.enter="loadList" />
      <el-select v-model="searchForm.status" placeholder="状态" clearable style="width: 140px" @change="loadList">
        <el-option label="待确认" value="pending" />
        <el-option label="已确认" value="confirmed" />
        <el-option label="已作废" value="voided" />
      </el-select>
      <el-button type="primary" @click="loadList">查询</el-button>
      <el-button @click="resetSearch">重置</el-button>
    </div>

    <el-row :gutter="16" class="mb-16">
      <el-col :span="6">
        <div class="mini-stat">
          <div class="mini-stat-value primary">¥{{ stats.totalAmount }}</div>
          <div class="mini-stat-label">收款总额(万元)</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="mini-stat">
          <div class="mini-stat-value success">{{ stats.confirmedCount }}</div>
          <div class="mini-stat-label">已确认单数</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="mini-stat">
          <div class="mini-stat-value warning">{{ stats.pendingCount }}</div>
          <div class="mini-stat-label">待确认单数</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="mini-stat">
          <div class="mini-stat-value danger">¥{{ stats.monthAmount }}</div>
          <div class="mini-stat-label">本月收款(万元)</div>
        </div>
      </el-col>
    </el-row>

    <div class="content-card">
      <el-table :data="list" stripe border style="width: 100%" v-loading="loading">
        <el-table-column prop="voucher_no" label="收款单号" width="160" />
        <el-table-column label="客户" min-width="160">
          <template #default="{ row }">{{ row.customer?.name || row.customer_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="关联项目" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.project?.name || row.project_name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="amount" label="收款金额" width="120">
          <template #default="{ row }">¥{{ row.amount }}</template>
        </el-table-column>
        <el-table-column prop="method" label="收款方式" width="120" align="center">
          <template #default="{ row }">
            <el-tag size="small">{{ methodLabel(row.method) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="receipt_date" label="收款日期" width="120" />
        <el-table-column prop="handler" label="经办人" width="100">
          <template #default="{ row }">{{ row.operator || row.handler || row.applicant?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag type="success" size="small">已收款</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleView(row)">查看</el-button>
            <el-button link type="primary" size="small" @click="handlePrint(row)">打印</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="showFormDialog" :title="editingId ? '编辑收款单' : '新增收款单'" width="1500px" destroy-on-close>
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-form-item label="收款单号" prop="receipt_no">
          <el-input v-model="formData.receipt_no" placeholder="如 SK-2026-001" />
        </el-form-item>
        <el-form-item label="客户" prop="customer">
          <el-select v-model="formData.customer_id" filterable placeholder="选择客户" style="width: 100%">
            <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="关联项目">
          <el-select v-model="formData.project_id" filterable clearable placeholder="选择项目" style="width: 100%">
            <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="收款金额" prop="amount">
          <el-input-number v-model="formData.amount" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="收款方式">
          <el-select v-model="formData.method" style="width: 100%">
            <el-option label="银行转账" value="bank" />
            <el-option label="现金" value="cash" />
            <el-option label="支付宝" value="alipay" />
            <el-option label="微信" value="wechat" />
            <el-option label="支票" value="check" />
            <el-option label="承兑汇票" value="check" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="资金账户">
          <el-select v-model="formData.account_id" filterable clearable placeholder="选择入账账户" style="width: 100%">
            <el-option v-for="a in accountOptions" :key="a.id" :label="`${a.name}（余额 ¥${formatAccountBalance(a.balance)}）`" :value="a.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="收款日期">
          <el-date-picker v-model="formData.receipt_date" type="date" placeholder="选择日期" style="width: 100%" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="关联应收" prop="receivable_id">
          <el-select v-model="formData.receivable_id" filterable clearable placeholder="选择该客户的应收款 (自动核销)" style="width: 100%" :disabled="!formData.customer_id" @focus="loadReceivables">
            <el-option v-for="r in receivables" :key="r.id" :label="`${r.receivable_no || 'AR-' + r.id} | 应收 ¥${r.amount} | 未收 ¥${r.remaining_amount}`" :value="r.id" />
          </el-select>
          <div v-if="receivables.length === 0 && formData.customer_id" class="hint-text">该客户暂无未收完的应收款</div>
        </el-form-item>
        <el-form-item label="经办人">
          <el-input v-model="formData.handler" placeholder="自动关联当前登录用户" disabled />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="formData.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showFormDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showDetailDialog" title="收款单详情" width="1500px">
      <el-descriptions :column="2" border v-if="detailRow">
        <el-descriptions-item label="收款单号">{{ detailRow.voucher_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="状态">
            <el-tag type="success" size="small">已收款</el-tag>
          </el-descriptions-item>
        <el-descriptions-item label="客户">{{ detailRow.customer?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="关联项目">{{ detailRow.project?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="收款金额" :span="2">
          <span style="font-weight:700;color:#1D9E75;font-size:18px">¥{{ detailRow.amount }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="收款方式">{{ methodLabel(detailRow.method ?? '') }}</el-descriptions-item>
        <el-descriptions-item label="收款日期">{{ detailRow.receipt_date }}</el-descriptions-item>
        <el-descriptions-item label="资金账户" :span="2">{{ detailRow.account?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="经办人">{{ detailRow.operator || '-' }}</el-descriptions-item>
        <el-descriptions-item label="凭证号">{{ detailRow.voucher_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="备注" :span="4">{{ detailRow.remark || detailRow.notes || '无' }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { getReceipts, createReceipt, updateReceipt, confirmReceipt, voidReceipt } from '@/api/modules'
import { get, post, put } from '@/utils/request'
import { paymentMethodLabel } from '@/utils/labels'
import { printTable } from '@/utils/exporter'

const methodLabel = (m: string) => paymentMethodLabel(m)

const userStore = useUserStore()
const currentUserName = computed(() => {
  const u = userStore.userInfo as { name?: string; username?: string } | null
  return u?.name || u?.username || ''
})

const LIST_KEY = 'oa:finance:receipts'

// 客户
interface CustomerItem { id: number; name: string; [k: string]: unknown }
// 项目
interface ProjectItem { id: number; name: string; [k: string]: unknown }
// 应收款
interface ReceivableItem {
  id: number
  receivable_no?: string
  amount?: number | string
  remaining_amount?: number | string
  received_amount?: number | string
  status?: string
  [k: string]: unknown
}
// 关联实体
interface NamedEntity { name?: string }
// 收款单
interface ReceiptItem {
  id: string | number
  receipt_no?: string
  customer?: string
  customerEntity?: NamedEntity
  customer_name?: string
  project?: NamedEntity
  project_name?: string
  amount?: number | string
  method?: string
  receipt_date?: string
  received_date?: string
  payment_date?: string
  handler?: string
  applicant?: NamedEntity
  operator?: string
  created_by?: number | string
  status?: string
  notes?: string
  remark?: string
  voucher_no?: string
  [k: string]: unknown
}
// API 错误
interface ApiError { message?: string }
// API 响应
interface ApiResponse<T = unknown> { data?: T; [k: string]: unknown }

const searchForm = ref({ keyword: '', status: '' })
const list = ref<ReceiptItem[]>([])
const loading = ref(false)
const customers = ref<CustomerItem[]>([])
const projects = ref<ProjectItem[]>([])
const receivables = ref<ReceivableItem[]>([])
const accountOptions = ref<{ id: number; name: string; balance: number }[]>([])

const loadList = async () => {
  loading.value = true
  try {
    // V1.2.12n fix: 改用 ledger/customer-receipts 接口 (跟新增/详情一致, 同一张表)
    const r: ApiResponse<{ list?: { data?: ReceiptItem[] }; data?: ReceiptItem[] } | ReceiptItem[] | unknown> = await get('/ledger/customer-receipts', { per_page: 500, page: 1 })
    const data = (r as { data?: unknown })?.data ?? r
    let arr: ReceiptItem[] = []
    if (Array.isArray(data)) arr = data as ReceiptItem[]
    else if (Array.isArray((data as { list?: { data?: ReceiptItem[] } })?.list?.data)) arr = (data as { list: { data: ReceiptItem[] } }).list.data
    else if (Array.isArray((data as { list?: ReceiptItem[] })?.list)) arr = (data as { list: ReceiptItem[] }).list as unknown as ReceiptItem[]
    else if (Array.isArray((data as { data?: ReceiptItem[] })?.data)) arr = (data as { data: ReceiptItem[] }).data
    list.value = arr
    try { localStorage.setItem(LIST_KEY, JSON.stringify(arr)) } catch {}
  } catch { list.value = [] } finally { loading.value = false }
}

const loadCustomers = async () => {
  try { const r: ApiResponse<{ items?: CustomerItem[]; data?: CustomerItem[] }> = await get('/customers', { pageSize: 500 });
    const d = r?.data ?? {}; customers.value = Array.isArray(d?.items) ? d.items : (Array.isArray(d?.data) ? d.data : []) } catch (e) {}
}
const loadProjects = async () => {
  try { const r: ApiResponse<{ items?: ProjectItem[]; data?: ProjectItem[] }> = await get('/projects', { pageSize: 500 });
    const d = r?.data ?? {}; projects.value = Array.isArray(d?.items) ? d.items : (Array.isArray(d?.data) ? d.data : []) } catch (e) {}
}
const loadReceivables = async () => {
  if (!formData.customer_id) { receivables.value = []; return }
  try {
    // V1.2.16 fix: 改调 /finance/receivables (跟应收款管理页同源, 同一个 receivables 表)
    // 之前的 /ledger/customers/{id}/receivables 是另一张表 customer_receivables, 数据不同步
    const r: ApiResponse<{ data?: ReceivableItem[] }> = await get('/finance/receivables', {
      customer_id: formData.customer_id,
      per_page: 200,
    })
    const d = (r as { data?: unknown })?.data ?? r
    const arr = Array.isArray(d) ? d : (Array.isArray((d as { data?: ReceivableItem[] })?.data ?? []) ? (d as { data: ReceivableItem[] }).data : [])
    // 只显示未收完的: receivables 表的字段是 remaining_amount
    receivables.value = arr.filter((x: ReceivableItem) => Number(x.remaining_amount || 0) > 0)
  } catch { receivables.value = [] }
}

const loadAccounts = async () => {
  try {
    const r = await get('/finance/accounts', { per_page: 200 })
    const data = (r as { data?: unknown })?.data ?? r
    // data = pagination 对象: {current_page, data: [...accounts], total, ...}
    const inner = (data as { data?: unknown[] })?.data ?? (Array.isArray(data) ? data : [])
    accountOptions.value = inner as { id: number; name: string; balance: number }[]
  } catch { accountOptions.value = [] }
}

const formatAccountBalance = (n: number | string | undefined | null): string => {
  if (n == null) return '0.00'
  return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

watch(() => formData.customer_id, (newId) => {
  // 客户变了, 清掉 receivables 让用户重新选
  receivables.value = []
  formData.receivable_id = null
  if (newId) loadReceivables()
})

onMounted(() => { loadList(); loadCustomers(); loadProjects(); loadAccounts() })

const resetSearch = () => { searchForm.value = { keyword: '', status: '' }; loadList() }

const stats = computed(() => {
  // V1.2.10 fix: 收款记录本身就是已确认, 不需要 filter status
  const total = list.value.reduce((s: number, r: ReceiptItem) => s + Number(r.amount || 0), 0)
  const confirmedCount = list.value.length
  const pendingCount = 0
  const now = new Date()
  const monthStart = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`
  const monthAmount = list.value.filter((r: ReceiptItem) => (r.receipt_date || r.payment_date || '') >= monthStart).reduce((s: number, r: ReceiptItem) => s + Number(r.amount || 0), 0)
  return { totalAmount: (total / 10000).toFixed(1), confirmedCount, pendingCount, monthAmount: (monthAmount / 10000).toFixed(1) }
})

const showFormDialog = ref(false)
const editingId = ref<string | null>(null)
const formRef = ref()
const submitting = ref(false)
const formData = reactive({
  receipt_no: '', customer_id: null as number | null, project_id: null as number | null,
  receivable_id: null as number | null,
  amount: 0, method: 'bank', account_id: null as number | null,
  receipt_date: '', handler: '', notes: '',
})
const formRules = {
  receipt_no: [{ required: true, message: '请输入单号', trigger: 'blur' }],
  customer_id: [{ required: true, message: '请选择客户', trigger: 'change' }],
  receivable_id: [{ required: true, message: '请选择关联应收款 (决定这笔款核销到哪一笔)', trigger: 'change' }],
  amount: [{ required: true, message: '请输入金额', trigger: 'blur' }],
}

const handleCreate = () => {
  editingId.value = null
  Object.assign(formData, {
    receipt_no: `SK-${new Date().getFullYear()}-${String(Math.floor(Math.random()*999)+1).padStart(3,'0')}`,
    customer_id: null, project_id: null, receivable_id: null,
    amount: 0, method: 'bank', account_id: null,
    receipt_date: new Date().toISOString().slice(0,10),
    handler: currentUserName.value,  // V1.2.12n: 自动关联当前登录用户
    notes: ''
  })
  receivables.value = []
  showFormDialog.value = true
}
const handleSubmit = async () => {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  submitting.value = true
  try {
    // V1.2.12n: 调用正确的 LedgerController::createCustomerReceipt (/api/ledger/customer-receipts)
    const payload = {
      customer_id: formData.customer_id,
      project_id: formData.project_id || undefined,
      amount: formData.amount,
      receipt_date: formData.receipt_date,
      method: formData.method,
      voucher_no: formData.receipt_no,  // 单号写到 voucher_no
      account_id: formData.account_id || undefined,
      operator: currentUserName.value || formData.handler,  // 经办人 → operator
      remark: formData.notes || undefined,
      allocations: [{
        receivable_id: formData.receivable_id,
        amount: formData.amount,
      }],
    }
    await post('/ledger/customer-receipts', payload)
    ElMessage.success('收款单已创建')
    showFormDialog.value = false
    loadList()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; msg?: string } } }
    ElMessage.error(err?.response?.data?.message || err?.response?.data?.msg || (e as ApiError)?.message || '保存失败')
  } finally { submitting.value = false }
}

const showDetailDialog = ref(false)
const detailRow = ref<ReceiptItem | null>(null)
const handleView = (row: ReceiptItem) => { detailRow.value = row; showDetailDialog.value = true }

const handleConfirm = async (row: ReceiptItem) => {
  try { await ElMessageBox.confirm(`确认收款单 ${row.receipt_no}，金额 ¥${row.amount}？`, '确认收款', { type: 'success', confirmButtonText: '确认' }) } catch { return }
  await confirmReceipt(Number(row.id))
  const all: ReceiptItem[] = JSON.parse(localStorage.getItem(LIST_KEY) || '[]')
  const idx = all.findIndex((x: ReceiptItem) => x.id === row.id)
  if (idx >= 0) { all[idx].status = 'confirmed'; localStorage.setItem(LIST_KEY, JSON.stringify(all)); loadList(); ElMessage.success('已确认') }
}
const handleVoid = async (row: ReceiptItem) => {
  try { await ElMessageBox.confirm(`确认作废收款单 ${row.receipt_no}？`, '作废确认', { type: 'warning' }) } catch { return }
  await voidReceipt(Number(row.id))
  const all: ReceiptItem[] = JSON.parse(localStorage.getItem(LIST_KEY) || '[]')
  const idx = all.findIndex((x: ReceiptItem) => x.id === row.id)
  if (idx >= 0) { all[idx].status = 'voided'; localStorage.setItem(LIST_KEY, JSON.stringify(all)); loadList(); ElMessage.success('已作废') }
}
const handlePrint = (row: ReceiptItem) => {
  const headers = ['字段', '内容']
  const rows = [
    ['收款单号', row.receipt_no || '-'],
    ['关联客户', row.customer?.name || row.customer_name || '-'],
    ['关联项目', row.project?.name || row.project_name || '-'],
    ['收款金额', '¥' + Number(row.amount || 0).toFixed(2)],
    ['付款方式', ({ bank: '银行转账', cash: '现金', alipay: '支付宝', wechat: '微信', check: '支票' }[row.method as string] || row.method || '-')],
    ['收款日期', row.received_date?.slice(0, 10) || row.payment_date?.slice(0, 10) || '-'],
    ['凭证号', row.voucher_no || '-'],
    ['收款状态', '已收款'],
    ['经手人', row.operator || '-'],
    ['备注', row.remark || row.notes || '-'],
  ]
  printTable(`收款单 - ${row.receipt_no || ''}`, headers, rows, { orientation: 'portrait' })
}
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; .page-title { font-size: 20px; color: #0C447C; font-weight: 600; } }
.filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding: 16px; background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); flex-wrap: wrap; }
.content-card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.mb-16 { margin-bottom: 16px; }
.mini-stat { background: #fff; border-radius: 8px; padding: 16px; text-align: center; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.mini-stat-value { font-size: 22px; font-weight: 700; &.primary { color: #0C447C; } &.success { color: #1D9E75; } &.warning { color: #BA7517; } &.danger { color: #A32D2D; } }
.mini-stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #1D9E75; }
.fw-bold { font-weight: 700; }
</style>
