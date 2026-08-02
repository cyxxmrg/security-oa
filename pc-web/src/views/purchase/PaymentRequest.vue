<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">付款申请</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="handleReset">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建申请</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="关联合同">
          <el-select v-model="searchForm.contract_id" placeholder="全部合同" clearable filterable style="width: 220px">
            <el-option v-for="c in contractOptions" :key="c.id" :label="`${c.code}（${c.title}）`" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="付款类型">
          <el-select v-model="searchForm.payment_type" placeholder="全部类型" clearable style="width: 140px">
            <el-option v-for="t in paymentTypeOptions" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="content-card">
      <div class="stats-row">
        <div class="stat-card" v-for="s in statCards" :key="s.label" :style="{ borderColor: s.color }">
          <div class="stat-icon" :style="{ background: s.color + '15', color: s.color }">
            <el-icon :size="20"><component :is="s.icon" /></el-icon>
          </div>
          <div>
            <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
            <div class="stat-label">{{ s.label }}</div>
          </div>
        </div>
      </div>

      <el-tabs v-model="activeTab" class="status-tabs">
        <el-tab-pane v-for="t in tabOptions" :key="t.value" :name="t.value">
          <template #label>
            <span class="tab-label">
              {{ t.label }}
              <el-badge :value="tabCount(t.value)" :max="99" type="primary" :hidden="tabCount(t.value) === 0" />
            </span>
          </template>
        </el-tab-pane>
      </el-tabs>

      <PaymentRequestTable
        :list="pagedList"
        :loading="loading"
        :page="page"
        :page-size="pageSize"
        :total="filteredList.length"
        @view="handleView"
        @edit="handleEdit"
        @delete="handleDelete"
        @page-change="(p: number) => page = p"
        @size-change="(s: number) => { pageSize = s; page = 1 }"
      />
    </div>

    <!-- 新建 付款申请 -->
    <el-dialog v-model="showFormDialog" :title="formMode === 'create' ? '新建付款申请' : '编辑付款申请'" width="1500px" :close-on-click-modal="false">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="110px">
        <el-form-item label="关联合同" prop="contract_id">
          <el-select v-model="formData.contract_id" placeholder="请选择关联合同" filterable style="width: 100%">
            <el-option v-for="c in contractOptions" :key="c.id" :label="`${c.code}（${c.title}）`" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="付款金额" prop="amount">
              <el-input-number v-model="formData.amount" :min="0" :step="1000" :precision="2" style="width: 100%" placeholder="元" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="付款类型" prop="payment_type">
              <el-select v-model="formData.payment_type" placeholder="请选择" style="width: 100%">
                <el-option v-for="t in paymentTypeOptions" :key="t.value" :label="t.label" :value="t.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="申请人" prop="applicant">
              <el-input v-model="formData.applicant" placeholder="请输入申请人姓名" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="申请日期">
              <el-date-picker v-model="formData.request_date" type="date" placeholder="选择日期" style="width: 100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="付款原因">
          <el-input v-model="formData.reason" type="textarea" :rows="3" placeholder="可选" maxlength="500" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showFormDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 详情 dialog -->
    <el-dialog v-model="showDetailDialog" title="付款申请详情" width="1500px">
      <div v-if="currentRow" class="detail-view">
        <div class="detail-row"><span class="label">申请 ID</span><span class="value">#{{ currentRow.id }}</span></div>
        <div class="detail-row"><span class="label">关联合同</span><span class="value link-text" @click="handleViewContract(currentRow)">{{ currentRow.contract?.code || currentRow.contract_id || '-' }}</span></div>
        <div class="detail-row"><span class="label">付款金额</span><span class="value" style="color:#1D9E75;font-weight:600">¥ {{ formatMoney(currentRow.amount) }}</span></div>
        <div class="detail-row"><span class="label">付款类型</span><span class="value">{{ paymentTypeLabel(currentRow.payment_type ?? '') }}</span></div>
        <div class="detail-row"><span class="label">申请人</span><span class="value">{{ currentRow.applicant || '-' }}</span></div>
        <div class="detail-row"><span class="label">申请时间</span><span class="value">{{ currentRow.request_date ? String(currentRow.request_date).slice(0, 10) : (currentRow.applied_at || '-') }}</span></div>
        <div class="detail-row"><span class="label">付款原因</span><span class="value">{{ currentRow.reason || '-' }}</span></div>
        <div class="detail-row"><span class="label">状态</span><span class="value"><el-tag :type="statusTagType(currentRow.status)" effect="plain" size="small">{{ statusLabel(currentRow.status) }}</el-tag></span></div>
        <template v-if="currentRow.approver_id">
          <div class="detail-row"><span class="label">审批人 ID</span><span class="value">{{ currentRow.approver_id }}</span></div>
          <div class="detail-row"><span class="label">审批时间</span><span class="value">{{ currentRow.approved_at || '-' }}</span></div>
          <div class="detail-row"><span class="label">审批备注</span><span class="value">{{ currentRow.approve_remark || '-' }}</span></div>
        </template>
      </div>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, Document, List, Money, CircleCheck, CircleClose } from '@element-plus/icons-vue'
