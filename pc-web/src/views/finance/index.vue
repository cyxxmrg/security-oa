<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">财务概览</span>
      <div class="header-actions">
        <el-button type="warning" :icon="Switch" @click="showTransferDialog = true">公司内部转账</el-button>
        <el-button type="primary" :icon="Wallet" @click="showAccountDialog = true">资金账户管理</el-button>
        <el-button @click="router.push('/finance/receivable')">应收账款</el-button>
        <el-button @click="router.push('/finance/payable')">应付账款</el-button>
      </div>
    </div>

    <FinanceStatCards :loading="loading" :stats="stats" />

    <!-- 资金账户余额显示 -->
    <div class="content-card mb-16">
      <div class="card-header">
        <span>💰 资金账户余额</span>
        <el-link type="primary" :underline="false" @click="showAccountDialog = true">管理账户 →</el-link>
      </div>
      <AccountCardList
        v-loading="accountsLoading"
        :accounts="accounts"
        :icon-fn="accountIcon"
        :status-label="accountStatusLabel"
      />
      <el-empty v-if="!accountsLoading && accounts.length === 0" description="暂无资金账户" :image-size="60" />
    </div>

    <ReceivablePayableCard @goto="(p: string) => router.push(p)" />

    <!-- 资金账户管理 Dialog -->
    <el-dialog v-model="showAccountDialog" title="资金账户管理" width="1500px" destroy-on-close>
      <div class="account-toolbar">
        <el-input v-model="accountFilter.keyword" placeholder="账户名/账号" clearable style="width: 200px" @keyup.enter="loadAccounts" />
        <el-select v-model="accountFilter.type" placeholder="账户类型" clearable style="width: 140px">
          <el-option v-for="t in accountTypeOptions" :key="t.value" :label="t.label" :value="t.value" />
        </el-select>
        <el-button type="primary" :icon="Plus" @click="handleAddAccount">新增账户</el-button>
      </div>
      <el-table :data="filteredAccounts" border stripe v-loading="accountsLoading" height="500">
        <el-table-column type="index" width="50" align="center" />
        <el-table-column label="账户类型" width="110">
          <template #default="{ row }">
            <el-tag :type="accountTypeTagType(row.type)" effect="plain">
              <el-icon class="mr-4"><component :is="accountIcon(row.type)" /></el-icon>
              {{ accountTypeLabel(row.type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="账户名称" width="160" />
        <el-table-column prop="account_no" label="账号" width="200" />
        <el-table-column prop="bank_name" label="开户行" width="160" show-overflow-tooltip />
        <el-table-column label="余额" width="140" align="right">
          <template #default="{ row }">
            <span :class="row.balance >= 0 ? 'balance-positive' : 'balance-negative'">¥ {{ formatMoney(row.balance) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="币种" width="80" align="center">
          <template #default="{ row }">{{ row.currency || 'CNY' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" effect="dark" size="small">
              {{ accountStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="备注" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.notes || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleViewAccount(row)">流水</el-button>
            <el-button link type="primary" size="small" @click="handleEditAccount(row)">编辑</el-button>
            <el-button link type="warning" size="small" @click="handleAdjustAccount(row)">调账</el-button>
            <el-button link type="danger" size="small" @click="handleDeleteAccount(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <!-- 新增/编辑账户 Dialog -->
    <el-dialog v-model="showAccountForm" :title="accountForm.id ? '编辑账户' : '新增账户'" width="1500px" destroy-on-close>
      <el-form ref="accountFormRef" :model="accountForm" :rules="accountRules" label-width="100px">
        <el-form-item label="账户名称" prop="name">
          <el-input v-model="accountForm.name" placeholder="如：招商银行基本户" />
        </el-form-item>
        <el-form-item label="账户类型" prop="type">
          <el-select v-model="accountForm.type" placeholder="请选择" style="width: 100%">
            <el-option v-for="t in accountTypeOptions" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="账号" prop="account_no">
          <el-input v-model="accountForm.account_no" placeholder="银行账号" />
        </el-form-item>
        <el-form-item label="开户行" prop="bank_name">
          <el-input v-model="accountForm.bank_name" placeholder="如：招商银行深圳分行" />
        </el-form-item>
        <el-form-item label="初始余额" prop="balance">
          <el-input-number v-model="accountForm.balance" :precision="2" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="币种">
          <el-select v-model="accountForm.currency" style="width: 100%">
            <el-option label="人民币 CNY" value="CNY" />
            <el-option label="美元 USD" value="USD" />
            <el-option label="欧元 EUR" value="EUR" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="accountForm.status">
            <el-radio value="active">启用</el-radio>
            <el-radio value="frozen">冻结</el-radio>
            <el-radio value="closed">已注销</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="accountForm.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAccountForm = false">取消</el-button>
        <el-button type="primary" @click="handleSaveAccount">保存</el-button>
      </template>
    </el-dialog>

    <!-- 公司内部转账 Dialog -->
    <el-dialog v-model="showTransferDialog" title="公司内部转账" width="1500px" destroy-on-close>
      <el-alert type="info" :closable="false" style="margin-bottom: 16px">
        公司内部转账用于不同账户间的资金互转（如基本户转一般户、备用金调拨等）
      </el-alert>
      <el-form ref="transferFormRef" :model="transferForm" :rules="transferRules" label-width="100px">
        <el-form-item label="转出账户" prop="from_account_id">
          <el-select v-model="transferForm.from_account_id" placeholder="请选择转出账户" style="width: 100%" filterable @change="onFromAccountChange">
            <el-option v-for="a in activeAccounts" :key="a.id" :label="`${a.name} (¥${formatMoney(a.balance)})`" :value="a.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="转入账户" prop="to_account_id">
          <el-select v-model="transferForm.to_account_id" placeholder="请选择转入账户" style="width: 100%" filterable>
            <el-option v-for="a in activeAccounts" :key="a.id" :label="`${a.name} (¥${formatMoney(a.balance)})`" :value="a.id" :disabled="a.id === transferForm.from_account_id" />
          </el-select>
        </el-form-item>
        <el-form-item label="转账金额" prop="amount">
          <el-input-number v-model="transferForm.amount" :precision="2" :min="0.01" :max="fromAccountBalance" style="width: 100%" />
          <div v-if="fromAccount" class="balance-tip">转出账户余额：¥ {{ formatMoney(fromAccount.balance) }}</div>
        </el-form-item>
        <el-form-item label="手续费" prop="fee">
          <el-input-number v-model="transferForm.fee" :precision="2" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="转账日期" prop="transfer_date">
          <el-date-picker v-model="transferForm.transfer_date" type="date" placeholder="选择日期" style="width: 100%" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="用途" prop="purpose">
          <el-input v-model="transferForm.purpose" placeholder="如：支付货款 / 备用金调拨" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="transferForm.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTransferDialog = false">取消</el-button>
        <el-button type="warning" @click="handleTransfer">确认转账</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, type Component } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import { Wallet, Plus, Switch, CreditCard, Money, Coin, OfficeBuilding } from '@element-plus/icons-vue'
import { get, post, put, del } from '@/utils/request'
import { unwrapList, unwrapStats } from '@/utils/response'
import FinanceStatCards from './components/dashboard/FinanceStatCards.vue'
import AccountCardList from './components/dashboard/AccountCardList.vue'
import ReceivablePayableCard from './components/dashboard/ReceivablePayableCard.vue'
import type { FinanceAccount, AccountStats, FinanceOverview, FinanceTagType } from './types'

const router = useRouter()
const loading = ref(false)
const stats = ref<FinanceOverview>({})

// 资金账户
const accounts = ref<FinanceAccount[]>([])
const accountsLoading = ref(false)
const showAccountDialog = ref(false)
const showAccountForm = ref(false)
const accountFormRef = ref<FormInstance | null>(null)
const accountFilter = reactive({ keyword: '', type: '' })
const accountForm = reactive({
  id: null as number | null, name: '', type: 'bank', account_no: '', bank_name: '',
  balance: 0, currency: 'CNY', status: 'active', notes: ''
})
const accountRules: FormRules = {
  name: [{ required: true, message: '请输入账户名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择账户类型', trigger: 'change' }],
  account_no: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  bank_name: [{ required: true, message: '请输入开户行', trigger: 'blur' }]
}

// 公司内部转账
const showTransferDialog = ref(false)
const transferFormRef = ref<FormInstance | null>(null)
const transferForm = reactive({
  from_account_id: null as number | null, to_account_id: null as number | null,
  amount: 0, fee: 0, transfer_date: new Date().toISOString().slice(0, 10),
  purpose: '', notes: ''
})
const transferRules: FormRules = {
  from_account_id: [{ required: true, message: '请选择转出账户', trigger: 'change' }],
  to_account_id: [{ required: true, message: '请选择转入账户', trigger: 'change' }],
  amount: [{ required: true, message: '请输入转账金额', trigger: 'blur' }],
  transfer_date: [{ required: true, message: '请选择转账日期', trigger: 'change' }],
  purpose: [{ required: true, message: '请输入用途', trigger: 'blur' }]
}

// 字典
const accountTypeOptions = [
  { value: 'bank', label: '银行账户' },
  { value: 'cash', label: '现金' },
  { value: 'alipay', label: '支付宝' },
  { value: 'wechat', label: '微信' },
  { value: 'other', label: '其他' }
]
const accountTypeLabel = (t: string) => accountTypeOptions.find(x => x.value === t)?.label || t
const accountTypeTagType = (t?: string): FinanceTagType => ({ bank: 'primary', cash: 'success', alipay: 'info', wechat: 'success', other: 'info' } as Record<string, FinanceTagType>)[t ?? ''] || 'info'
const accountIcon = (t: string): Component => ({ bank: CreditCard, cash: Money, alipay: Coin, wechat: Wallet, other: Wallet }[t] || Wallet)
const accountStatusLabel = (s: string) => ({ active: '启用', frozen: '冻结', closed: '已注销' }[s] || s)

// 计算属性
const activeAccounts = computed(() => accounts.value.filter(a => a.status === 'active'))
const filteredAccounts = computed(() => accounts.value.filter(a => {
  if (accountFilter.keyword && !a.name?.includes(accountFilter.keyword) && !a.account_no?.includes(accountFilter.keyword)) return false
  if (accountFilter.type && a.type !== accountFilter.type) return false
  return true
}))
const fromAccount = computed(() => accounts.value.find(a => a.id === transferForm.from_account_id))
const fromAccountBalance = computed(() => Number(fromAccount.value?.balance || 0))

const loadStats = async () => {
  loading.value = true
  try {
    const res = await get('/finance/overview')
    // V1.2.10 用 unwrapStats 解包统计对象
    stats.value = unwrapStats(res)
  } catch (e) {} finally { loading.value = false }
}

const loadAccounts = async () => {
  accountsLoading.value = true
  try {
    const res = await get('/finance/accounts', { per_page: 50 })
    // V1.2.10 用 unwrapList 统一解包
    accounts.value = unwrapList(res)
  } catch (e) {
    accounts.value = []
    ElMessage.error('资金账户加载失败: ' + ((e as { message?: string })?.message || '网络异常'))
  } finally { accountsLoading.value = false }
}

const handleAddAccount = () => {
  Object.assign(accountForm, { id: null, name: '', type: 'bank', account_no: '', bank_name: '', balance: 0, currency: 'CNY', status: 'active', notes: '' })
  showAccountForm.value = true
}
const handleEditAccount = (row: FinanceAccount) => { Object.assign(accountForm, row); showAccountForm.value = true }
const handleDeleteAccount = async (row: FinanceAccount) => {
  try {
    await ElMessageBox.confirm(`确定删除账户「${row.name}」吗？`, '删除确认', { type: 'warning' })
    await del(`/finance/accounts/${row.id}`)
    ElMessage.success('已删除')
    loadAccounts()
  } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as { message?: string })?.message || '删除失败') }
}
const handleViewAccount = (row: FinanceAccount) => {
  ElMessage.info(`查看账户「${row.name}」的流水（待开发）`)
}
const handleAdjustAccount = (row: FinanceAccount) => {
  ElMessageBox.prompt('请输入调整金额（正数加，负数减）', '账户调账', { inputPattern: /^-?\d+(\.\d+)?$/, inputErrorMessage: '请输入有效数字' })
    .then(({ value }) => {
      row.balance = Number(row.balance || 0) + parseFloat(value)
      ElMessage.success(`调账成功：${value}元`)
      loadAccounts()
    }).catch(() => {})
}
const handleSaveAccount = async () => {
  if (!accountFormRef.value) return
  try { await accountFormRef.value.validate() } catch { return }
  try {
    if (accountForm.id) { await put(`/finance/accounts/${accountForm.id}`, accountForm); ElMessage.success('已更新') }
    else { await post('/finance/accounts', accountForm); ElMessage.success('已新增') }
    showAccountForm.value = false
    loadAccounts()
  } catch (e: unknown) { ElMessage.error((e as { message?: string })?.message || '保存失败') }
}

const onFromAccountChange = () => {
  // 切换转出账户时，重置金额为 0
  transferForm.amount = 0
}

const handleTransfer = async () => {
  if (!transferFormRef.value) return
  try { await transferFormRef.value.validate() } catch { return }
  if (transferForm.from_account_id === transferForm.to_account_id) {
    ElMessage.error('转出和转入账户不能相同')
    return
  }
  if (transferForm.amount > fromAccountBalance.value) {
    ElMessage.error('转账金额超过转出账户余额')
    return
  }
  try {
    await ElMessageBox.confirm(
      `确认从「${fromAccount.value?.name}」转出 ¥${formatMoney(transferForm.amount)} 到其他账户？`,
      '转账确认', { type: 'warning' }
    )
    await post('/finance/accounts/transfer', {
      from_account_id: transferForm.from_account_id,
      to_account_id: transferForm.to_account_id,
      amount: transferForm.amount,
      fee: transferForm.fee,
      transfer_date: transferForm.transfer_date,
      method: '内部转账',                          // 后端 method 字段
      purpose: transferForm.purpose,               // 业务用途（保留给明细）
      remark: transferForm.notes,                  // 后端字段名是 remark
    })
    ElMessage.success('转账成功')
    showTransferDialog.value = false
    Object.assign(transferForm, { from_account_id: null, to_account_id: null, amount: 0, fee: 0, purpose: '', notes: '' })
    loadAccounts()
  } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as { message?: string })?.message || '转账失败') }
}

