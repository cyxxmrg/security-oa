<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">质保金管理</span>
      <div class="header-actions">
        <ScopeToggle @change="loadList" />
        <el-button :icon="Refresh" plain @click="handleReset">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建质保金</el-button>
      </div>
    </div>

    <!-- KPI 卡片 -->
    <div class="kpi-row">
      <el-card v-for="kpi in kpis" :key="kpi.label" shadow="hover" :body-style="{ padding: '14px 18px' }" class="kpi-card">
        <div class="kpi-label">{{ kpi.label }}</div>
        <div class="kpi-value" :style="{ color: kpi.color }">{{ kpi.value }}</div>
      </el-card>
    </div>

    <!-- 筛选区 -->
    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 140px">
            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="项目">
          <el-input v-model="searchForm.project_id" placeholder="项目ID" clearable style="width: 120px" />
        </el-form-item>
        <el-form-item label="质保期">
          <el-input v-model="searchForm.warranty_id" placeholder="质保期ID" clearable style="width: 120px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="content-card">
      <el-table
        :data="pagedList"
        v-loading="loading"
        stripe
        border
        :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
      >
        <el-table-column prop="id" label="编号" width="60" align="center" />
        <el-table-column label="项目" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.project?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="客户" width="120" show-overflow-tooltip>
          <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="合同金额" width="120" align="right">
          <template #default="{ row }">¥ {{ formatAmount(row.contract_amount) }}</template>
        </el-table-column>
        <el-table-column label="质保金" width="120" align="right">
          <template #default="{ row }"><strong>¥ {{ formatAmount(row.deposit_amount) }}</strong></template>
        </el-table-column>
        <el-table-column label="已释放" width="110" align="right">
          <template #default="{ row }">¥ {{ formatAmount(row.release_amount) }}</template>
        </el-table-column>
        <el-table-column label="已没收" width="110" align="right">
          <template #default="{ row }">¥ {{ formatAmount(row.forfeit_amount) }}</template>
        </el-table-column>
        <el-table-column label="余额" width="120" align="right">
          <template #default="{ row }">
            <strong :style="{ color: balanceColor(row) }">¥ {{ formatAmount(balance(row)) }}</strong>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="plain" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="留置日" width="110" align="center">
          <template #default="{ row }">{{ row.hold_date || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="goDetail(row)">详情</el-button>
            <el-button v-if="['held', 'partial_released'].includes(row.status)" link type="success" :icon="Refresh" @click="showReleaseDialog(row, 'partial')">部分释放</el-button>
            <el-button v-if="['held', 'partial_released'].includes(row.status)" link type="warning" :icon="Check" @click="showReleaseDialog(row, 'full')">全部释放</el-button>
            <el-button v-if="['held', 'partial_released'].includes(row.status)" link type="danger" :icon="CircleClose" @click="showForfeitDialog(row)">没收</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="filteredList.length"
          layout="total, sizes, prev, pager, next, jumper"
          background
        />
      </div>
    </div>

    <!-- 释放 dialog -->
    <el-dialog v-model="releaseDialog.visible" :title="releaseDialog.title" width="500px">
      <el-form :model="releaseDialog.form" label-width="100px">
        <el-form-item v-if="releaseDialog.type === 'partial'" label="释放金额" required>
          <el-input-number v-model="releaseDialog.form.release_amount" :min="0.01" :max="releaseDialog.balance" :precision="2" />
          <span style="margin-left: 8px; color: #909399">余额: ¥ {{ formatAmount(releaseDialog.balance) }}</span>
        </el-form-item>
        <el-form-item label="释放日期" required>
          <el-date-picker v-model="releaseDialog.form.release_date" type="date" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="到账银行" required>
          <el-select v-model="releaseDialog.form.bank_account_id" filterable placeholder="请选择银行账户" style="width:100%">
            <el-option v-for="b in bankAccountOptions" :key="b.id" :value="b.id" :label="`${b.bank_name} - ${b.account_name} (${b.account_no})`">
              <template #default>
                <span>{{ b.bank_name }} - {{ b.account_name }} ({{ b.account_no }})</span>
              </template>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="收款人">
          <el-select v-model="releaseDialog.form.beneficiary_name" filterable allow-create default-first-option placeholder="输入或选择收款人" style="width:100%">
            <el-option v-for="u in employeeOptions" :key="u.id" :label="u.name" :value="u.name" />
          </el-select>
        </el-form-item>
        <el-form-item label="原因" required>
          <el-input v-model="releaseDialog.form.release_reason" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="releaseDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="submitRelease">确认</el-button>
      </template>
    </el-dialog>

    <!-- 没收 dialog -->
    <el-dialog v-model="forfeitDialog.visible" title="没收质保金" width="500px">
      <el-form :model="forfeitDialog.form" label-width="100px">
        <el-form-item label="没收金额" required>
          <el-input-number v-model="forfeitDialog.form.forfeit_amount" :min="0.01" :max="forfeitDialog.balance" :precision="2" />
          <span style="margin-left: 8px; color: #909399">余额: ¥ {{ formatAmount(forfeitDialog.balance) }}</span>
        </el-form-item>
        <el-form-item label="没收日期" required>
          <el-date-picker v-model="forfeitDialog.form.forfeit_date" type="date" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="没收原因" required>
          <el-input v-model="forfeitDialog.form.forfeit_reason" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="forfeitDialog.visible = false">取消</el-button>
        <el-button type="danger" @click="submitForfeit">确认没收</el-button>
      </template>
    </el-dialog>

    <!-- 创建质保金弹窗 -->
    <el-dialog v-model="showCreateDialog" title="新建质保金" width="600px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="createFormRef" :model="createForm" label-width="110px">
        <el-form-item label="项目" required>
          <el-select v-model="createForm.project_id" filterable placeholder="请选择项目" style="width:100%" @change="onProjectChange">
            <el-option v-for="p in projectOptions" :key="p.id" :label="`${p.name || ''}${p.code ? ' ('+p.code+')' : ''}`" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="客户" required>
          <el-select v-model="createForm.customer_id" filterable placeholder="选择项目后自动填充" style="width:100%" :disabled="true">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="合同金额" required>
          <el-input-number v-model="createForm.contract_amount" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item label="质保金比例(%)">
          <el-input-number v-model="createForm.deposit_rate" :min="0" :max="100" :precision="1" style="width:100%" />
          <span style="margin-left:8px;color:#909399">自动计算: ¥{{ autoDepositAmount }}</span>
        </el-form-item>
        <el-form-item label="质保金额">
          <el-input-number v-model="createForm.deposit_amount" :min="0" :precision="2" style="width:100%" placeholder="留空则按比例自动计算" />
        </el-form-item>
        <el-form-item label="留置日期" required>
          <el-date-picker v-model="createForm.hold_date" type="date" value-format="YYYY-MM-DD" style="width:100%" />
        </el-form-item>
        <el-form-item label="预计释放">
          <el-date-picker v-model="createForm.release_date" type="date" value-format="YYYY-MM-DD" style="width:100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="createForm.reason" type="textarea" :rows="2" maxlength="1000" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="createLoading" @click="handleCreateSubmit">保存</el-button>
      </template>
    </el-dialog>

    <!-- 详情弹窗 -->
    <el-dialog v-model="showDetailDialog" title="质保金详情" width="1440px" :close-on-click-modal="false" destroy-on-close>
      <div v-loading="detailLoading" v-if="detailDeposit">
        <el-descriptions :column="3" border>
          <el-descriptions-item label="项目">{{ detailDeposit.project?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="客户">{{ detailDeposit.customer?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="质保期">{{ detailDeposit.warranty?.warranty_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="合同金额">¥ {{ formatAmount(detailDeposit.contract_amount) }}</el-descriptions-item>
          <el-descriptions-item label="质保金比例">{{ detailDeposit.deposit_rate }}%</el-descriptions-item>
          <el-descriptions-item label="质保金">¥ {{ formatAmount(detailDeposit.deposit_amount) }}</el-descriptions-item>
          <el-descriptions-item label="已释放">¥ {{ formatAmount(detailDeposit.release_amount) }}</el-descriptions-item>
          <el-descriptions-item label="已没收">¥ {{ formatAmount(detailDeposit.forfeit_amount) }}</el-descriptions-item>
          <el-descriptions-item label="余额"><strong style="color:#67C23A">¥ {{ formatAmount(balance(detailDeposit)) }}</strong></el-descriptions-item>
          <el-descriptions-item label="留置日期">{{ detailDeposit.hold_date || '-' }}</el-descriptions-item>
          <el-descriptions-item label="预计释放">{{ detailDeposit.expected_release_date || '-' }}</el-descriptions-item>
          <el-descriptions-item label="支付方式">{{ detailDeposit.payment_method || '-' }}</el-descriptions-item>
          <el-descriptions-item label="银行账户" :span="3">{{ detailDeposit.bank_account || '-' }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="3">{{ detailDeposit.notes || detailDeposit.reason || '-' }}</el-descriptions-item>
        </el-descriptions>

        <h4 style="margin-top:20px;margin-bottom:12px;font-size:14px;color:#303133">操作记录</h4>
        <el-table :data="detailDeposit.logs || []" border size="small">
          <el-table-column label="时间" width="160" align="center">
            <template #default="{ row }">{{ row.created_at ? row.created_at.replace('T', ' ').slice(0, 16) : '-' }}</template>
          </el-table-column>
          <el-table-column label="操作类型" width="110" align="center">
            <template #default="{ row }">
              <el-tag :type="row.operation_type === 'forfeit' ? 'danger' : 'success'" size="small" effect="plain">
                {{ row.operation_type === 'partial_release' ? '部分释放' : row.operation_type === 'full_release' ? '全部释放' : '没收' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="金额" width="110" align="right">
            <template #default="{ row }">¥ {{ formatAmount(row.amount) }}</template>
          </el-table-column>
          <el-table-column label="到账银行" min-width="200" show-overflow-tooltip>
            <template #default="{ row }">
              <template v-if="row.bankAccount">
                <template v-if="row.bankAccount.bank_name">{{ row.bankAccount.bank_name }} - {{ row.bankAccount.name }} <span v-if="row.bankAccount.account_no">({{ row.bankAccount.account_no }})</span></template>
                <span v-else>{{ row.bankAccount.name }} <span v-if="row.bankAccount.account_no">({{ row.bankAccount.account_no }})</span></span>
              </template>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column label="收款人" width="100" align="center">
            <template #default="{ row }">{{ row.beneficiary || '-' }}</template>
          </el-table-column>
          <el-table-column label="原因" min-width="160" show-overflow-tooltip>
            <template #default="{ row }">{{ row.reason || '-' }}</template>
          </el-table-column>
        </el-table>
      </div>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus'
import { Plus, Search, Refresh, View, Check, CircleClose } from '@element-plus/icons-vue'
import { warrantyDepositApi } from '@/api/warranty'
import { unwrapList } from '@/utils/response'
import { get } from '@/utils/request'
import ScopeToggle from '@/components/ScopeToggle.vue'
import type { WarrantyDeposit, TagType, ReleaseDialogState, ForfeitDialogState } from './types'

const router = useRouter()

const statusOptions = [
  { value: 'held',             label: '留置中' },
  { value: 'partial_released', label: '部分释放' },
  { value: 'fully_released',   label: '全部释放' },
  { value: 'forfeited',        label: '已没收' },
]

const statusLabel = (s: string) => statusOptions.find(x => x.value === s)?.label || s || '-'
const statusTagType = (s: string): TagType => ({ held: 'info', partial_released: 'warning', fully_released: 'success', forfeited: 'danger' } as Record<string, TagType>)[s] || 'info'

const formatAmount = (v: unknown) => {
  const n = parseFloat(String(v ?? 0)) || 0
  return n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const balance = (row: WarrantyDeposit) => {
  return (parseFloat(String(row.deposit_amount ?? 0)) || 0) - (parseFloat(String(row.release_amount ?? 0)) || 0) - (parseFloat(String(row.forfeit_amount ?? 0)) || 0)
}
const balanceColor = (row: WarrantyDeposit) => {
  const b = balance(row)
  if (b <= 0) return '#909399'
  return '#67C23A'
}

const loading = ref(false)
const list = ref<WarrantyDeposit[]>([])
const page = ref(1)
const pageSize = ref(10)

const employeeOptions = ref<{ id: number; name: string }[]>([])
const bankAccountOptions = ref<{ id: number; bank_name: string; account_name: string; account_no: string }[]>([])

// 详情弹窗
const showDetailDialog = ref(false)
const detailDeposit = ref<WarrantyDeposit | null>(null)
const detailLoading = ref(false)

// 新建质保金弹窗
const showCreateDialog = ref(false)
const createLoading = ref(false)
const createFormRef = ref<FormInstance | null>(null)
const projectOptions = ref<{ id: number; name: string; code?: string; customer?: { id: number; name: string } }[]>([])
const customerOptions = ref<{ id: number; name: string }[]>([])
const createForm = reactive({
  project_id: null as number | null,
  customer_id: null as number | null,
  contract_amount: 0,
  deposit_rate: 5,
  deposit_amount: 0,
  hold_date: new Date().toISOString().slice(0, 10),
  release_date: '',
  reason: '',
})
const autoDepositAmount = computed(() => {
  if (createForm.contract_amount && createForm.deposit_rate) {
    return (createForm.contract_amount * createForm.deposit_rate / 100).toFixed(2)
  }
  return '0.00'
})

const loadProjectOptions = async () => {
  try {
    const res = await get('/projects', { per_page: 500 })
    const body = res as { data?: { data?: unknown[] } }
    const arr = body?.data?.data || body?.data || []
    projectOptions.value = arr as { id: number; name: string; code?: string; customer?: { id: number; name: string } }[]
  } catch { projectOptions.value = [] }
}

const onProjectChange = (val: number) => {
  const p = projectOptions.value.find(x => x.id === val)
  if (p?.customer) {
    createForm.customer_id = p.customer.id
    customerOptions.value = [p.customer]
  }
}

const handleCreateSubmit = async () => {
  if (!createForm.project_id || !createForm.customer_id) {
    ElMessage.warning('请选择项目（自动填充客户）')
    return
  }
  if (!createForm.contract_amount || createForm.contract_amount <= 0) {
    ElMessage.warning('请填写合同金额')
    return
  }
  createLoading.value = true
  try {
    const payload: Record<string, unknown> = {
      project_id: createForm.project_id,
      customer_id: createForm.customer_id,
      contract_amount: createForm.contract_amount,
      hold_date: createForm.hold_date,
    }
    if (createForm.deposit_amount > 0) payload.deposit_amount = createForm.deposit_amount
    if (createForm.release_date) payload.release_date = createForm.release_date
    if (createForm.reason) payload.reason = createForm.reason
    await warrantyDepositApi.create(payload)
    ElMessage.success('质保金已创建')
    showCreateDialog.value = false
    loadList()
  } catch { /* 拦截器已提示 */ }
  finally { createLoading.value = false }
}

const searchForm = reactive({ status: '', project_id: '', warranty_id: '' })

const kpis = computed(() => {
  const total = list.value.length
  const held = list.value.filter(x => x.status === 'held').length
  const totalAmount = list.value.reduce((s, x) => s + (parseFloat(String(x.deposit_amount ?? 0)) || 0), 0)
  const released = list.value.reduce((s, x) => s + (parseFloat(String(x.release_amount ?? 0)) || 0), 0)
  return [
    { label: '质保金笔数', value: total, color: '#409EFF' },
    { label: '留置中',     value: held,  color: '#909399' },
    { label: '总额 (¥)',   value: totalAmount.toLocaleString('zh-CN', { maximumFractionDigits: 0 }), color: '#67C23A' },
    { label: '已释放 (¥)', value: released.toLocaleString('zh-CN', { maximumFractionDigits: 0 }), color: '#E6A23C' },
  ]
})

const filteredList = computed(() => {
  return list.value.filter(x => {
    if (searchForm.status && x.status !== searchForm.status) return false
    if (searchForm.project_id && String(x.project_id) !== String(searchForm.project_id)) return false
    if (searchForm.warranty_id && String(x.warranty_id) !== String(searchForm.warranty_id)) return false
    return true
  })
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

async function loadList() {
  loading.value = true
  try {
    const res = await warrantyDepositApi.list({ per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    list.value = unwrapList(res)
  } catch (e: unknown) {
    ElMessage.error('加载质保金失败: ' + ((e as { message?: string })?.message || 'unknown'))
    list.value = []
  } finally {
    loading.value = false
  }
}

function handleSearch() { page.value = 1 }
function handleReset() {
  Object.assign(searchForm, { status: '', project_id: '', warranty_id: '' })
  page.value = 1
  loadList()
}

function handleAdd() {
  createForm.project_id = null
  createForm.customer_id = null
  createForm.contract_amount = 0
  createForm.deposit_rate = 5
  createForm.deposit_amount = 0
  createForm.hold_date = new Date().toISOString().slice(0, 10)
  createForm.release_date = ''
  createForm.reason = ''
  showCreateDialog.value = true
}

function goDetail(row: WarrantyDeposit) {
  detailDeposit.value = null
  showDetailDialog.value = true
  detailLoading.value = true
  warrantyDepositApi.show(row.id!).then(res => {
    detailDeposit.value = (res as Record<string, unknown>)?.data as WarrantyDeposit || res as WarrantyDeposit
  }).catch(() => {}).finally(() => { detailLoading.value = false })
}

const releaseDialog = reactive<ReleaseDialogState>({
  visible: false, title: '', type: 'partial',
  target: null, balance: 0,
  form: { release_amount: 0, release_date: new Date().toISOString().slice(0, 10), release_reason: '', beneficiary_name: '', bank_account_id: null as number | null },
})

function showReleaseDialog(row: WarrantyDeposit, type: 'partial' | 'full') {
  releaseDialog.type = type
  releaseDialog.title = type === 'partial' ? '部分释放' : '全部释放'
  releaseDialog.target = row
  releaseDialog.balance = balance(row)
  releaseDialog.form = {
    release_amount: type === 'full' ? releaseDialog.balance : 0,
    release_date: new Date().toISOString().slice(0, 10),
    release_reason: '',
    beneficiary_name: '',
    bank_account_id: null,
  }
  releaseDialog.visible = true
}

async function submitRelease() {
  if (!releaseDialog.form.release_reason) {
    ElMessage.warning('请填写原因')
    return
  }
  if (!releaseDialog.form.bank_account_id) {
    ElMessage.warning('请选择到账银行')
    return
  }
  if (releaseDialog.type === 'partial' && (!releaseDialog.form.release_amount || releaseDialog.form.release_amount <= 0)) {
    ElMessage.warning('请填写释放金额')
    return
  }
  const target = releaseDialog.target
  if (!target) return
  try {
    if (releaseDialog.type === 'partial') {
      await warrantyDepositApi.partialRelease(target.id!, {
        amount: releaseDialog.form.release_amount,
        reason: releaseDialog.form.release_reason,
        bank_account_id: releaseDialog.form.bank_account_id,
        beneficiary: releaseDialog.form.beneficiary_name || null,
      })
    } else {
      await warrantyDepositApi.fullRelease(target.id!, {
        bank_account_id: releaseDialog.form.bank_account_id,
        beneficiary: releaseDialog.form.beneficiary_name || null,
      })
    }
    ElMessage.success(releaseDialog.type === 'partial' ? '部分释放成功' : '全部释放成功')
    releaseDialog.visible = false
    loadList()
  } catch { /* 拦截器已提示 */ }
}

const forfeitDialog = reactive<ForfeitDialogState>({
  visible: false, target: null, balance: 0,
  form: { forfeit_amount: 0, forfeit_date: new Date().toISOString().slice(0, 10), forfeit_reason: '' },
})

function showForfeitDialog(row: WarrantyDeposit) {
  forfeitDialog.target = row
  forfeitDialog.balance = balance(row)
  forfeitDialog.form = {
    forfeit_amount: forfeitDialog.balance,
    forfeit_date: new Date().toISOString().slice(0, 10),
    forfeit_reason: '',
  }
  forfeitDialog.visible = true
}

async function submitForfeit() {
  if (!forfeitDialog.form.forfeit_reason) {
    ElMessage.warning('请填写没收原因')
    return
  }
  if (!forfeitDialog.form.forfeit_amount || forfeitDialog.form.forfeit_amount <= 0) {
    ElMessage.warning('请填写没收金额')
    return
  }
  const target = forfeitDialog.target
  if (!target) return
  try {
    await warrantyDepositApi.forfeit(target.id!, {
      amount: forfeitDialog.form.forfeit_amount,
      reason: forfeitDialog.form.forfeit_reason,
    })
    ElMessage.success('已没收')
    forfeitDialog.visible = false
    loadList()
  } catch { /* 拦截器已提示 */ }
}

async function loadEmployees() {
  try {
    const res = await get('/users', { per_page: 500 })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    employeeOptions.value = (d as { id: number; name: string }[]) || []
    // 默认收款人为当前登录用户
    try {
      const info = JSON.parse(localStorage.getItem('oa_user_info') || '{}')
      if (info.name) releaseDialog.form.beneficiary_name = info.name
    } catch {}
  } catch { employeeOptions.value = [] }
}

async function loadBankAccounts() {
  try {
    const res = await get('/finance/accounts', { per_page: 200 })
    // API 返回分页格式: {code, data: {current_page, data: [...]}}
    const body = res as { data?: { data?: unknown[] } }
    const arr = body?.data?.data || body?.data || []
    bankAccountOptions.value = (arr as { id: number; name: string; bank_name?: string; account_no?: string; status?: string }[])
      .filter(a => a.status === 'active' || !a.status)
      .map(a => ({
        id: a.id,
        bank_name: a.bank_name || a.name,
        account_name: a.name,
        account_no: a.account_no || '',
      }))
  } catch { bankAccountOptions.value = [] }
}

onMounted(() => {
  loadList()
  loadEmployees()
  loadBankAccounts()
  loadProjectOptions()
})
</script>
