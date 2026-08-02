<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">采购合同签订</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="handleReset">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建合同</el-button>
      </div>
    </div>

    <ContractFilterBar
      v-model:search-form="searchForm"
      @search="handleSearch"
      @reset="handleReset"
    />

    <div class="content-card">
      <ContractStatCards :stats="stats" />

      <ContractTable
        :list="pagedList"
        :loading="loading"
        :page="page"
        :page-size="pageSize"
        :total="filteredList.length"
        @view="handleView"
        @view-plan="handleViewPlan"
        @edit="handleEdit"
        @ship="openShipDialog"
        @sign="handleSign"
        @delete="handleDelete"
        @page-change="(p: number) => page = p"
        @size-change="(s: number) => { pageSize = s; page = 1 }"
      />
    </div>

    <!-- 新建 / 编辑 合同 -->
    <el-dialog v-model="showFormDialog" :title="formMode === 'create' ? '新建采购合同' : '编辑采购合同'" width="1500px" :close-on-click-modal="false">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="110px">
        <el-form-item label="合同名称" prop="title">
          <el-input v-model="formData.title" placeholder="如：海康摄像头批量采购合同" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="关联采购计划">
              <el-select v-model="formData.plan_id" placeholder="可选" filterable clearable style="width: 100%">
                <el-option v-for="p in planOptions" :key="p.id" :label="`${p.code}（${p.title}）`" :value="p.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="供应商 ID" prop="supplier_id">
              <el-input-number v-model="formData.supplier_id" :min="1" :step="1" style="width: 100%" placeholder="请输入供应商 ID" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="合同金额" prop="total_amount">
              <el-input-number v-model="formData.total_amount" :min="0" :step="1000" :precision="2" style="width: 100%" placeholder="元" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="签订日期" prop="signed_at">
              <el-date-picker v-model="formData.signed_at" type="date" placeholder="选择签订日期" style="width: 100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="开始日期">
              <el-date-picker v-model="formData.start_date" type="date" placeholder="选择开始日期" style="width: 100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束日期">
              <el-date-picker v-model="formData.end_date" type="date" placeholder="选择结束日期" style="width: 100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="付款条款">
          <el-input v-model="formData.payment_terms" placeholder="如：30%预付，货到60%，质保10%" />
        </el-form-item>
        <el-form-item label="收货地址">
          <el-input v-model="formData.delivery_address" placeholder="如：深圳市南山区科技园 1 号楼" />
        </el-form-item>
        <el-form-item label="签约人">
          <el-input v-model="formData.signer" placeholder="如：王五" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="formData.remark" type="textarea" :rows="3" placeholder="可选" maxlength="500" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showFormDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 发货 Dialog -->
    <el-dialog v-model="showShipDialog" title="合同发货" width="1500px" :close-on-click-modal="false">
      <el-form ref="shipFormRef" :model="shipForm" :rules="shipRules" label-width="110px">
        <el-form-item label="合同编号">
          <span class="highlight">{{ shipTarget?.code }}</span>
        </el-form-item>
        <el-form-item label="合同名称">
          <span>{{ shipTarget?.title }}</span>
        </el-form-item>
        <el-form-item label="物流公司" prop="carrier">
          <el-input v-model="shipForm.carrier" placeholder="如：顺丰速运" />
        </el-form-item>
        <el-form-item label="物流单号">
          <el-input v-model="shipForm.tracking_no" placeholder="可选" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="发货日期" prop="shipped_at">
              <el-date-picker v-model="shipForm.shipped_at" type="date" style="width: 100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="预计到达">
              <el-date-picker v-model="shipForm.expected_arrival_at" type="date" style="width: 100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="收货人">
          <el-input v-model="shipForm.consignee" placeholder="可选" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="shipForm.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showShipDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="confirmShip">确认发货</el-button>
      </template>
    </el-dialog>

    <!-- 详情 Drawer -->
    <el-drawer v-model="showDetailDrawer" title="采购合同详情" size="640px" direction="rtl">
      <div v-if="currentRow" v-loading="detailLoading" class="detail-view">
        <PurchaseFlowStepper entity-type="contract" :entity-status="currentRow.status" />
        <PurchaseFlowHistory entity-type="contract" :entity-id="currentRow.id" />
        <div class="detail-section">
          <div class="section-title">基本信息</div>
          <div class="detail-row"><span class="label">合同编号</span><span class="value highlight">{{ currentRow.code }}</span></div>
          <div class="detail-row"><span class="label">合同名称</span><span class="value">{{ currentRow.title }}</span></div>
          <div class="detail-row"><span class="label">关联采购计划</span><span class="value link-text" @click="handleViewPlan(currentRow)">{{ currentRow.plan?.code || currentRow.plan_id || '-' }}</span></div>
          <div class="detail-row"><span class="label">供应商 ID</span><span class="value">#{{ currentRow.supplier_id }}</span></div>
          <div class="detail-row"><span class="label">签约人</span><span class="value">{{ currentRow.signer || '-' }}</span></div>
          <div class="detail-row"><span class="label">签订日期</span><span class="value">{{ currentRow.signed_at ? String(currentRow.signed_at).slice(0, 10) : '-' }}</span></div>
          <div class="detail-row"><span class="label">开始日期</span><span class="value">{{ currentRow.start_date ? String(currentRow.start_date).slice(0, 10) : '-' }}</span></div>
          <div class="detail-row"><span class="label">结束日期</span><span class="value">{{ currentRow.end_date ? String(currentRow.end_date).slice(0, 10) : '-' }}</span></div>
          <div class="detail-row"><span class="label">状态</span><span class="value"><el-tag :type="statusTagType(currentRow.status)" effect="plain" size="small">{{ statusLabel(currentRow.status) }}</el-tag></span></div>
        </div>

        <div class="detail-section">
          <div class="section-title">付款进度</div>
          <div class="payment-summary">
            <div class="payment-stat">
              <div class="stat-num" style="color:#0C447C">¥ {{ formatMoney(currentRow.total_amount) }}</div>
              <div class="stat-name">合同金额</div>
            </div>
            <div class="payment-stat">
              <div class="stat-num" style="color:#1D9E75">¥ {{ formatMoney(paidAmountOf(currentRow)) }}</div>
              <div class="stat-name">已付金额</div>
            </div>
            <div class="payment-stat">
              <div class="stat-num" style="color:#BA7517">¥ {{ formatMoney(Number(currentRow.total_amount || 0) - paidAmountOf(currentRow)) }}</div>
              <div class="stat-name">未付金额</div>
            </div>
          </div>
          <el-progress :percentage="paidPercent(currentRow)" :stroke-width="14" :text-inside="true" :color="paidPercent(currentRow) >= 100 ? '#1D9E75' : '#0C447C'" style="margin-top:16px" />
        </div>

        <div class="detail-section" v-if="currentRow.payment_terms">
          <div class="section-title">付款条款</div>
          <div class="terms-box">{{ currentRow.payment_terms }}</div>
        </div>

        <div class="detail-section" v-if="currentRow.delivery_address">
          <div class="section-title">收货地址</div>
          <div class="terms-box">{{ currentRow.delivery_address }}</div>
        </div>

        <div class="detail-section" v-if="currentRow.shipments && currentRow.shipments.length">
          <div class="section-title">关联发货单 ({{ currentRow.shipments.length }})</div>
          <el-table :data="currentRow.shipments" size="small" border>
            <el-table-column prop="code" label="发货单号" width="160" />
            <el-table-column prop="carrier" label="物流公司" width="100" />
            <el-table-column prop="tracking_no" label="物流单号" width="160" />
            <el-table-column label="发货日期" width="120">
              <template #default="{ row }">{{ row.shipped_at ? String(row.shipped_at).slice(0, 10) : '-' }}</template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">{{ row.status }}</template>
            </el-table-column>
          </el-table>
        </div>

        <div class="detail-section">
          <div class="section-title">备注</div>
          <div class="terms-box">{{ currentRow.remark || '（无）' }}</div>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, Document, Money, CircleCheck, CircleClose } from '@element-plus/icons-vue'