import { purchase } from '@/api/modules'
import { unwrapList, unwrapStats } from '@/utils/response'
import { purchaseFlow } from '@/api/purchase-flow'
import PaymentRequestTable from './components/payment-request/PaymentRequestTable.vue'
import PaymentRequestFilterBar from './components/payment-request/PaymentRequestFilterBar.vue'
import PaymentRequestStatCards from './components/payment-request/PaymentRequestStatCards.vue'
import PurchaseFlowStepper from '@/components/PurchaseFlowStepper.vue'
import PurchaseFlowHistory from '@/components/PurchaseFlowHistory.vue'


// === 类型（替代 any）===
interface PaymentRequest {
  id: number
  contract_id?: number | null
  supplier_id?: number | null
  amount?: number | string
  payment_type?: string
  request_date?: string
  applicant?: string
  reason?: string
  status: string
  contract?: { id?: number; code: string } | null
  approver_id?: number | null
  approved_at?: string | null
  approve_remark?: string | null
  applied_at?: string
  [key: string]: unknown
}
interface ContractOption {
  id: number
  code: string
  title: string
  [key: string]: unknown
}
interface PaymentRequestPayload {
  contract_id: number | null
  amount: number
  payment_type: string
  request_date: string | null
  applicant: string | null
  reason: string | null
  supplier_id?: number
  [key: string]: unknown
}
type ElTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

// === 状态选项（与后端 pending/approved/rejected/paid 一致） ===
const statusOptions = [
  { value: 'pending', label: '待审批' },
  { value: 'approved', label: '已通过' },
  { value: 'rejected', label: '已驳回' },
  { value: 'paid', label: '已付款' }
]

// === Tab 选项 ===
const tabOptions = [
  { value: 'pending', label: '待审批' },
  { value: 'approved', label: '已通过' },
  { value: 'rejected', label: '已驳回' },
  { value: 'paid', label: '已付款' }
]

// === 付款类型（与后端 enum 一致）===
const paymentTypeOptions = [
  { value: 'full', label: '全额' },
  { value: 'advance', label: '预付' },
  { value: 'progress', label: '进度款' },
  { value: 'retention', label: '尾款' }
]

// === 列表 / 加载 ===
const loading = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<PaymentRequest[]>([])
const contractOptions = ref<ContractOption[]>([])
const activeTab = ref('pending')
const stats = reactive({ pending: 0, approved: 0, rejected: 0, paid: 0, total: 0, total_amount: 0 })

const searchForm = reactive({ status: '', contract_id: null as number | null, payment_type: '' })

