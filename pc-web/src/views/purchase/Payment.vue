<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">财务付款</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="handleReset">刷新</el-button>
        <el-button type="success" :icon="Money" @click="handleNewPay">新建付款</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="ID / 凭证号" clearable style="width: 180px" />
        </el-form-item>
        <el-form-item label="付款方式">
          <el-select v-model="searchForm.payment_method" placeholder="全部方式" clearable style="width: 140px">
            <el-option v-for="m in methodOptions" :key="m.value" :label="m.label" :value="m.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="付款日期">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 240px"
          />
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

      <el-tabs v-model="activeTab" class="pay-tabs" @tab-change="handleTabChange">
        <el-tab-pane label="已付款" name="success" />
        <el-tab-pane label="失败" name="failed" />
        <el-tab-pane label="已冲销" name="reversed" />
      </el-tabs>

      <PaymentTable
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

    <!-- 新建付款 dialog -->
    <el-dialog v-model="showPayDialog" title="执行付款" width="1500px" :close-on-click-modal="false">
      <el-form ref="payFormRef" :model="payForm" :rules="payRules" label-width="110px">
        <el-form-item label="关联付款申请">
          <el-select v-model="payForm.payment_request_id" placeholder="可选，从已通过的付款申请中选择" filterable clearable style="width: 100%" @change="onRequestChange">
            <el-option v-for="r in requestOptions" :key="r.id" :label="`#${r.id}（合同 #${r.contract_id} ¥${formatMoney(r.amount)}）`" :value="r.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="关联合同" prop="contract_id">
          <el-select v-model="payForm.contract_id" placeholder="请选择关联合同" filterable style="width: 100%">
            <el-option v-for="c in contractOptions" :key="c.id" :label="`${c.code}（${c.title}）`" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="付款金额" prop="amount">
              <el-input-number v-model="payForm.amount" :min="0" :step="1000" :precision="2" style="width: 100%" placeholder="元" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="付款方式" prop="payment_method">
              <el-select v-model="payForm.payment_method" placeholder="请选择" style="width: 100%">
                <el-option v-for="m in methodOptions" :key="m.value" :label="m.label" :value="m.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="付款日期" prop="paid_at">
              <el-date-picker v-model="payForm.paid_at" type="date" placeholder="选择付款日期" style="width: 100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="凭证号" prop="voucher_no">
              <el-input v-model="payForm.voucher_no" placeholder="如 BNK2026061900001" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="经办人">
          <el-input v-model="payForm.operator" placeholder="请输入经办人" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="payForm.remark" type="textarea" :rows="2" placeholder="可选" maxlength="200" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPayDialog = false">取消</el-button>
        <el-button type="success" :loading="paying" @click="handleConfirmPay">确认付款</el-button>
      </template>
    </el-dialog>

    <!-- 查看 dialog -->
    <el-dialog v-model="showViewDialog" title="付款记录详情" width="1500px">
      <div v-if="currentRow" class="detail-view">
        <div class="detail-row"><span class="label">付款 ID</span><span class="value">#{{ currentRow.id }}</span></div>
        <div class="detail-row"><span class="label">关联合同</span><span class="value">{{ currentRow.contract?.code || currentRow.contract_id || '-' }}</span></div>
        <div class="detail-row">
          <span class="label">付款金额</span>
          <span class="value amount-text">¥ {{ formatMoney(currentRow.amount) }}</span>
        </div>
        <div class="detail-row"><span class="label">付款方式</span><span class="value">{{ methodLabel(currentRow.payment_method ?? '') }}</span></div>
        <div class="detail-row"><span class="label">凭证号</span><span class="value mono">{{ currentRow.voucher_no || '-' }}</span></div>
        <div class="detail-row"><span class="label">付款日期</span><span class="value">{{ currentRow.paid_at ? String(currentRow.paid_at).slice(0, 10) : '-' }}</span></div>
        <div class="detail-row"><span class="label">经办人</span><span class="value">{{ currentRow.operator || '-' }}</span></div>
        <div class="detail-row">
          <span class="label">状态</span>
          <span class="value">
            <el-tag :type="statusTagType(currentRow.status ?? '')" effect="plain" size="small">{{ statusLabel(currentRow.status ?? '') }}</el-tag>
          </span>
        </div>
        <div class="detail-row"><span class="label">备注</span><span class="value">{{ currentRow.remark || '-' }}</span></div>
      </div>
      <template #footer>
        <el-button @click="showViewDialog = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Search, Refresh, Money, Document, List, CircleCheck, CircleClose
} from '@element-plus/icons-vue'
import { purchase } from '@/api/modules'
import { purchaseFlow } from '@/api/purchase-flow'
import { unwrapList, unwrapStats } from '@/utils/response'
import PaymentTable from './components/payment/PaymentTable.vue'