import { purchase } from '@/api/modules'
import { purchaseFlow } from '@/api/purchase-flow'
import { unwrapList, unwrapStats } from '@/utils/response'
import ContractFilterBar, { type SearchForm } from './components/contract/ContractFilterBar.vue'
import ContractStatCards from './components/contract/ContractStatCards.vue'
import ContractTable from './components/contract/ContractTable.vue'
import PurchaseFlowStepper from '@/components/PurchaseFlowStepper.vue'
import PurchaseFlowHistory from '@/components/PurchaseFlowHistory.vue'

// 发货单
interface ShipmentItem {
  id?: number
  code?: string
  carrier?: string
  tracking_no?: string
  status?: string
  shipped_at?: string
  [key: string]: unknown
}
// 付款申请
interface PaymentRequest {
  id?: number
  status?: string
  amount?: number | string
  [key: string]: unknown
}
// 采购合同 (列表项 / 详情共用)
interface PurchaseContract {
  id: number
  code?: string
  title?: string
  plan_id?: number | null
  plan?: { id?: number; code?: string; [key: string]: unknown }
  project_id?: number | null
  supplier_id?: number | null
  signer?: string
  total_amount?: number | string
  signed_at?: string
  start_date?: string
  end_date?: string
  status?: string
  payment_terms?: string
  delivery_address?: string
  remark?: string
  shipments?: ShipmentItem[]
  paymentRequests?: PaymentRequest[]
  [key: string]: unknown
}
// 采购计划下拉项
interface PlanOption {
  id: number
  code: string
  title: string
}