const filteredList = computed(() => {
  let arr = [...list.value]
  if (activeTab.value) arr = arr.filter(r => r.status === activeTab.value)
  if (searchForm.contract_id) arr = arr.filter(r => r.contract_id === searchForm.contract_id)
  if (searchForm.payment_type) arr = arr.filter(r => r.payment_type === searchForm.payment_type)
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const tabCount = (status: string) => list.value.filter(l => l.status === status).length

const statCards = computed(() => [
  { label: '申请总数', value: stats.total, icon: Document, color: '#0C447C' },
  { label: '待审批', value: stats.pending, icon: List, color: '#BA7517' },
  { label: '已通过', value: stats.approved, icon: Money, color: '#534AB7' },
  { label: '已付款', value: stats.paid, icon: CircleCheck, color: '#1D9E75' }
])

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 200, page: 1 }
    if (searchForm.contract_id) params.contract_id = searchForm.contract_id
    if (searchForm.payment_type) params.payment_type = searchForm.payment_type
    const res: unknown = await purchase.getPaymentRequests(params)
    // V0.6.3 不再解包, 兼容两种形态
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  try {
    const res: unknown = await purchase.getPaymentRequestStats()
    // V0.6.3 不再解包, 兼容两种形态
    Object.assign(stats, unwrapStats(res))
  } catch { /* 静默 */ }
}

const loadContracts = async () => {
  try {
    const res: unknown = await purchase.getContracts({ per_page: 200 })
    // V0.6.3 不再解包, 兼容两种形态
    contractOptions.value = unwrapList(res).map((c: ContractOption) => ({ id: c.id, code: c.code, title: c.title }))
  } catch {
    contractOptions.value = []
  }
}

const handleSearch = () => { page.value = 1 }
const handleReset = () => {
  searchForm.status = ''
  searchForm.contract_id = null
  searchForm.payment_type = ''
  page.value = 1
  loadList()
}

// === 详情 ===
const showDetailDialog = ref(false)
const currentRow = ref<PaymentRequest | null>(null)
const handleView = (row: PaymentRequest) => {
  currentRow.value = row
  showDetailDialog.value = true
}
const handleViewContract = (row: PaymentRequest) => {
  const c = contractOptions.value.find((x: ContractOption) => x.id === row.contract_id)
  ElMessage.info(`查看合同：${c?.code || row.contract_id}（占位）`)
}

// === 新建 / 编辑 ===
const showFormDialog = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const formRef = ref()
const submitting = ref(false)
const formData = reactive({
  id: 0,
  contract_id: null as number | null,
  supplier_id: null as number | null,
  amount: 0,
  payment_type: 'full',
  request_date: '',
  applicant: '',
  reason: ''
})
const formRules = {
  contract_id: [{ required: true, message: '请选择关联合同', trigger: 'change' }],
  amount: [{ required: true, type: 'number' as const, min: 0, message: '请输入付款金额', trigger: 'blur' }],
  payment_type: [{ required: true, message: '请选择付款类型', trigger: 'change' }],
  applicant: [{ required: true, message: '请输入申请人', trigger: 'blur' }]
}

const resetForm = () => {
  Object.assign(formData, {
    id: 0, contract_id: null, supplier_id: null, amount: 0, payment_type: 'full',
    request_date: '', applicant: '', reason: ''
  })
}

const handleAdd = () => {
  formMode.value = 'create'
  resetForm()
  showFormDialog.value = true
}

const handleEdit = (row: PaymentRequest) => {
  if (row.status !== 'pending') {
    ElMessage.warning('仅「待审批」状态可编辑')
    return
  }
  formMode.value = 'edit'
  Object.assign(formData, {
    id: row.id,
    contract_id: row.contract_id || null,
    supplier_id: row.supplier_id || null,
    amount: Number(row.amount || 0),
    payment_type: row.payment_type || 'full',
    request_date: row.request_date ? String(row.request_date).slice(0, 10) : '',
    applicant: row.applicant || '',
    reason: row.reason || ''
  })
  showFormDialog.value = true
}

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    const payload: PaymentRequestPayload = {
      contract_id: formData.contract_id,
      amount: Number(formData.amount) || 0,
      payment_type: formData.payment_type,
      request_date: formData.request_date || null,
      applicant: formData.applicant || null,
      reason: formData.reason || null
    }
    if (formData.supplier_id) payload.supplier_id = formData.supplier_id
    if (formMode.value === 'create') {
      // V0.6.2 走 flow API (contract_id 放 body)
      await purchaseFlow.createPaymentRequest(formData.contract_id!, payload)
      ElMessage.success('付款申请创建成功')
    } else {
      // 后端无 PUT，按业务约定：先创建新申请再删除旧的，或仅修改 reason/amount（后端无 PUT）
      // 实际后端没有 PUT /payment-requests/{id}，仅支持 destroy + store
      ElMessage.warning('后端暂不支持编辑，请删除后重新创建')
      showFormDialog.value = false
      return
    }
    showFormDialog.value = false
    page.value = 1
    await loadList()
    await loadStats()
  } catch { /* 拦截器已提示 */ }
  finally { submitting.value = false }
}