// === 类型 ===
import type { PaymentItem, ContractOption, PaymentRequestOption } from './types'

// === 选项 ===
const methodOptions = [
  { value: 'transfer', label: '银行转账' },
  { value: 'cash', label: '现金' },
  { value: 'check', label: '支票' },
  { value: 'other', label: '其他' }
]

// === 合同下拉（用于选择 contract_id 必填项）===
const contractOptions = ref<ContractOption[]>([])
const requestOptions = ref<PaymentRequestOption[]>([])  // 已审批/可付款的 PaymentRequest

// === 列表 / 加载 ===
const loading = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<PaymentItem[]>([])
const activeTab = ref('success')
const stats = reactive({ success: 0, failed: 0, reversed: 0, total: 0, total_amount: 0 })

const searchForm = reactive({ keyword: '', status: '', payment_method: '' })
const dateRange = ref<[string, string] | null>(null)

const filteredList = computed(() => {
  let arr = [...list.value]
  if (activeTab.value) arr = arr.filter(r => r.status === activeTab.value)
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter(r => String(r.id).includes(kw) || (r.voucher_no || '').toLowerCase().includes(kw))
  }
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  if (searchForm.payment_method) arr = arr.filter(r => r.payment_method === searchForm.payment_method)
  if (dateRange.value && dateRange.value.length === 2) {
    const [start, end] = dateRange.value
    arr = arr.filter(r => {
      const d = (r.paid_at || '').slice(0, 10)
      return d >= start && d <= end
    })
  }
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const statCards = computed(() => [
  { label: '成功笔数', value: stats.success, icon: CircleCheck, color: '#1D9E75' },
  { label: '成功总额', value: '¥ ' + formatMoney(stats.total_amount), icon: Money, color: '#0C447C' },
  { label: '失败', value: stats.failed, icon: CircleClose, color: '#A32D2D' },
  { label: '已冲销', value: stats.reversed, icon: Document, color: '#909399' }
])

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 200, page: 1 }
    if (searchForm.payment_method) params.payment_method = searchForm.payment_method
    if (dateRange.value && dateRange.value.length === 2) {
      params.date_from = dateRange.value[0]
      params.date_to = dateRange.value[1]
    }
    const res = await purchase.getPayments(params)
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  try {
    const res = await purchase.getPaymentStats()
    Object.assign(stats, unwrapStats(res))
  } catch { /* 静默 */ }
}

const loadContracts = async () => {
  try {
    const res = await purchase.getContracts({ per_page: 200 })
    contractOptions.value = unwrapList(res).map((c: ContractOption) => ({ id: c.id, code: c.code, title: c.title }))
  } catch { contractOptions.value = [] }
}

const loadRequests = async () => {
  try {
    // 已通过、可作为付款来源的申请
    const res = await purchase.getPaymentRequests({ status: 'approved', per_page: 200 })
    requestOptions.value = unwrapList(res)
  } catch { requestOptions.value = [] }
}

const handleSearch = () => { page.value = 1 }
const handleReset = () => {
  searchForm.keyword = ''
  searchForm.status = ''
  searchForm.payment_method = ''
  dateRange.value = null
  page.value = 1
  loadList()
}
const handleTabChange = () => { page.value = 1 }

// === 工具 ===
const formatMoney = (n: number | string | undefined) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const methodLabel = (m: string) => methodOptions.find(o => o.value === m)?.label || m || '-'
const statusLabel = (s?: string) => ({ success: '已付款', failed: '失败', reversed: '已冲销' } as Record<string, string>)[s ?? ''] || s || '-'
const statusTagType = (s?: string): 'success' | 'danger' | 'info' => ({ success: 'success', failed: 'danger', reversed: 'info' } as Record<string, 'success' | 'danger' | 'info'>)[s ?? ''] || 'info'

// === 查看 ===
const showViewDialog = ref(false)
const currentRow = ref<PaymentItem | null>(null)
const handleView = (row: PaymentItem) => {
  currentRow.value = row
  showViewDialog.value = true
}
const handleDownload = (_row: PaymentItem) => {
  ElMessage.success('下载凭证（占位）')
}
const handleEdit = (row: PaymentItem) => {
  ElMessage.info(`编辑付款 #${row.id}（占位，待接入编辑接口）`)
}
const handleDelete = async (row: PaymentItem) => {
  try {
    await ElMessageBox.confirm(`确认删除付款记录 #${row.id}？`, '删除确认', { type: 'warning' })
    // TODO: 接入删除付款接口
    ElMessage.success('已删除（占位）')
  } catch { /* cancel */ }
}

