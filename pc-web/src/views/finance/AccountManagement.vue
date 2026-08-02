<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">资金账户管理</span>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadAll">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新增账户</el-button>
      </div>
    </div>

    <!-- KPI 概览 -->
    <el-row :gutter="16" class="kpi-row">
      <el-col :xs="12" :sm="6">
        <div class="kpi-card">
          <div class="kpi-label">账户总数</div>
          <div class="kpi-value">{{ stats.count || 0 }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6">
        <div class="kpi-card primary">
          <div class="kpi-label">启用账户</div>
          <div class="kpi-value">{{ stats.active_count || 0 }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6">
        <div class="kpi-card success">
          <div class="kpi-label">总余额</div>
          <div class="kpi-value">¥{{ formatMoney(stats.total_balance) }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6">
        <div class="kpi-card warning">
          <div class="kpi-label">本月交易笔数</div>
          <div class="kpi-value">{{ monthlyTxnCount }}</div>
        </div>
      </el-col>
    </el-row>

    <!-- 筛选 -->
    <div class="filter-bar">
      <el-input v-model="filter.keyword" placeholder="账户名 / 开户行 / 账号" clearable style="width: 280px" @keyup.enter="loadAll" />
      <el-select v-model="filter.type" placeholder="账户类型" clearable style="width: 140px">
        <el-option v-for="t in typeOptions" :key="t.value" :label="t.label" :value="t.value" />
      </el-select>
      <el-select v-model="filter.status" placeholder="状态" clearable style="width: 120px">
        <el-option label="启用" value="active" />
        <el-option label="冻结" value="frozen" />
        <el-option label="已注销" value="closed" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="loadAll">查询</el-button>
      <el-button @click="resetFilter">重置</el-button>
    </div>

    <!-- 账户列表 -->
    <div class="content-card">
      <el-table :data="filteredAccounts" border stripe v-loading="loading" height="auto">
        <el-table-column type="index" width="50" align="center" />
        <el-table-column label="账户类型" width="120">
          <template #default="{ row }">
            <el-tag :type="typeTagType(row.type)" effect="plain">
              <el-icon class="mr-4"><component :is="typeIcon(row.type)" /></el-icon>
              {{ typeLabel(row.type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="账户名称" min-width="160" />
        <el-table-column prop="account_no" label="账号" min-width="200" />
        <el-table-column prop="bank_name" label="开户行" min-width="160" show-overflow-tooltip />
        <el-table-column label="余额" width="140" align="right">
          <template #default="{ row }">
            <span :class="row.balance >= 0 ? 'balance-positive' : 'balance-negative'">
              ¥ {{ formatMoney(row.balance) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="currency" label="币种" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="dark" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
        <el-table-column label="操作" width="260" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="viewTransactions(row)">流水</el-button>
            <el-button link type="primary" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" size="small" @click="adjustAccount(row)">调账</el-button>
            <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 新增/编辑账户 Dialog -->
    <el-dialog v-model="showForm" :title="form.id ? '编辑账户' : '新增账户'" width="600px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
        <el-form-item label="账户名称" prop="name">
          <el-input v-model="form.name" placeholder="如：招商银行基本户" />
        </el-form-item>
        <el-form-item label="账户类型" prop="type">
          <el-select v-model="form.type" placeholder="请选择" style="width: 100%">
            <el-option v-for="t in typeOptions" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="账号">
          <el-input v-model="form.account_no" placeholder="银行账号 / 支付宝账号" />
        </el-form-item>
        <el-form-item label="开户行">
          <el-input v-model="form.bank_name" placeholder="如：招商银行深圳分行" />
        </el-form-item>
        <el-form-item v-if="!form.id" label="初始余额">
          <el-input-number v-model="form.balance" :precision="2" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="币种">
          <el-select v-model="form.currency" style="width: 100%">
            <el-option label="人民币 CNY" value="CNY" />
            <el-option label="美元 USD" value="USD" />
            <el-option label="欧元 EUR" value="EUR" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio value="active">启用</el-radio>
            <el-radio value="frozen">冻结</el-radio>
            <el-radio value="closed">已注销</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showForm = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 账户流水 Dialog -->
    <el-dialog v-model="showTxnDialog" :title="`账户流水 - ${txnAccount?.name || ''}`" width="1000px" destroy-on-close>
      <el-table :data="txnList" border stripe v-loading="txnLoading" height="500">
        <el-table-column prop="payment_date" label="日期" width="110" />
        <el-table-column label="方式" width="80">
          <template #default="{ row }">{{ methodLabel(row.method) }}</template>
        </el-table-column>
        <el-table-column label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.receivable_id ? 'success' : 'warning'" size="small">
              {{ row.receivable_id ? '收款' : '付款' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="金额" min-width="120" align="right">
          <template #default="{ row }">¥{{ formatMoney(row.amount) }}</template>
        </el-table-column>
        <el-table-column prop="voucher_no" label="凭证号" min-width="140" />
        <el-table-column prop="operator" label="经办人" width="100" />
        <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import { Plus, Refresh, Search, Wallet, Money, CreditCard, ChatDotRound } from '@element-plus/icons-vue'
import { get, post, put, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import { paymentMethodLabel } from '@/utils/labels'
import type { FinanceAccount, AccountStats, AccountTransaction } from './types'

const methodLabel = (m: string) => paymentMethodLabel(m)

const loading = ref(false)
const submitting = ref(false)
const accounts = ref<FinanceAccount[]>([])
const stats = ref<AccountStats>({})

const filter = reactive({ keyword: '', type: '', status: '' })

const typeOptions = [
  { value: 'bank',    label: '银行账户' },
  { value: 'cash',    label: '现金' },
  { value: 'alipay',  label: '支付宝' },
  { value: 'wechat',  label: '微信' },
  { value: 'other',   label: '其他' },
]
const typeLabel = (t: string) => typeOptions.find(x => x.value === t)?.label || t
const typeIcon = (t: string) => {
  if (t === 'bank')   return CreditCard
  if (t === 'cash')   return Money
  if (t === 'alipay') return Wallet
  if (t === 'wechat') return ChatDotRound
  return Wallet
}
const typeTagType = (t: string): 'primary' | 'success' | 'warning' | 'info' => {
  if (t === 'bank')   return 'primary'
  if (t === 'cash')   return 'warning'
  if (t === 'alipay') return 'success'
  if (t === 'wechat') return 'success'
  return 'info'
}
const statusLabel = (s: string) => ({ active: '启用', frozen: '冻结', closed: '已注销' }[s] || s)
const statusTagType = (s: string): 'success' | 'info' | 'danger' => {
  if (s === 'active') return 'success'
  if (s === 'frozen') return 'info'
  return 'danger'
}

const formatMoney = (n: number | string | undefined | null): string => {
  if (n == null) return '0.00'
  return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

const filteredAccounts = computed(() => {
  let list = accounts.value
  if (filter.keyword) {
    const k = filter.keyword.toLowerCase()
    list = list.filter(a =>
      (a.name || '').toLowerCase().includes(k) ||
      (a.bank_name || '').toLowerCase().includes(k) ||
      (a.account_no || '').toLowerCase().includes(k)
    )
  }
  if (filter.type)   list = list.filter(a => a.type === filter.type)
  if (filter.status) list = list.filter(a => a.status === filter.status)
  return list
})

const monthlyTxnCount = ref(0)

const loadAll = async () => {
  loading.value = true
  try {
    const res = await get('/finance/accounts', { per_page: 100 })
    accounts.value = unwrapList(res)
    stats.value = res?.stats || res?.data?.stats || {}
    // V1.2.16 fix: 本月交易笔数已由后端 stats.monthly_txn_count 直接返回，避免逐账户循环 N 次
    monthlyTxnCount.value = (res?.stats?.monthly_txn_count || res?.data?.stats?.monthly_txn_count || 0) as number
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + ((e as { message?: string })?.message || ''))
    accounts.value = []
  } finally { loading.value = false }
}

const resetFilter = () => {
  filter.keyword = ''; filter.type = ''; filter.status = ''
  loadAll()
}

// ============ Form ============
const showForm = ref(false)
const formRef = ref<FormInstance | null>(null)
const form = reactive({
  id: null as number | null,
  name: '', type: 'bank', account_no: '', bank_name: '',
  balance: 0, currency: 'CNY', status: 'active', remark: '',
})
const formRules: FormRules = {
  name: [{ required: true, message: '请输入账户名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
}

const handleAdd = () => {
  Object.assign(form, { id: null, name: '', type: 'bank', account_no: '', bank_name: '', balance: 0, currency: 'CNY', status: 'active', remark: '' })
  showForm.value = true
}
const handleEdit = (row: FinanceAccount) => {
  Object.assign(form, { ...row, id: row.id })
  showForm.value = true
}
const handleSave = async () => {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  submitting.value = true
  try {
    if (form.id) {
      await put(`/finance/accounts/${form.id}`, form)
      ElMessage.success('已更新')
    } else {
      await post('/finance/accounts', form)
      ElMessage.success('已新增')
    }
    showForm.value = false
    loadAll()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '保存失败')
  } finally { submitting.value = false }
}

const handleDelete = async (row: FinanceAccount) => {
  try {
    await ElMessageBox.confirm(`确定删除账户「${row.name}」？`, '删除确认', { type: 'warning' })
    await del(`/finance/accounts/${row.id}`)
    ElMessage.success('已删除')
    loadAll()
  } catch (e: unknown) {
    if (e !== 'cancel') ElMessage.error((e as { message?: string })?.message || '删除失败')
  }
}

const adjustAccount = async (row: FinanceAccount) => {
  try {
    const { value } = await ElMessageBox.prompt('请输入调整金额（正数加，负数减）', '账户调账', {
      inputPattern: /^-?\d+(\.\d+)?$/, inputErrorMessage: '请输入有效数字',
    })
    const delta = parseFloat(value)
    // 通过 put 调账 (后端会校验)
    const newBalance = Number(row.balance || 0) + delta
    await put(`/finance/accounts/${row.id}`, { balance: newBalance, remark: `${row.remark || ''} | 调账${delta > 0 ? '+' : ''}${delta}` })
    ElMessage.success(`调账成功: ${delta > 0 ? '+' : ''}${delta}`)
    loadAll()
  } catch {}
}

// ============ Transactions ============
const showTxnDialog = ref(false)
const txnLoading = ref(false)
const txnList = ref<AccountTransaction[]>([])
const txnAccount = ref<FinanceAccount | null>(null)
const viewTransactions = async (row: FinanceAccount) => {
  txnAccount.value = row
  showTxnDialog.value = true
  txnLoading.value = true
  try {
    const r = await get(`/finance/accounts/${row.id}/transactions`, { per_page: 100 })
    let list: AccountTransaction[] = []
    if (Array.isArray(r)) list = r
    else if (Array.isArray(r?.data)) list = r.data
    else if (Array.isArray(r?.data?.data)) list = r.data.data
    txnList.value = list
  } catch (e: unknown) {
    ElMessage.error('流水加载失败')
    txnList.value = []
  } finally { txnLoading.value = false }
}

onMounted(loadAll)
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
.kpi-card.warning { border-left-color: #E6A23C; }
.kpi-label { font-size: 13px; color: #909399; margin-bottom: 6px; }
.kpi-value { font-size: 22px; font-weight: 700; color: #303133; }
.filter-bar {
  display: flex; gap: 12px; align-items: center;
  background: #fff; padding: 14px 20px; border-radius: 8px;
  margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.content-card { background: #fff; padding: 16px 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.balance-positive { color: #67C23A; font-weight: 600; }
.balance-negative { color: #F56C6C; font-weight: 600; }
.mr-4 { margin-right: 4px; }
</style>