// === 审批 ===
const handleApprove = async (row: PaymentRequest, decision: 'approve' | 'reject') => {
  let remark = ''
  if (decision === 'reject') {
    try {
      const { value } = await ElMessageBox.prompt('请输入驳回原因', '驳回付款申请', {
        inputType: 'textarea',
        inputValidator: (v: string) => (v && v.trim() ? true : '请填写驳回原因')
      })
      remark = value
    } catch { return }
  }
  try {
    await purchaseFlow.approvePaymentRequest(row.id, remark)
    ElMessage.success(decision === 'approve' ? '已批准' : '已驳回')
    await loadList()
    await loadStats()
  } catch { /* 拦截器已提示 */ }
}

// === 删除 ===
const handleDelete = async (row: PaymentRequest) => {
  if (row.status === 'paid') {
    ElMessage.warning('已付款的申请不可删除')
    return
  }
  try {
    await ElMessageBox.confirm(
      `确认删除付款申请 #${row.id}？该操作不可恢复。`,
      '删除确认',
      { type: 'warning', confirmButtonText: '确认删除', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    await purchase.deletePaymentRequest(row.id)
    ElMessage.success('已删除')
    await loadList()
    await loadStats()
    if (pagedList.value.length === 0 && page.value > 1) page.value -= 1
  } catch { /* 拦截器已提示 */ }
}

// === 工具 ===
const formatMoney = (n?: number | string) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const statusLabel = (s: string) => statusOptions.find(o => o.value === s)?.label || s || '-'
const STATUS_TAG_MAP: Record<string, ElTagType> = {
  pending: 'warning', approved: 'success', rejected: 'danger', paid: 'primary',
}
const statusTagType = (s: string): ElTagType => STATUS_TAG_MAP[s] || 'info'
const paymentTypeLabel = (t: string) => paymentTypeOptions.find(o => o.value === t)?.label || t || '-'

onMounted(() => { loadList(); loadStats(); loadContracts() })
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.filter-bar { background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.stat-card {
  display: flex; align-items: center; gap: 12px; padding: 12px 16px;
  background: #fff; border: 1px solid #ebeef5; border-left: 4px solid;
  border-radius: 6px; transition: all 0.2s;
  &:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
  .stat-value { font-size: 20px; font-weight: 700; line-height: 1.2; }
  .stat-label { font-size: 12px; color: #909399; margin-top: 2px; }
}
.status-tabs { margin-bottom: 12px; }
.tab-label { display: inline-flex; align-items: center; gap: 6px; }
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; &:hover { text-decoration: underline; } }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
.sub-text { font-size: 11px; color: #909399; margin-top: 2px; }
.account-cell { line-height: 1.4; }
.voucher-text {
  display: inline-flex; align-items: center; gap: 4px;
  color: #0C447C; font-size: 12px;
  max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.voucher-preview {
  display: flex; align-items: center; gap: 8px;
  margin-top: 8px; padding: 8px 12px; background: #f5f7fa; border-radius: 4px;
  font-size: 13px;
}
.form-tip { font-size: 12px; color: #909399; margin-top: 4px; line-height: 1.4; }
.detail-view {
  .detail-row {
    display: flex; padding: 10px 0; border-bottom: 1px dashed #ebeef5;
    .label { width: 110px; color: #909399; font-size: 13px; }
    .value { flex: 1; color: #303133; font-size: 14px; word-break: break-all; }
    &:last-child { border-bottom: none; }
  }
}
</style>
