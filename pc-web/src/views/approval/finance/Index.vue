<template>
  <div class="page-container">
    <!-- 页头：标题 + 待我审批徽标 -->
    <div class="page-header">
      <div class="header-left">
        <h2>财务审批</h2>
        <el-tag v-if="pendingCount > 0" type="danger" effect="dark" round class="ml-12">
          待我审批 {{ pendingCount }}
        </el-tag>
      </div>
      <div class="header-right">
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Search" @click="showSearch = !showSearch">筛选</el-button>
      </div>
    </div>

    <!-- 筛选区 -->
    <transition name="el-fade-in">
      <div v-if="showSearch" class="filter-bar">
        <el-input v-model="filter.keyword" placeholder="单号/标题" clearable style="width: 200px" @keyup.enter="handleSearch" />
        <el-select v-model="filter.subType" placeholder="审批子类" clearable style="width: 160px">
          <el-option v-for="t in subTypeOptions" :key="t.value" :label="t.label" :value="t.value" />
        </el-select>
        <el-select v-model="filter.priority" placeholder="优先级" clearable style="width: 120px">
          <el-option v-for="p in priorityOptions" :key="p.value" :label="p.label" :value="p.value" />
        </el-select>
        <el-date-picker v-model="filter.dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" style="width: 260px" />
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="resetFilter">重置</el-button>
      </div>
    </transition>

    <!-- 4 张统计卡片 -->
    <div class="stats-row">
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div>
            <div class="stat-label">待我审批</div>
            <div class="stat-value primary">{{ pendingCount }}</div>
          </div>
          <el-icon :size="32" class="stat-icon primary"><Bell /></el-icon>
        </div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div>
            <div class="stat-label">我已审批</div>
            <div class="stat-value success">{{ approvedCount }}</div>
          </div>
          <el-icon :size="32" class="stat-icon success"><Check /></el-icon>
        </div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div>
            <div class="stat-label">本月涉及金额</div>
            <div class="stat-value warning">¥ {{ formatMoney(totalAmount) }}</div>
          </div>
          <el-icon :size="32" class="stat-icon warning"><Money /></el-icon>
        </div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content">
          <div>
            <div class="stat-label">本月已支付</div>
            <div class="stat-value info">¥ {{ formatMoney(paidAmount) }}</div>
          </div>
          <el-icon :size="32" class="stat-icon info"><CreditCard /></el-icon>
        </div>
      </el-card>
    </div>

    <div class="content-card">
      <el-tabs v-model="activeTab" class="approval-tabs">
        <el-tab-pane name="pending">
          <template #label>
            <el-badge :value="pendingCount" :hidden="pendingCount === 0" type="danger">
              待我审批
            </el-badge>
          </template>
        </el-tab-pane>
        <el-tab-pane name="approved">
          <template #label><span>我已审批</span></template>
        </el-tab-pane>
        <el-tab-pane name="rejected">
          <template #label><span>我已驳回</span></template>
        </el-tab-pane>
        <el-tab-pane name="initiated">
          <template #label><span>我发起的</span></template>
        </el-tab-pane>
        <el-tab-pane name="cc">
          <template #label><span>抄送我的</span></template>
        </el-tab-pane>
      </el-tabs>

      <el-table :data="filteredList" stripe border style="width: 100%" v-loading="loading" @row-dblclick="handleDetail">
        <el-table-column type="index" width="50" align="center" />
        <el-table-column prop="code" label="单号" width="160" fixed>
          <template #default="{ row }">
            <el-link type="primary" :underline="false" @click="handleDetail(row)">{{ row.code }}</el-link>
          </template>
        </el-table-column>
        <el-table-column label="审批子类" width="120">
          <template #default="{ row }">
            <el-tag :type="subTypeTagType(row.subType)" effect="plain">
              <el-icon class="mr-4"><component :is="subTypeIcon(row.subType)" /></el-icon>
              {{ subTypeLabel(row.subType) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="220" show-overflow-tooltip />
        <el-table-column label="发起人" width="100">
          <template #default="{ row }">{{ row.initiator?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="金额" width="140" align="right">
          <template #default="{ row }">
            <span class="amount">¥ {{ formatMoney(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="账户" width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.bankAccount" class="bank-info">
              <el-icon><CreditCard /></el-icon>
              {{ row.bankAccount }}
            </span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="发起时间" width="160">
          <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="优先级" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="priorityTagType(row.priority)" effect="dark" size="small">
              {{ priorityLabel(row.priority) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="dark">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleDetail(row)">详情</el-button>
            <template v-if="row.status === 'pending' && canApprove(row)">
              <el-button link type="success" size="small" @click="handleApprove(row)">通过</el-button>
              <el-button link type="danger" size="small" @click="handleReject(row)">驳回</el-button>
              <el-button link type="warning" size="small" @click="handleTransfer(row)">转交</el-button>
            </template>
            <el-tag v-else-if="row.status === 'pending' && isSelfSubmitted(row) && !isAdmin" size="small" type="info" effect="plain">自己提交, 不能自审</el-tag>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loading && filteredList.length === 0" description="暂无数据" :image-size="100" />
    </div>

    <!-- 详情 Dialog -->
    <el-dialog v-model="showDetailDialog" :title="`财务审批详情 — ${currentItem?.code || ''}`" width="1500px" destroy-on-close>
      <div v-if="currentItem">
        <el-descriptions :column="3" border>
          <el-descriptions-item label="单号">{{ currentItem.code }}</el-descriptions-item>
          <el-descriptions-item label="子类">
            <el-tag :type="subTypeTagType(currentItem.subType)" effect="plain">
              {{ subTypeLabel(currentItem.subType) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="优先级">
            <el-tag :type="priorityTagType(currentItem.priority)" effect="dark">
              {{ priorityLabel(currentItem.priority) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="标题" :span="3">{{ currentItem.title }}</el-descriptions-item>
          <el-descriptions-item label="发起人">{{ currentItem.initiator?.name }}</el-descriptions-item>
          <el-descriptions-item label="金额"><span class="amount">¥ {{ formatMoney(currentItem.amount) }}</span></el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTagType(currentItem.status)" effect="dark">
              {{ statusLabel(currentItem.status) }}
            </el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <h4 class="section-title">💰 财务详情</h4>
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item v-for="(v, k) in currentItem.detail" :key="k" :label="k">{{ v }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="section-title">🔁 审批流程</h4>
        <el-timeline>
          <el-timeline-item
            v-for="(n, i) in currentItem.flow"
            :key="i"
            :timestamp="formatDateTime(n.time)"
            :type="flowNodeType(n.action)"
            :hollow="i !== currentItem.flow.length - 1"
            size="large"
          >
            <strong>{{ n.operator }}</strong>
            <el-tag :type="flowNodeType(n.action)" effect="plain" size="small" class="ml-8">{{ flowActionLabel(n.action) }}</el-tag>
            <div v-if="n.comment" class="flow-comment">💬 {{ n.comment }}</div>
            <!-- 付款成功节点 (finance/expense + status=approved 才显示) -->
            <div v-if="n.action === 'pay_done'" class="flow-payment">
              <el-tag type="success" effect="dark" size="small">💰 付款成功</el-tag>
              <span v-if="n.paid_amount" class="ml-8">¥ {{ formatMoney(n.paid_amount) }}</span>
              <span v-if="n.paid_at" class="text-muted ml-8">{{ formatDateTime(n.paid_at) }}</span>
            </div>
          </el-timeline-item>
        </el-timeline>

        <!-- V1.2.7f: 已审批通过的报销 → 显示付款按钮, 跳到付款单创建页 -->
        <div v-if="currentItem.subType === 'expense' && currentItem.status === 'approved' && !paymentDone" class="payment-action">
          <el-alert type="warning" :closable="false" show-icon>
            <template #title>
              这条报销已审批通过, 尚未付款。点击下方按钮跳到付款单创建页, 付款完成后流程会自动追加"付款成功"节点。
            </template>
          </el-alert>
          <div class="payment-buttons mt-12">
            <el-button type="primary" :icon="Money" @click="goCreatePayment">前往付款 →</el-button>
            <el-button :icon="Refresh" @click="reloadItem">刷新状态</el-button>
          </div>
        </div>
        <div v-else-if="currentItem.subType === 'expense' && currentItem.status === 'approved' && paymentDone" class="payment-action">
          <el-alert type="success" :closable="false" show-icon>
            <template #title>✅ 已付款完成, 流程已记录付款节点</template>
          </el-alert>
        </div>

        <div v-if="currentItem.status === 'pending' && canApprove(currentItem)" class="approval-input">
          <h4 class="section-title">✍️ 我的审批</h4>
          <el-input v-model="approvalComment" type="textarea" :rows="3" placeholder="请输入审批意见（驳回必填）" maxlength="500" show-word-limit />
        </div>
      </div>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
        <template v-if="currentItem?.status === 'pending' && canApprove(currentItem)">
          <el-button type="danger" @click="handleReject(currentItem)">驳回</el-button>
          <el-button type="warning" @click="handleTransfer(currentItem)">转交</el-button>
          <el-button type="success" @click="handleApprove(currentItem)">通过</el-button>
        </template>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search, Bell, Check, Money, CreditCard } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import { useRouter } from 'vue-router'
import { approvalSubTypeLabel, localizeEnumText, priorityLabel as commonPriorityLabel, statusLabel as commonStatusLabel } from '@/utils/labels'

// el-tag 类型
type TagType = '' | 'success' | 'warning' | 'info' | 'danger' | 'primary'

// 审批流程节点
interface FlowNode {
  time?: string
  operator?: string
  action: string
  comment?: string
  paid_amount?: number | string
  paid_at?: string
  [key: string]: unknown
}
// 发起人
interface Initiator { name?: string }
// 财务审批项
interface FinanceApprovalItem {
  id: number
  code?: string
  subType?: string
  title?: string
  initiator?: Initiator
  amount?: number | string
  bankAccount?: string
  created_at?: string
  priority?: string
  status?: string
  currentApproverId?: number
  applicantId?: number
  flow?: FlowNode[]
  detail?: Record<string, unknown>
  payload?: { claim_id?: number; claim_no?: string; claimId?: number; claimNo?: string; [k: string]: unknown }
  [key: string]: unknown
}
// API 错误
interface ApiError { message?: string }

const loading = ref(false)
const showSearch = ref(false)
const showDetailDialog = ref(false)
const activeTab = ref('pending')
const list = ref<FinanceApprovalItem[]>([])
const currentItem = ref<FinanceApprovalItem | null>(null)
const approvalComment = ref('')

const filter = reactive({ keyword: '', subType: '', priority: '', dateRange: [] as (string | Date)[] })

// 财务审批子类
const subTypeOptions = [
  { value: 'expense', label: '报销' },
  { value: 'reimbursement', label: '报销' },
  { value: 'payment', label: '付款单' },
  { value: 'receivable', label: '应收确认' },
  { value: 'payable', label: '应付确认' },
  { value: 'purchase', label: '采购付款' },
  { value: 'commission', label: '居间费' },
  { value: 'salary', label: '薪资调整' },
  { value: 'reimburse', label: '差旅报销' },
  { value: 'loan', label: '借款' },
  { value: 'other', label: '其他' }
]
const priorityOptions = [
  { value: 'urgent', label: '紧急' }, { value: 'high', label: '高' },
  { value: 'normal', label: '普通' }, { value: 'low', label: '低' }
]
const subTypeLabel = (t: string) => subTypeOptions.find(x => x.value === t)?.label || approvalSubTypeLabel(t)
const priorityLabel = (p: string) => priorityOptions.find(x => x.value === p)?.label || commonPriorityLabel(p)
const subTypeTagType = (t: string): TagType => ({
  expense: 'danger', payment: 'danger', receivable: 'warning', payable: 'warning',
  purchase: 'success', commission: 'success', salary: 'info',
  reimburse: 'danger', loan: 'warning', other: 'info'
}[t] as TagType || 'info')
const subTypeIcon = (t: string) => ({
  expense: 'Money', payment: 'CreditCard', receivable: 'Wallet', payable: 'Wallet',
  purchase: 'ShoppingCart', commission: 'Coin', salary: 'UserFilled',
  reimburse: 'Ticket', loan: 'CreditCard', other: 'MoreFilled'
}[t] || 'MoreFilled')
const priorityTagType = (p: string): TagType => ({ urgent: 'danger', high: 'warning', normal: 'primary', low: 'info' }[p] as TagType || 'info')
const statusLabel = (s: string) => commonStatusLabel(s)
const statusTagType = (s: string): TagType => ({ pending: 'warning', approved: 'success', rejected: 'danger', transferred: 'info', cancelled: 'info' }[s] as TagType || 'info')
const flowNodeType = (a: string): TagType => ({ submit: 'primary', approve: 'success', reject: 'danger', transfer: 'warning', comment: 'info', pay_done: 'success' }[a] as TagType || 'info')
const flowActionLabel = (a: string) => ({ submit: '发起', approve: '通过', reject: '驳回', transfer: '转交', comment: '补充', pay_done: '付款' }[a] || a)

const router = useRouter()

// V1.2.7f: 报销已通过审批 → 检测 flow 里有没有 pay_done 节点
const paymentDone = computed(() => {
  const flow: FlowNode[] = currentItem.value?.flow ?? []
  return flow.some(n => n.action === 'pay_done')
})

const goCreatePayment = () => {
  // 把 claim_id 通过 query 带到付款单创建页, 让付款单自动关联这条报销
  const payload = currentItem.value?.payload ?? {}
  const claimId = payload.claim_id ?? payload.claimId
  const claimNo = payload.claim_no ?? payload.claimNo ?? currentItem.value?.code
  const amount = currentItem.value?.amount ?? 0
  const title = currentItem.value?.title ?? ''
  const applicant = currentItem.value?.initiator?.name ?? ''
  if (!claimId) {
    ElMessage.warning('该审批单缺少报销单 ID, 无法跳转付款')
    return
  }
  router.push({
    path: '/finance/payment',
    query: {
      from_approval: '1',
      claim_id: String(claimId),
      claim_no: String(claimNo ?? ''),
      amount: String(amount),
      title,
      applicant,
    }
  })
  showDetailDialog.value = false
  ElMessage.success('已跳到付款单创建页, 付款完成后该流程会自动追加"付款成功"节点')
}

const reloadItem = async () => {
  if (!currentItem.value?.id) return
  try {
    const r = await get(`/approvals/finance/${currentItem.value.id}`) // eslint-disable-line @typescript-eslint/no-explicit-any (API 响应结构不确定)
    currentItem.value = { ...(r?.data ?? r), title: localizeEnumText((r?.data ?? r)?.title) }
    ElMessage.success('已刷新')
  } catch (e: unknown) {
    ElMessage.error((e as ApiError)?.message || '刷新失败')
  }
}

const pendingCount = computed(() => list.value.filter(i => i.status === 'pending').length)
const approvedCount = computed(() => list.value.filter(i => i.status === 'approved').length)
const totalAmount = computed(() => list.value.filter(i => i.status === 'pending' || i.status === 'approved').reduce((s, i) => s + (parseFloat(i.amount) || 0), 0))
const paidAmount = computed(() => list.value.filter(i => i.status === 'approved').reduce((s, i) => s + (parseFloat(i.amount) || 0), 0))

const loadList = async () => {
  loading.value = true
  try {
    const res = await get('/approvals/finance', { per_page: 200 }) // eslint-disable-line @typescript-eslint/no-explicit-any (API 响应结构不确定)
    list.value = unwrapList(res).map((item: FinanceApprovalItem) => ({ ...item, title: localizeEnumText(item.title as string) }))
  } catch { list.value = [] }
  finally { loading.value = false }
}

const filteredList = computed(() => list.value.filter(item => {
  if (activeTab.value === 'pending' && item.status !== 'pending') return false
  if (activeTab.value === 'approved' && item.status !== 'approved') return false
  if (activeTab.value === 'rejected' && item.status !== 'rejected') return false
  if (filter.keyword && !(item.code || '').includes(filter.keyword) && !(item.title || '').includes(filter.keyword)) return false
  if (filter.subType && item.subType !== filter.subType) return false
  if (filter.priority && item.priority !== filter.priority) return false
  return true
}))

const canApprove = (row: FinanceApprovalItem) => row.status === 'pending' && Number(row.currentApproverId) === Number(myId.value)
// V1.2.7k: 系统账号可越权审批, 否则申请人不能审批自己的单子
const isSelfSubmitted = (row: FinanceApprovalItem) => Number(row.applicantId) === Number(myId.value)
const isAdmin = ref(false)
const myId = ref<number | null>(null)
const loadMyId = async () => {
  try {
    const me = await get('/auth/me') // eslint-disable-line @typescript-eslint/no-explicit-any (API 响应结构不确定)
    myId.value = me?.data?.user?.id ?? me?.user?.id ?? me?.id ?? null
    isAdmin.value = me?.data?.user?.is_system === true || me?.data?.user?.user_type === 'system'
  } catch {}
}
const handleDetail = (row: FinanceApprovalItem) => { currentItem.value = row; approvalComment.value = ''; showDetailDialog.value = true }
const handleApprove = async (row: FinanceApprovalItem) => {
  try { await ElMessageBox.confirm('确定审批通过吗？', '审批确认', { type: 'success' }); await post(`/approvals/finance/${row.id}/approve`, { comment: approvalComment.value || '同意' }); ElMessage.success('已通过'); showDetailDialog.value = false; loadList() } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as ApiError)?.message || '操作失败') }
}
const handleReject = async (row: FinanceApprovalItem) => {
  if (!approvalComment.value.trim()) { ElMessage.warning('请填写驳回意见'); return }
  try { await ElMessageBox.confirm('确定驳回吗？', '驳回确认', { type: 'warning' }); await post(`/approvals/finance/${row.id}/reject`, { comment: approvalComment.value }); ElMessage.success('已驳回'); showDetailDialog.value = false; loadList() } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as ApiError)?.message || '操作失败') }
}
const handleTransfer = async (row: FinanceApprovalItem) => {
  try { const { value: target } = await ElMessageBox.prompt('请输入转交给谁', '转交审批', { inputPlaceholder: '用户名' }); if (!target) return; await post(`/approvals/finance/${row.id}/forward`, { target }); ElMessage.success(`已转交 ${target}`); showDetailDialog.value = false; loadList() } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as ApiError)?.message || '操作失败') }
}
const handleSearch = () => {}
const resetFilter = () => { filter.keyword = ''; filter.subType = ''; filter.priority = ''; filter.dateRange = [] }
const formatDateTime = (d: string) => d ? new Date(d).toLocaleString('zh-CN', { hour12: false }).slice(0, 16) : '-'
const formatMoney = (v: unknown) => { const n = parseFloat(v as string); return isNaN(n) ? '0.00' : n.toFixed(2) }

onMounted(() => { loadMyId(); loadList() })
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; .header-left { display: flex; align-items: center; gap: 12px; h2 { font-size: 20px; color: #0C447C; margin: 0; } } }
.filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding: 16px; background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px; }
.stat-card { border-radius: 8px; .stat-content { display: flex; align-items: center; justify-content: space-between; } .stat-label { font-size: 14px; color: #606266; margin-bottom: 8px; } .stat-value { font-size: 28px; font-weight: 600; line-height: 1.2; &.primary { color: #BA7517; } &.success { color: #1D9E75; } &.warning { color: #A32D2D; } &.info { color: #534AB7; } } .stat-icon { opacity: 0.2; } }
.content-card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.section-title { margin: 20px 0 12px; font-size: 15px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 8px; }
.flow-comment { margin-top: 4px; color: #606266; font-size: 13px; }
.amount { color: #A32D2D; font-weight: 600; }
.bank-info { display: inline-flex; align-items: center; gap: 4px; color: #534AB7; font-family: monospace; font-size: 12px; }
.text-muted { color: #c0c4cc; }
.mt-12 { margin-top: 12px; }
.payment-action { margin: 16px 0; }
.payment-buttons { display: flex; gap: 8px; }
.flow-payment { margin-top: 6px; display: flex; align-items: center; gap: 4px; }
.ml-8 { margin-left: 8px; }
.ml-12 { margin-left: 12px; }
.mr-4 { margin-right: 4px; }
</style>