// === 新建付款（实付落地）===
const showPayDialog = ref(false)
const paying = ref(false)
const payFormRef = ref()
const payForm = reactive({
  payment_request_id: null as number | null,
  contract_id: null as number | null,
  supplier_id: null as number | null,
  amount: 0,
  payment_method: 'transfer',
  paid_at: new Date().toISOString().slice(0, 10),
  voucher_no: '',
  operator: '',
  remark: ''
})
const payRules = {
  contract_id: [{ required: true, message: '请选择关联合同', trigger: 'change' }],
  amount: [{ required: true, type: 'number' as const, min: 0, message: '请输入付款金额', trigger: 'blur' }],
  payment_method: [{ required: true, message: '请选择付款方式', trigger: 'change' }],
  paid_at: [{ required: true, message: '请选择付款日期', trigger: 'change' }],
  voucher_no: [{ required: true, message: '请输入凭证号', trigger: 'blur' }]
}

const resetPayForm = () => {
  Object.assign(payForm, {
    payment_request_id: null,
    contract_id: null,
    supplier_id: null,
    amount: 0,
    payment_method: 'transfer',
    paid_at: new Date().toISOString().slice(0, 10),
    voucher_no: '',
    operator: '',
    remark: ''
  })
}

const handleNewPay = () => {
  resetPayForm()
  loadRequests()
  showPayDialog.value = true
}

// 选 payment_request_id 自动填合同与金额
const onRequestChange = (id: number | null) => {
  if (!id) return
  const req = requestOptions.value.find((r: PaymentRequestOption) => r.id === id)
  if (req) {
    payForm.contract_id = req.contract_id ?? null
    payForm.supplier_id = req.supplier_id || null
    payForm.amount = Number(req.amount || 0)
  }
}

const handleConfirmPay = async () => {
  const valid = await payFormRef.value?.validate().catch(() => false)
  if (!valid) return
  paying.value = true
  try {
    const payload: Record<string, unknown> = {
      contract_id: payForm.contract_id,
      amount: Number(payForm.amount) || 0,
      payment_method: payForm.payment_method,
      paid_at: payForm.paid_at,
      voucher_no: payForm.voucher_no,
      operator: payForm.operator || null,
      remark: payForm.remark || null
    }
    if (payForm.payment_request_id) payload.payment_request_id = payForm.payment_request_id
    if (payForm.supplier_id) payload.supplier_id = payForm.supplier_id
    // V0.6.2 走 flow API (payment_request_id 放 body)
    if (payForm.payment_request_id) {
      await purchaseFlow.executePayment(payForm.payment_request_id, payload)
    } else {
      // 没有 payment_request_id 时, 退到老的 createPayment
      await purchase.createPayment(payload)
    }
    ElMessage.success('付款成功')
    showPayDialog.value = false
    page.value = 1
    await loadList()
    await loadStats()
  } catch { /* 拦截器已提示 */ }
  finally { paying.value = false }
}

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
.pay-tabs { margin-bottom: 12px; }
:deep(.pay-tabs .el-tabs__nav-wrap::after) { height: 1px; }
:deep(.pay-tabs .el-tabs__item) { font-size: 14px; font-weight: 500; }
:deep(.pay-tabs .el-tabs__item.is-active) { color: #0C447C; }
:deep(.pay-tabs .el-tabs__active-bar) { background-color: #0C447C; }

.link-text { color: #0C447C; cursor: pointer; font-weight: 500; &:hover { text-decoration: underline; } }
.amount-text { color: #A32D2D; font-weight: 700; }
.mono { font-family: 'Consolas', 'Monaco', monospace; }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
.batch-tip { margin-bottom: 12px; }
.reject-summary { background: #fafbfc; padding: 12px; border-radius: 6px; }

.detail-view {
  .detail-row {
    display: flex; padding: 8px 0; border-bottom: 1px dashed #ebeef5;
    .label { width: 110px; color: #909399; font-size: 13px; flex-shrink: 0; }
    .value { flex: 1; color: #303133; font-size: 14px; word-break: break-all; }
    &:last-child { border-bottom: none; }
  }
}
</style>
