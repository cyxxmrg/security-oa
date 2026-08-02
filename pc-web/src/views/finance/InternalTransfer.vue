<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">内部转账明细</span>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleCreate">新增内部转账</el-button>
      </div>
    </div>

    <!-- KPI 概览 -->
    <el-row :gutter="16" class="kpi-row">
      <el-col :xs="12" :sm="8">
        <div class="kpi-card">
          <div class="kpi-label">转账总笔数</div>
          <div class="kpi-value">{{ stats.count || 0 }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8">
        <div class="kpi-card primary">
          <div class="kpi-label">转账总金额</div>
          <div class="kpi-value">¥{{ formatMoney(stats.total_balance) }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8">
        <div class="kpi-card success">
          <div class="kpi-label">当前页金额</div>
          <div class="kpi-value">¥{{ formatMoney(currentPageTotal) }}</div>
        </div>
      </el-col>
    </el-row>

    <!-- 筛选 -->
    <div class="filter-bar">
      <el-input v-model="filter.keyword" placeholder="转账单号 / 备注 / 凭证号" clearable style="width: 240px" @keyup.enter="loadList(1)" @clear="loadList(1)" />
      <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width:240px" @change="loadList(1)" />
      <el-button type="primary" :icon="Search" @click="loadList(1)">查询</el-button>
      <el-button @click="resetFilter">重置</el-button>
    </div>

    <!-- 转账明细列表 -->
    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width:100%">
        <el-table-column type="index" label="#" width="50" align="center" />
        <el-table-column label="转账单号" width="180">
          <template #default="{ row }"><span class="record-no">{{ row.transfer_group_id }}</span></template>
        </el-table-column>
        <el-table-column label="转账日期" width="120" align="center">
          <template #default="{ row }">{{ formatDate(row.payment_date) }}</template>
        </el-table-column>
        <el-table-column label="转出账户" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <el-icon style="color:#A32D2D;vertical-align:middle;margin-right:4px"><Top /></el-icon>
            <span v-if="row.from_account">{{ row.from_account.name }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="目标账户" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <el-icon style="color:#1D9E75;vertical-align:middle;margin-right:4px"><Bottom /></el-icon>
            <span v-if="row.to_account">{{ row.to_account.name }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="140" align="right">
          <template #default="{ row }">
            <span style="font-weight:600;color:#0C447C">¥ {{ formatMoney(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="经办人" width="100">
          <template #default="{ row }">{{ row.operator || '-' }}</template>
        </el-table-column>
        <el-table-column label="凭证号" width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ row.voucher_no || '-' }}</template>
        </el-table-column>
        <el-table-column label="备注" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.remark || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="70" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openDetail(row.transfer_group_id)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination background layout="total,prev,pager,next" :total="pagination.total" :current-page="pagination.page" :page-size="pagination.per_page" @current-change="(p: number) => loadList(p)" />
      </div>
    </div>

    <!-- 新增内部转账 dialog -->
    <el-dialog v-model="showForm" title="新增内部转账" width="640px" :close-on-click-modal="false">
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
        <el-form-item label="转出账户" prop="from_account_id">
          <el-select v-model="form.from_account_id" placeholder="选择转出账户" filterable style="width:100%">
            <el-option v-for="a in accountOptions" :key="a.id" :label="`${a.name}（余额 ¥${formatMoney(a.balance)}）`" :value="a.id" :disabled="a.id === form.to_account_id" />
          </el-select>
        </el-form-item>
        <el-form-item label="目标账户" prop="to_account_id">
          <el-select v-model="form.to_account_id" placeholder="选择目标账户" filterable style="width:100%">
            <el-option v-for="a in accountOptions" :key="a.id" :label="`${a.name}（余额 ¥${formatMoney(a.balance)}）`" :value="a.id" :disabled="a.id === form.from_account_id" />
          </el-select>
        </el-form-item>
        <el-form-item label="转账金额" prop="amount">
          <el-input-number v-model="form.amount" :precision="2" :min="0.01" :step="100" style="width:100%" placeholder="请输入金额" />
        </el-form-item>
        <el-form-item label="转账日期" prop="payment_date">
          <el-date-picker v-model="form.payment_date" type="date" value-format="YYYY-MM-DD" placeholder="选择转账日期" style="width:100%" />
        </el-form-item>
        <el-form-item label="凭证号">
          <el-input v-model="form.voucher_no" placeholder="如：TR-2026-001" />
        </el-form-item>
        <el-form-item label="经办人">
          <el-input v-model="form.operator" placeholder="默认当前登录用户" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="例如: 项目调拨回款" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showForm = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSave">确认转账</el-button>
      </template>
    </el-dialog>

    <!-- 详情 dialog -->
    <el-dialog v-model="showDetail" title="内部转账详情" width="800px" :close-on-click-modal="false">
      <template v-if="detailItem">
        <el-descriptions :column="3" border>
          <el-descriptions-item label="转账单号" :span="2">
            <span class="record-no">{{ detailItem.transfer_group_id }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="转账日期">{{ formatDate(detailItem.payment_date) }}</el-descriptions-item>
          <el-descriptions-item label="转出账户" :span="3">
            <el-tag type="danger" size="small">
              {{ detailItem.from_account?.name || '-' }}
              <span v-if="detailItem.from_account?.balance_after != null">（当前余额 ¥{{ formatMoney(detailItem.from_account.balance_after) }}）</span>
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="目标账户" :span="3">
            <el-tag type="success" size="small">
              {{ detailItem.to_account?.name || '-' }}
              <span v-if="detailItem.to_account?.balance_after != null">（当前余额 ¥{{ formatMoney(detailItem.to_account.balance_after) }}）</span>
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="转账金额" :span="3">
            <span style="font-weight:700;color:#0C447C;font-size:16px">¥ {{ formatMoney(detailItem.amount) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="经办人">{{ detailItem.operator || '-' }}</el-descriptions-item>
          <el-descriptions-item label="凭证号">{{ detailItem.voucher_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="方式">{{ detailItem.method || '内部转账' }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="3">{{ detailItem.remark || '-' }}</el-descriptions-item>
          <el-descriptions-item label="创建时间" :span="3">{{ formatDate(detailItem.created_at) }}</el-descriptions-item>
        </el-descriptions>
        <h4 style="margin:16px 0 8px;color:#0C447C">分录明细</h4>
        <el-table :data="detailItem.items || []" stripe border style="width:100%">
          <el-table-column type="index" label="#" width="50" />
          <el-table-column label="账户" min-width="160">
            <template #default="{ row }">{{ row.account_name }}</template>
          </el-table-column>
          <el-table-column label="方向" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="row.direction === 'out' ? 'danger' : 'success'" size="small">
                {{ row.direction === 'out' ? '转出' : '转入' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="金额" width="160" align="right">
            <template #default="{ row }">
              <span :style="{ color: row.amount < 0 ? '#A32D2D' : '#1D9E75', fontWeight: 600 }">
                {{ row.amount < 0 ? '-' : '+' }}¥ {{ formatMoney(Math.abs(row.amount)) }}
              </span>
            </template>
          </el-table-column>
        </el-table>
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
import { Plus, Refresh, Search, Top, Bottom } from '@element-plus/icons-vue'
import { getInternalTransfers, getInternalTransferDetail, getFinanceAccounts, createFinanceTransfer } from '@/api/modules'

interface AccountOption { id: number; name: string; balance: number }
interface TransferRow {
  transfer_group_id: string
  payment_date?: string
  from_account?: { id: number; name: string } | null
  to_account?: { id: number; name: string } | null
  amount: number
  operator?: string
  voucher_no?: string
  remark?: string
  item_count?: number
  [k: string]: unknown
}
interface TransferDetail {
  transfer_group_id: string
  payment_date?: string
  from_account?: { id: number; name: string; balance_after?: number } | null
  to_account?: { id: number; name: string; balance_after?: number } | null
  amount: number
  method?: string
  operator?: string
  voucher_no?: string
  remark?: string
  created_at?: string
  items?: Array<{ id: number; account_id: number; account_name: string; amount: number; direction: 'in' | 'out' }>
}

const loading = ref(false)
const submitting = ref(false)
const list = ref<TransferRow[]>([])
const pagination = reactive({ page: 1, per_page: 15, total: 0 })
const dateRange = ref<[string, string] | null>(null)
const filter = reactive({ keyword: '' })
const stats = reactive<Record<string, number>>({})

const accountOptions = ref<AccountOption[]>([])

// 详情
const showDetail = ref(false)
const detailItem = ref<TransferDetail | null>(null)

// 表单
const showForm = ref(false)
const formRef = ref<FormInstance | null>(null)
const form = reactive({
  from_account_id: null as number | null,
  to_account_id: null as number | null,
  amount: 0,
  payment_date: new Date().toISOString().slice(0, 10),
  voucher_no: '',
  operator: '',
  remark: '',
})
const formRules: FormRules = {
  from_account_id: [{ required: true, message: '请选择转出账户', trigger: 'change' }],
  to_account_id:   [{ required: true, message: '请选择目标账户', trigger: 'change' }],
  amount:          [{ required: true, message: '请输入转账金额', trigger: 'blur' }],
  payment_date:    [{ required: true, message: '请选择转账日期', trigger: 'change' }],
}

const currentPageTotal = computed(() => list.value.reduce((s, r) => s + Number(r.amount || 0), 0))

function formatMoney(n: number | string | undefined | null): string {
  if (n == null) return '0.00'
  return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

function formatDate(s?: string | null) {
  if (!s) return '-'
  const d = new Date(s)
  if (isNaN(d.getTime())) return String(s).slice(0, 10)
  const pad = (n: number) => n.toString().padStart(2, '0')
  return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
}

async function loadAccounts() {
  try {
    const res = await getFinanceAccounts({ per_page: 200 })
    const data = (res as { data?: unknown })?.data
    let items: AccountOption[] = []
    if (Array.isArray(data)) items = data as AccountOption[]
    else if (Array.isArray((data as { data?: unknown })?.data)) items = (data as { data: AccountOption[] }).data
    accountOptions.value = items
  } catch (e) {
    console.warn('[loadAccounts]', e)
    accountOptions.value = []
  }
}

async function loadList(page = 1) {
  pagination.page = page
  loading.value = true
  try {
    const params: Record<string, unknown> = { page, per_page: pagination.per_page }
    if (filter.keyword) params.keyword = filter.keyword
    if (dateRange.value) { params.date_from = dateRange.value[0]; params.date_to = dateRange.value[1] }
    const res = await getInternalTransfers(params)
    const data = (res as { data?: { data?: TransferRow[]; total?: number }; stats?: Record<string, number> })?.data
    list.value = data?.data || []
    pagination.total = data?.total || 0
    const s = (res as { stats?: Record<string, number> })?.stats
    if (s) Object.assign(stats, s)
  } catch (e) {
    console.error('[loadList]', e)
    list.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

function resetFilter() {
  filter.keyword = ''
  dateRange.value = null
  loadList(1)
}

function handleCreate() {
  Object.assign(form, {
    from_account_id: null, to_account_id: null, amount: 0,
    payment_date: new Date().toISOString().slice(0, 10),
    voucher_no: '', operator: '', remark: '',
  })
  showForm.value = true
}

async function handleSave() {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  submitting.value = true
  try {
    await createFinanceTransfer({
      from_account_id: form.from_account_id,
      to_account_id: form.to_account_id,
      amount: form.amount,
      payment_date: form.payment_date,
      voucher_no: form.voucher_no || undefined,
      operator: form.operator || undefined,
      remark: form.remark || undefined,
    })
    ElMessage.success('转账成功')
    showForm.value = false
    loadList(1)
    loadAccounts() // 刷新余额显示
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '转账失败')
  } finally {
    submitting.value = false
  }
}

async function openDetail(groupId: string) {
  detailItem.value = null
  showDetail.value = true
  try {
    const res = await getInternalTransferDetail(groupId)
    detailItem.value = ((res as { data?: TransferDetail })?.data) || null
  } catch (e) {
    console.error(e)
    showDetail.value = false
    ElMessage.error('加载详情失败')
  }
}

onMounted(() => {
  loadAccounts()
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
.kpi-card.primary { border-left-color: #409EFF; }
.kpi-card.success { border-left-color: #67C23A; }
.kpi-label { font-size: 13px; color: #909399; margin-bottom: 6px; }
.kpi-value { font-size: 22px; font-weight: 700; color: #303133; }
.filter-bar {
  display: flex; gap: 12px; align-items: center;
  background: #fff; padding: 14px 20px; border-radius: 8px;
  margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.content-card { background: #fff; padding: 16px 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.record-no { font-family: "DIN Pro", monospace; font-weight: 500; color: #0C447C; }
.muted { color: #c0c4cc; }
.pagination-wrap { margin-top: 12px; display: flex; justify-content: flex-end; }
</style>