// === 状态选项（与后端 draft/signed/shipping/completed/cancelled 一致） ===
const statusOptions = [
  { value: 'draft', label: '草稿' },
  { value: 'signed', label: '已签订' },
  { value: 'shipping', label: '运输中' },
  { value: 'completed', label: '已完成' },
  { value: 'cancelled', label: '已取消' }
]

// === 列表 / 加载 ===
const loading = ref(false)
const submitting = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<PurchaseContract[]>([])
const stats = reactive({ draft: 0, signed: 0, shipping: 0, completed: 0, cancelled: 0, total: 0, total_amount: 0 })

const planOptions = ref<PlanOption[]>([])

const searchForm = reactive({ keyword: '', status: '', date_range: [] as string[] })

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter(r => (r.code || '').toLowerCase().includes(kw) || (r.title || '').toLowerCase().includes(kw))
  }
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  if (searchForm.date_range && searchForm.date_range.length === 2) {
    const [start, end] = searchForm.date_range
    arr = arr.filter(r => {
      const d = (r.signed_at || '').slice(0, 10)
      return d && d >= start && d <= end
    })
  }
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const statCards = computed(() => [
  { label: '合同总数', value: stats.total, icon: Document, color: '#0C447C' },
  { label: '已签订', value: stats.signed, icon: CircleCheck, color: '#1D9E75' },
  { label: '合同总金额', value: '¥ ' + formatMoney(stats.total_amount), icon: Money, color: '#BA7517' },
  { label: '运输中', value: stats.shipping, icon: Money, color: '#534AB7' }
])

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 200, page: 1 }
    if (searchForm.keyword) params.keyword = searchForm.keyword
    if (searchForm.status) params.status = searchForm.status
    const res: unknown = await purchase.getContracts(params)
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  try {
    const res: unknown = await purchase.getContractStats()
    Object.assign(stats, unwrapStats(res))
  } catch { /* 静默 */ }
}

const loadPlans = async () => {
  try {
    const { getPurchasePlans } = await import('@/api/modules')
    const res: unknown = await getPurchasePlans({ per_page: 200 })
    planOptions.value = unwrapList(res).map((p: PlanOption) => ({ id: p.id, code: p.code, title: p.title }))
  } catch { planOptions.value = [] }
}

const handleSearch = () => { page.value = 1 }
const handleReset = () => {
  searchForm.keyword = ''
  searchForm.status = ''
  searchForm.date_range = []
  page.value = 1
  loadList()
}

// === 详情 Drawer（调 getContractDetail 拿完整关系）===
const showDetailDrawer = ref(false)
const currentRow = ref<PurchaseContract | null>(null)
const detailLoading = ref(false)
const handleView = async (row: PurchaseContract) => {
  currentRow.value = row
  showDetailDrawer.value = true
  detailLoading.value = true
  try {
    const res: unknown = await purchase.getContractDetail(row.id)
    const c = res as PurchaseContract
    if (c && c.id) currentRow.value = c
  } catch { /* 拦截器已提示 */ }
  finally { detailLoading.value = false }
}
const handleViewPlan = (row: PurchaseContract) => {
  ElMessage.info(`查看采购计划：${row.plan?.code || row.plan_id || '-'}（占位）`)
}

const paidAmountOf = (row: PurchaseContract) => {
  const prs = row.paymentRequests || []
  return prs.filter((p: PaymentRequest) => p.status === 'paid').reduce((s: number, p: PaymentRequest) => s + Number(p.amount || 0), 0)
}
const paidPercent = (row: PurchaseContract) => {
  const total = Number(row.total_amount || 0)
  if (!total) return 0
  return Math.min(100, Math.round((paidAmountOf(row) / total) * 100))
}

