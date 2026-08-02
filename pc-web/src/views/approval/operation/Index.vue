<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h2>运营审批</h2>
        <el-tag v-if="pendingCount > 0" type="danger" effect="dark" round class="ml-12">待我审批 {{ pendingCount }}</el-tag>
      </div>
      <div class="header-right">
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Search" @click="showSearch = !showSearch">筛选</el-button>
      </div>
    </div>

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

    <div class="stats-row">
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content"><div><div class="stat-label">待我审批</div><div class="stat-value primary">{{ pendingCount }}</div></div><el-icon :size="32" class="stat-icon primary"><Bell /></el-icon></div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content"><div><div class="stat-label">请假待审</div><div class="stat-value success">{{ leavePending }}</div></div><el-icon :size="32" class="stat-icon success"><Calendar /></el-icon></div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content"><div><div class="stat-label">用车待审</div><div class="stat-value warning">{{ vehiclePending }}</div></div><el-icon :size="32" class="stat-icon warning"><Van /></el-icon></div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content"><div><div class="stat-label">采购待审</div><div class="stat-value info">{{ purchasePending }}</div></div><el-icon :size="32" class="stat-icon info"><ShoppingCart /></el-icon></div>
      </el-card>
    </div>

    <div class="content-card">
      <el-tabs v-model="activeTab" class="approval-tabs">
        <el-tab-pane name="pending"><template #label><el-badge :value="pendingCount" :hidden="pendingCount === 0" type="danger">待我审批</el-badge></template></el-tab-pane>
        <el-tab-pane name="approved"><template #label><span>我已审批</span></template></el-tab-pane>
        <el-tab-pane name="rejected"><template #label><span>我已驳回</span></template></el-tab-pane>
        <el-tab-pane name="initiated"><template #label><span>我发起的</span></template></el-tab-pane>
        <el-tab-pane name="cc"><template #label><span>抄送我的</span></template></el-tab-pane>
      </el-tabs>

      <el-table :data="filteredList" stripe border style="width: 100%" v-loading="loading" @row-dblclick="handleDetail">
        <el-table-column type="index" width="50" align="center" />
        <el-table-column prop="code" label="单号" width="160" fixed>
          <template #default="{ row }"><el-link type="primary" :underline="false" @click="handleDetail(row)">{{ row.code }}</el-link></template>
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
        <el-table-column label="申请人" width="100">
          <template #default="{ row }">{{ row.initiator?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="时长/数量" width="120" align="center">
          <template #default="{ row }">
            <span v-if="row.duration">{{ row.duration }} {{ row.unit || '天' }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="开始时间" width="160">
          <template #default="{ row }">{{ formatDate(row.start_date) }}</template>
        </el-table-column>
        <el-table-column label="结束时间" width="160">
          <template #default="{ row }">{{ formatDate(row.end_date) }}</template>
        </el-table-column>
        <el-table-column label="优先级" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="priorityTagType(row.priority)" effect="dark" size="small">{{ priorityLabel(row.priority) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="dark">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleDetail(row)">详情</el-button>
            <template v-if="row.status === 'pending' && canApprove(row)">
              <el-button link type="success" size="small" @click="handleApprove(row)">通过</el-button>
              <el-button link type="danger" size="small" @click="handleReject(row)">驳回</el-button>
              <el-button link type="warning" size="small" @click="handleTransfer(row)">转交</el-button>
            </template>
            <template v-else-if="row.status === 'pending' && isSelfSubmitted(row) && !isAdmin">
              <el-tag size="small" type="info" effect="plain">自己提交, 不能自审</el-tag>
            </template>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loading && filteredList.length === 0" description="暂无数据" :image-size="100" />
    </div>

    <el-dialog v-model="showDetailDialog" :title="`运营审批详情 — ${currentItem?.code || ''}`" width="1500px" destroy-on-close>
      <div v-if="currentItem">
        <el-descriptions :column="3" border>
          <el-descriptions-item label="单号">{{ currentItem.code }}</el-descriptions-item>
          <el-descriptions-item label="子类"><el-tag :type="subTypeTagType(currentItem.subType)" effect="plain">{{ subTypeLabel(currentItem.subType) }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="优先级"><el-tag :type="priorityTagType(currentItem.priority)" effect="dark">{{ priorityLabel(currentItem.priority) }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="标题" :span="3">{{ currentItem.title }}</el-descriptions-item>
          <el-descriptions-item label="申请人">{{ currentItem.initiator?.name }}</el-descriptions-item>
          <el-descriptions-item label="时长">{{ currentItem.duration }} {{ currentItem.unit || '天' }}</el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag :type="statusTagType(currentItem.status)" effect="dark">{{ statusLabel(currentItem.status) }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="开始时间">{{ formatDateTime(currentItem.start_date) }}</el-descriptions-item>
          <el-descriptions-item label="结束时间" :span="2">{{ formatDateTime(currentItem.end_date) }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="section-title">📄 申请详情</h4>
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item v-for="(v, k) in currentItem.detail" :key="k" :label="k">{{ v }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="section-title">🔁 审批流程</h4>
        <el-timeline>
          <el-timeline-item v-for="(n, i) in (currentItem.flow || [])" :key="i" :timestamp="formatDateTime(n.time)" :type="flowNodeType(n.action)" :hollow="i !== (currentItem.flow || []).length - 1" size="large">
            <strong>{{ n.operator }}</strong>
            <el-tag :type="flowNodeType(n.action)" effect="plain" size="small" class="ml-8">{{ flowActionLabel(n.action) }}</el-tag>
            <div v-if="n.comment" class="flow-comment">💬 {{ n.comment }}</div>
          </el-timeline-item>
        </el-timeline>

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
import { Refresh, Search, Bell, Calendar, Van, ShoppingCart } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import { approvalSubTypeLabel, localizeEnumText, priorityLabel as commonPriorityLabel, statusLabel as commonStatusLabel } from '@/utils/labels'

type TagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'

interface FlowNode {
  time?: string
  action?: string
  operator?: string
  comment?: string
  [key: string]: unknown
}

interface ApprovalItem {
  id?: number
  code?: string
  subType?: string
  title?: string
  status?: string
  priority?: string
  duration?: number | string
  unit?: string
  start_date?: string
  end_date?: string
  currentApproverId?: number | string
  applicantId?: number | string
  initiator?: { name?: string } | null
  detail?: Record<string, unknown>
  flow?: FlowNode[]
  [key: string]: unknown
}

const loading = ref(false)
const showSearch = ref(false)
const showDetailDialog = ref(false)
const activeTab = ref('pending')
const list = ref<ApprovalItem[]>([])
const currentItem = ref<ApprovalItem | null>(null)
const approvalComment = ref('')
const filter = reactive({ keyword: '', subType: '', priority: '', dateRange: [] as (string | Date)[] })

// 运营审批子类
const subTypeOptions = [
  { value: 'leave', label: '请假' }, { value: 'overtime', label: '加班' },
  { value: 'vehicle', label: '用车' }, { value: 'vehicle_dispatch', label: '派车' },
  { value: 'purchase', label: '采购' }, { value: 'purchase_requirement', label: '采购需求' },
  { value: 'purchase_plan', label: '采购计划' }, { value: 'purchase_payment', label: '采购付款' },
  { value: 'sales', label: '销售' }, { value: 'discount', label: '折扣' },
  { value: 'transfer', label: '调拨' }, { value: 'attendance', label: '考勤异常' },
  { value: 'customer', label: '客户' }, { value: 'other', label: '其他' }
]
const priorityOptions = [
  { value: 'urgent', label: '紧急' }, { value: 'high', label: '高' },
  { value: 'normal', label: '普通' }, { value: 'low', label: '低' }
]
const subTypeLabel = (t: string | undefined) => subTypeOptions.find(x => x.value === t)?.label || approvalSubTypeLabel(t ?? '')
const priorityLabel = (p: string | undefined) => priorityOptions.find(x => x.value === p)?.label || commonPriorityLabel(p ?? '')
const subTypeTagType = (t: string | undefined): TagType => ({
  leave: 'warning', overtime: 'warning', vehicle: 'primary', vehicle_dispatch: 'primary',
  purchase: 'success', purchase_requirement: 'success', purchase_plan: 'success', purchase_payment: 'success', sales: 'success', discount: 'warning',
  transfer: 'info', attendance: 'danger', customer: 'info', other: 'info'
} as Record<string, TagType>)[t ?? ''] || 'info'
const subTypeIcon = (t: string | undefined) => ({
  leave: 'Calendar', overtime: 'AlarmClock', vehicle: 'Van', vehicle_dispatch: 'Promotion',
  purchase: 'ShoppingCart', purchase_requirement: 'ShoppingCart', purchase_plan: 'Files', purchase_payment: 'CreditCard', sales: 'TrendCharts', discount: 'Discount',
  transfer: 'Share', attendance: 'AlarmClock', customer: 'OfficeBuilding', other: 'MoreFilled'
} as Record<string, string>)[t ?? ''] || 'MoreFilled'
const priorityTagType = (p: string | undefined): TagType => ({ urgent: 'danger', high: 'warning', normal: 'primary', low: 'info' } as Record<string, TagType>)[p ?? ''] || 'info'
const statusLabel = (s: string | undefined) => commonStatusLabel(s ?? '')
const statusTagType = (s: string | undefined): TagType => ({ pending: 'warning', approved: 'success', rejected: 'danger', transferred: 'info', cancelled: 'info' } as Record<string, TagType>)[s ?? ''] || 'info'
const flowNodeType = (a: string | undefined): TagType => ({ submit: 'primary', approve: 'success', reject: 'danger', transfer: 'warning', comment: 'info' } as Record<string, TagType>)[a ?? ''] || 'info'
const flowActionLabel = (a: string | undefined) => ({ submit: '发起', approve: '通过', reject: '驳回', transfer: '转交', comment: '补充' } as Record<string, string>)[a ?? ''] || a || ''

const pendingCount = computed(() => list.value.filter(i => i.status === 'pending').length)
const leavePending = computed(() => list.value.filter(i => i.status === 'pending' && i.subType === 'leave').length)
const vehiclePending = computed(() => list.value.filter(i => i.status === 'pending' && i.subType === 'vehicle').length)
const purchasePending = computed(() => list.value.filter(i => i.status === 'pending' && (i.subType === 'purchase_order' || i.subType === 'purchase_requirement' || i.subType === 'purchase_plan' || i.subType === 'purchase')).length)

const loadList = async () => {
  loading.value = true
  try {
    // V1.2.7e: 后端返回 {code, data: {data:[], total:N}} 分页格式
    // 拆出真正的 list 数组, 否则 v-for 在对象上跑不动, loading 死循环
    const res = await get('/approvals/operation', { per_page: 200 })
    list.value = unwrapList(res).map((item) => ({ ...item, title: localizeEnumText(item.title) }))
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

const canApprove = (row: ApprovalItem) => row.status === 'pending' && Number(row.currentApproverId) === Number(myId.value)
// V1.2.7k: 系统账号可越权审批, 否则申请人不能审批自己的单子
const isSelfSubmitted = (row: ApprovalItem) => Number(row.applicantId) === Number(myId.value)
// 注入当前用户 id 供 canApprove 比较 (后端返回 current_approver_id 是数字, 前端没 myId)
const myId = ref<number | null>(null)
const isAdmin = ref(false)
const loadMyId = async () => {
  try {
    const me = await get('/auth/me')
    // /api/auth/me 返回 {code, data: {user: {id, name, ...}}}
    myId.value = me?.data?.user?.id ?? me?.user?.id ?? me?.id ?? null
    // V1.2.7k: system/admin 账号可以审批任何人的申请
    isAdmin.value = me?.data?.user?.is_system === true || me?.data?.user?.user_type === 'system'
  } catch {}
}
const handleDetail = (row: ApprovalItem) => { currentItem.value = row; approvalComment.value = ''; showDetailDialog.value = true }
const handleApprove = async (row: ApprovalItem) => { try { await ElMessageBox.confirm('确定审批通过吗？', '审批确认', { type: 'success' }); await post(`/approvals/operation/${row.id}/approve`, { comment: approvalComment.value || '同意' }); ElMessage.success('已通过'); showDetailDialog.value = false; loadList() } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as { message?: string })?.message || '操作失败') } }
const handleReject = async (row: ApprovalItem) => { if (!approvalComment.value.trim()) { ElMessage.warning('请填写驳回意见'); return }; try { await ElMessageBox.confirm('确定驳回吗？', '驳回确认', { type: 'warning' }); await post(`/approvals/operation/${row.id}/reject`, { comment: approvalComment.value }); ElMessage.success('已驳回'); showDetailDialog.value = false; loadList() } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as { message?: string })?.message || '操作失败') } }
const handleTransfer = async (row: ApprovalItem) => { try { const { value: target } = await ElMessageBox.prompt('请输入转交给谁', '转交审批', { inputPlaceholder: '用户名' }); if (!target) return; await post(`/approvals/operation/${row.id}/forward`, { target }); ElMessage.success(`已转交 ${target}`); showDetailDialog.value = false; loadList() } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as { message?: string })?.message || '操作失败') } }
const handleSearch = () => {}
const resetFilter = () => { filter.keyword = ''; filter.subType = ''; filter.priority = ''; filter.dateRange = [] }
const formatDate = (d?: string) => d ? new Date(d).toISOString().slice(0, 10) : '-'
const formatDateTime = (d?: string) => d ? new Date(d).toLocaleString('zh-CN', { hour12: false }).slice(0, 16) : '-'

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
.text-muted { color: #c0c4cc; }
.ml-8 { margin-left: 8px; }
.ml-12 { margin-left: 12px; }
.mr-4 { margin-right: 4px; }
</style>