const formatMoney = (n: number | string | null | undefined) => {
  const v = Number(n || 0)
  return v.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(() => {
  loadStats()
  loadAccounts()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; .page-title { font-size: 20px; color: #0C447C; font-weight: 600; } .header-actions { display: flex; gap: 8px; } }
.mb-16 { margin-bottom: 16px; }
.stat-card { background: #fff; border-radius: 8px; padding: 24px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
.stat-value { font-size: 26px; font-weight: 700; &.primary { color: #0C447C; } &.success { color: #1D9E75; } &.warning { color: #BA7517; } &.danger { color: #A32D2D; } }
.stat-label { font-size: 13px; color: #909399; margin-top: 6px; }
.content-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.card-header { font-size: 15px; font-weight: 600; color: #2c3e50; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
.mini-card { margin-bottom: 16px; }
.card-title { font-size: 14px; font-weight: 600; }

// 资金账户卡片
.account-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-radius: 8px;
  background: #f8fafc;
  border-left: 4px solid #0C447C;
  margin-bottom: 12px;
  transition: all 0.2s;
  &:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
  &.account-cash { border-left-color: #1D9E75; }
  &.account-alipay { border-left-color: #534AB7; }
  &.account-wechat { border-left-color: #BA7517; }
  &.account-other { border-left-color: #909399; }
}
.account-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(12, 68, 124, 0.1);
  color: #0C447C;
}
.account-info { flex: 1; min-width: 0; }
.account-name { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 2px; }
.account-no { font-size: 12px; color: #909399; font-family: monospace; margin-bottom: 4px; }
.account-balance { font-size: 18px; font-weight: 700; margin: 4px 0; &.positive { color: #1D9E75; } &.negative { color: #A32D2D; } }
.account-meta { display: flex; align-items: center; gap: 6px; .bank-name { font-size: 11px; color: #909399; } }

// 列表中的余额
.balance-positive { color: #1D9E75; font-weight: 600; }
.balance-negative { color: #A32D2D; font-weight: 600; }

.account-toolbar { display: flex; gap: 8px; margin-bottom: 12px; }
.mr-4 { margin-right: 4px; }
.balance-tip { font-size: 12px; color: #909399; margin-top: 4px; }
</style>