// === 新建 / 编辑 ===
const showFormDialog = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const formRef = ref()
const formData = reactive({
  id: 0,
  plan_id: null as number | null,
  project_id: null as number | null,
  supplier_id: null as number | null,
  title: '',
  total_amount: 0,
  signed_at: '',
  start_date: '',
  end_date: '',
  payment_terms: '',
  delivery_address: '',
  signer: '',
  remark: ''
})
const formRules = {
  title: [{ required: true, message: '请输入合同名称', trigger: 'blur' }],
  supplier_id: [{ required: true, message: '请填写供应商 ID', trigger: 'blur' }],
  total_amount: [{ required: true, type: 'number' as const, min: 0, message: '请输入合同金额', trigger: 'blur' }],
  signed_at: [{ required: true, message: '请选择签订日期', trigger: 'change' }]
}

const resetForm = () => {
  Object.assign(formData, {
    id: 0, plan_id: null, project_id: null, supplier_id: null, title: '',
    total_amount: 0, signed_at: '', start_date: '', end_date: '',
    payment_terms: '', delivery_address: '', signer: '', remark: ''
  })
}

const handleAdd = () => {
  formMode.value = 'create'
  resetForm()
  showFormDialog.value = true
}

const handleEdit = (row: PurchaseContract) => {
  if (['shipping', 'completed', 'cancelled'].includes(row.status || '')) {
    ElMessage.warning('已开始运输 / 已完成 / 已取消的合同不可编辑')
    return
  }
  formMode.value = 'edit'
  Object.assign(formData, {
    id: row.id,
    plan_id: row.plan_id || null,
    project_id: row.project_id || null,
    supplier_id: row.supplier_id || null,
    title: row.title || '',
    total_amount: Number(row.total_amount || 0),
    signed_at: row.signed_at ? String(row.signed_at).slice(0, 10) : '',
    start_date: row.start_date ? String(row.start_date).slice(0, 10) : '',
    end_date: row.end_date ? String(row.end_date).slice(0, 10) : '',
    payment_terms: row.payment_terms || '',
    delivery_address: row.delivery_address || '',
    signer: row.signer || '',
    remark: row.remark || ''
  })
  showFormDialog.value = true
}

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  if (formData.end_date && formData.start_date && formData.end_date < formData.start_date) {
    ElMessage.warning('结束日期不能早于开始日期')
    return
  }
  submitting.value = true
  try {
    const payload: Record<string, unknown> = {
      title: formData.title,
      supplier_id: Number(formData.supplier_id) || 0,
      total_amount: Number(formData.total_amount) || 0,
      signed_at: formData.signed_at || null,
      start_date: formData.start_date || null,
      end_date: formData.end_date || null,
      payment_terms: formData.payment_terms || null,
      delivery_address: formData.delivery_address || null,
      signer: formData.signer || null,
      remark: formData.remark || null
    }
    if (formData.plan_id) payload.plan_id = formData.plan_id
    if (formData.project_id) payload.project_id = formData.project_id
    if (formMode.value === 'create') {
      await purchase.createContract(payload)
      ElMessage.success('采购合同创建成功')
    } else {
      await purchase.updateContract(formData.id, payload)
      ElMessage.success('采购合同已更新')
    }
    showFormDialog.value = false
    page.value = 1
    await loadList()
    await loadStats()
  } catch { /* 拦截器已提示 */ }
  finally { submitting.value = false }
}

// === 签署 (走 flow API) ===
const handleSign = async (row: PurchaseContract) => {
  if (row.status === 'signed') {
    ElMessage.warning('合同已签署')
    return
  }
  try {
    await ElMessageBox.confirm(`确认签署采购合同「${row.code}」？签署后不可编辑。`, '签署确认', { type: 'success' })
  } catch { return }
  try {
    await purchaseFlow.signContract(row.id)
    ElMessage.success('合同已签署')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

// === 发货（触发 shipment）===
const showShipDialog = ref(false)
const shipTarget = ref<PurchaseContract | null>(null)
const shipFormRef = ref()
const shipForm = reactive({
  carrier: '',
  tracking_no: '',
  shipped_at: new Date().toISOString().slice(0, 10),
  expected_arrival_at: '',
  consignee: '',
  remark: ''
})
const shipRules = {
  carrier: [{ required: true, message: '请输入物流公司', trigger: 'blur' }],
  shipped_at: [{ required: true, message: '请选择发货日期', trigger: 'change' }]
}

const openShipDialog = (row: PurchaseContract) => {
  if (!['signed', 'shipping'].includes(row.status || '')) {
    ElMessage.warning('只有「已签订」或「运输中」的合同可发货')
    return
  }
  shipTarget.value = row
  Object.assign(shipForm, {
    carrier: '', tracking_no: '',
    shipped_at: new Date().toISOString().slice(0, 10),
    expected_arrival_at: '', consignee: '', remark: ''
  })
  showShipDialog.value = true
}

const confirmShip = async () => {
  const valid = await shipFormRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    await purchaseFlow.createShipment(shipTarget.value!.id, {
      carrier: shipForm.carrier,
      tracking_no: shipForm.tracking_no || null,
      shipped_at: shipForm.shipped_at,
      expected_arrival_at: shipForm.expected_arrival_at || null,
      consignee: shipForm.consignee || null,
      remark: shipForm.remark || null
    })
    ElMessage.success('发货成功，发货单已生成')
    showShipDialog.value = false
    await loadList()
    await loadStats()
  } catch { /* 拦截器已提示 */ }
  finally { submitting.value = false }
}

// === 删除 ===
const handleDelete = async (row: PurchaseContract) => {
  if (row.status === 'completed') {
    ElMessage.warning('已完成的合同不可删除')
    return
  }
  try {
    await ElMessageBox.confirm(
      `确认删除采购合同「${row.code}」？该操作不可恢复。`,
      '删除确认',
      { type: 'warning', confirmButtonText: '确认删除', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    await purchase.deleteContract(row.id)
    ElMessage.success('已删除')
    await loadList()
    await loadStats()
    if (pagedList.value.length === 0 && page.value > 1) page.value -= 1
  } catch { /* 拦截器已提示 */ }
}

// === 工具 ===
const formatMoney = (n?: number | string) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const statusLabel = (s: string | undefined) => statusOptions.find(o => o.value === s)?.label || s || '-'
type ElTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'
const STATUS_TAG_MAP: Record<string, ElTagType> = {
  draft: 'info', signed: 'success', shipping: 'warning', completed: 'primary', cancelled: 'danger',
}
const statusTagType = (s: string | undefined): ElTagType => STATUS_TAG_MAP[s || ''] || 'info'

onMounted(() => { loadList(); loadStats(); loadPlans() })
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
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; &:hover { text-decoration: underline; } }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
.highlight { color: #0C447C; font-weight: 600; }

.paid-cell {
  .paid-text { font-size: 12px; color: #606266; margin-bottom: 4px; }
}

.detail-view {
  padding: 0 4px;
  .detail-section {
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid #ebeef5;
    &:last-child { border-bottom: none; }
  }
  .section-title {
    font-size: 15px; font-weight: 600; color: #0C447C;
    margin-bottom: 12px; padding-left: 10px;
    border-left: 3px solid #0C447C;
  }
  .detail-row {
    display: flex; padding: 8px 0;
    .label { width: 110px; color: #909399; font-size: 13px; }
    .value { flex: 1; color: #303133; font-size: 14px; }
  }
  .payment-summary {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
    .payment-stat {
      background: #f5f7fa; padding: 14px; border-radius: 6px; text-align: center;
      .stat-num { font-size: 18px; font-weight: 700; line-height: 1.3; }
      .stat-name { font-size: 12px; color: #909399; margin-top: 4px; }
    }
  }
  .terms-box {
    background: #f5f7fa; padding: 14px; border-radius: 6px;
    line-height: 1.7; color: #303133; font-size: 13px;
    white-space: pre-wrap; word-break: break-all;
  }
  .file-box {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; background: #f5f7fa; border-radius: 6px;
    border: 1px solid #ebeef5;
    .file-icon { font-size: 28px; color: #0C447C; }
    .file-info { flex: 1; }
    .file-name { font-size: 14px; color: #303133; font-weight: 500; }
    .file-meta { font-size: 12px; color: #909399; margin-top: 2px; }
  }
  .empty-file {
    padding: 24px; text-align: center; color: #909399;
    background: #f5f7fa; border-radius: 6px; font-size: 13px;
  }
}
</style>
