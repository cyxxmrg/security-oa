<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h2>项目审批</h2>
        <el-tag v-if="pendingCount > 0" type="danger" effect="dark" round class="ml-12">待我审批 {{ pendingCount }}</el-tag>
      </div>
      <div class="header-right">
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Search" @click="showSearch = !showSearch">筛选</el-button>
      </div>
    </div>

    <transition name="el-fade-in">
      <div v-if="showSearch" class="filter-bar">
        <el-input v-model="filter.keyword" placeholder="单号/项目名称" clearable style="width: 200px" @keyup.enter="handleSearch" />
        <el-select v-model="filter.subType" placeholder="审批子类" clearable style="width: 160px">
          <el-option v-for="t in subTypeOptions" :key="t.value" :label="t.label" :value="t.value" />
        </el-select>
        <el-select v-model="filter.stage" placeholder="目标阶段" clearable style="width: 140px">
          <el-option v-for="s in stageOptions" :key="s.value" :label="s.label" :value="s.value" />
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
        <div class="stat-content"><div><div class="stat-label">新建项目</div><div class="stat-value success">{{ createPending }}</div></div><el-icon :size="32" class="stat-icon success"><Plus /></el-icon></div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content"><div><div class="stat-label">阶段流转</div><div class="stat-value warning">{{ stagePending }}</div></div><el-icon :size="32" class="stat-icon warning"><Right /></el-icon></div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-content"><div><div class="stat-label">本月立项</div><div class="stat-value info">{{ monthlyCreated }}</div></div><el-icon :size="32" class="stat-icon info"><Files /></el-icon></div>
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

      <el-table :data="filteredList" stripe border style="width: 100%" v-loading="loading" @row-dblclick="(r) => handleDetail(r as ProjectApproval)">
        <el-table-column type="index" width="50" align="center" />
        <el-table-column prop="code" label="单号" width="160" fixed>
          <template #default="{ row }"><el-link type="primary" :underline="false" @click="handleDetail(row as ProjectApproval)">{{ row.code }}</el-link></template>
        </el-table-column>
        <el-table-column label="审批子类" width="130">
          <template #default="{ row }">
            <el-tag :type="subTypeTagType(row.subType)" effect="plain">
              <el-icon class="mr-4"><component :is="subTypeIcon(row.subType)" /></el-icon>
              {{ subTypeLabel(row.subType) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="项目名称" min-width="220" show-overflow-tooltip />
        <el-table-column label="目标阶段" width="120" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.to_stage" effect="plain" type="primary">→ {{ stageLabel(row.to_stage) }}</el-tag>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="项目金额" width="130" align="right">
          <template #default="{ row }">
            <span class="amount">¥ {{ formatMoney(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="项目经理" width="100">
          <template #default="{ row }">{{ row.manager?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="工期" width="180">
          <template #default="{ row }">
            <span v-if="row.start_date && row.end_date">{{ formatDate(row.start_date) }} ~ {{ formatDate(row.end_date) }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="发起人" width="100">
          <template #default="{ row }">{{ row.initiator?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="dark">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleDetail(row as ProjectApproval)">详情</el-button>
            <template v-if="row.status === 'pending' && canApprove(row as ProjectApproval)">
              <el-button link type="success" size="small" @click="handleApprove(row as ProjectApproval)">通过</el-button>
              <el-button link type="danger" size="small" @click="handleReject(row as ProjectApproval)">驳回</el-button>
              <el-button link type="warning" size="small" @click="handleTransfer(row as ProjectApproval)">转交</el-button>
            </template>
            <el-tag v-else-if="row.status === 'pending' && isSelfSubmitted(row as ProjectApproval) && !isAdmin" size="small" type="info" effect="plain">自己提交, 不能自审</el-tag>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loading && filteredList.length === 0" description="暂无数据" :image-size="100" />
    </div>

    <el-dialog v-model="showDetailDialog" :title="`项目审批详情 — ${currentItem?.code || ''}`" width="1500px" destroy-on-close>
      <div v-if="currentItem">
        <el-descriptions :column="3" border>
          <el-descriptions-item label="单号">{{ currentItem.code }}</el-descriptions-item>
          <el-descriptions-item label="子类"><el-tag :type="subTypeTagType(currentItem.subType)" effect="plain">{{ subTypeLabel(currentItem.subType) }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag :type="statusTagType(currentItem.status)" effect="dark">{{ statusLabel(currentItem.status) }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="项目名称" :span="3"><strong style="font-size: 16px;">{{ currentItem.title }}</strong></el-descriptions-item>
          <el-descriptions-item label="目标阶段">
            <el-tag v-if="currentItem.to_stage" type="primary" effect="plain">→ {{ stageLabel(currentItem.to_stage) }}</el-tag>
            <span v-else>-</span>
          </el-descriptions-item>
          <el-descriptions-item label="项目金额"><span class="amount">¥ {{ formatMoney(currentItem.amount) }}</span></el-descriptions-item>
          <el-descriptions-item label="工期">{{ formatDate(currentItem.start_date) }} ~ {{ formatDate(currentItem.end_date) }}</el-descriptions-item>
          <el-descriptions-item label="项目经理">{{ currentItem.manager?.name }}</el-descriptions-item>
          <el-descriptions-item label="客户">{{ currentItem.customer?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="发起人">{{ currentItem.initiator?.name }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="section-title">📋 项目详情</h4>
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item v-for="(v, k) in currentItem.detail" :key="k" :label="k">{{ v }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="section-title">🔁 审批流程</h4>
        <el-timeline>
          <el-timeline-item v-for="(n, i) in currentItem.flow" :key="i" :timestamp="formatDateTime(n.time)" :type="flowNodeType(n.action)" :hollow="i !== currentItem.flow.length - 1" size="large">
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
import { Refresh, Search, Bell, Plus, Right, Files } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import { approvalSubTypeLabel, localizeEnumText, projectStageLabel, statusLabel as commonStatusLabel } from '@/utils/labels'

type ElTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

interface FlowNode {
  time: string
  action: string
  operator?: string
  comment?: string
  [key: string]: unknown
}

interface ProjectApproval {
  id: number
  code?: string
  subType: string
  title: string
  to_stage: string | null
  amount: number | string
  manager?: { name?: string } | null
  start_date: string | null
  end_date: string | null
  initiator?: { name?: string } | null
  customer?: { name?: string } | null
  status: string
  currentApproverId?: number | null
  applicantId?: number | null
  detail?: Record<string, unknown>
  flow: FlowNode[]
  [key: string]: unknown
}

interface AuthMe {
  id?: number
  data?: { user?: { id?: number; is_system?: boolean; user_type?: string } }
  user?: { id?: number; is_system?: boolean; user_type?: string }
}

const loading = ref(false)
const showSearch = ref(false)
const showDetailDialog = ref(false)
const activeTab = ref('pending')
const list = ref<ProjectApproval[]>([])
const currentItem = ref<ProjectApproval | null>(null)
const approvalComment = ref('')
const filter = reactive({ keyword: '', subType: '', stage: '', dateRange: [] })

// 项目审批子类
const subTypeOptions = [
  { value: 'project_create', label: '新建项目' },
  { value: 'project_stage', label: '阶段流转' },
  { value: 'project_close', label: '项目结项' },
  { value: 'contract', label: '合同审批' },
  { value: 'contract_change', label: '合同变更' },
  { value: 'settlement', label: '项目结算' },
  { value: 'warranty', label: '质保金' },
  { value: 'design', label: '设计方案' },
  { value: 'change', label: '设计变更' },
  { value: 'other', label: '其他' }
]
const stageOptions = [
  // V1.2.10 与后端 migration 2024_01_02_000001 对齐 (7 个值), 移除 debug/acceptance
  { value: 'initiation', label: '立项' },
  { value: 'inquiry', label: '询价' },
  { value: 'contract', label: '合同阶段' },
  { value: 'purchase', label: '采购阶段' },
  { value: 'construction', label: '施工阶段' },
  { value: 'settlement', label: '结算阶段' },
  { value: 'warranty', label: '维保阶段' }
]
const priorityOptions = [
  { value: 'urgent', label: '紧急' }, { value: 'high', label: '高' },
  { value: 'normal', label: '普通' }, { value: 'low', label: '低' }
]
const subTypeLabel = (t: string) => subTypeOptions.find(x => x.value === t)?.label || approvalSubTypeLabel(t)
const stageLabel = (s: string) => stageOptions.find(x => x.value === s)?.label || projectStageLabel(s)
const priorityLabel = (p: string) => priorityOptions.find(x => x.value === p)?.label || p
const SUBTYPE_TAG_MAP: Record<string, ElTagType> = {
  project_create: 'success', project_stage: 'primary', project_close: 'info',
  contract: 'danger', contract_change: 'warning', settlement: 'success',
  warranty: 'info', design: 'primary', change: 'warning', other: 'info',
}
const subTypeTagType = (t: string): ElTagType => SUBTYPE_TAG_MAP[t] || 'info'
const subTypeIcon = (t: string) => ({
  project_create: 'Plus', project_stage: 'Right', project_close: 'CircleCheck',
  contract: 'Document', contract_change: 'EditPen', settlement: 'Money',
  warranty: 'Coin', design: 'Picture', change: 'Refresh', other: 'MoreFilled'
}[t] || 'MoreFilled')
const PRIORITY_TAG_MAP: Record<string, ElTagType> = { urgent: 'danger', high: 'warning', normal: 'primary', low: 'info' }
const priorityTagType = (p: string): ElTagType => PRIORITY_TAG_MAP[p] || 'info'
const statusLabel = (s: string) => commonStatusLabel(s)
const STATUS_TAG_MAP: Record<string, ElTagType> = { pending: 'warning', approved: 'success', rejected: 'danger', transferred: 'info', cancelled: 'info' }
const statusTagType = (s: string): ElTagType => STATUS_TAG_MAP[s] || 'info'
const FLOW_NODE_MAP: Record<string, ElTagType> = { submit: 'primary', approve: 'success', reject: 'danger', transfer: 'warning', comment: 'info' }
const flowNodeType = (a: string): ElTagType => FLOW_NODE_MAP[a] || 'info'
const flowActionLabel = (a: string) => ({ submit: '发起', approve: '通过', reject: '驳回', transfer: '转交', comment: '补充' }[a] || a)

const pendingCount = computed(() => list.value.filter(i => i.status === 'pending').length)
const createPending = computed(() => list.value.filter(i => i.status === 'pending' && i.subType === 'project_create').length)
const stagePending = computed(() => list.value.filter(i => i.status === 'pending' && i.subType === 'project_stage').length)
const monthlyCreated = computed(() => list.value.filter(i => i.subType === 'project_create' && i.status === 'approved').length)

const loadList = async () => {
  loading.value = true
  try {
    const res: unknown = await get('/approvals/project', { per_page: 200 })
    list.value = unwrapList(res).map((item: ProjectApproval) => ({ ...item, title: localizeEnumText(item.title) }))
  } catch { list.value = [] }
  finally { loading.value = false }
}

const filteredList = computed(() => list.value.filter(item => {
  if (activeTab.value === 'pending' && item.status !== 'pending') return false
  if (activeTab.value === 'approved' && item.status !== 'approved') return false
  if (activeTab.value === 'rejected' && item.status !== 'rejected') return false
  if (filter.keyword && !(item.code || '').includes(filter.keyword) && !(item.title || '').includes(filter.keyword)) return false
  if (filter.subType && item.subType !== filter.subType) return false
  if (filter.stage && item.to_stage !== filter.stage) return false
  return true
}))

const canApprove = (row: ProjectApproval) => row.status === 'pending' && Number(row.currentApproverId) === Number(myId.value)
// V1.2.7k: 系统账号可越权审批, 否则申请人不能审批自己的单子
const isSelfSubmitted = (row: ProjectApproval) => Number(row.applicantId) === Number(myId.value)
const isAdmin = ref(false)
const myId = ref<number | null>(null)
const loadMyId = async () => {
  try {
    const me = (await get('/auth/me')) as AuthMe
    myId.value = me?.data?.user?.id ?? me?.user?.id ?? me?.id ?? null
    isAdmin.value = me?.data?.user?.is_system === true || me?.data?.user?.user_type === 'system'
  } catch {}
}
const handleDetail = (row: ProjectApproval) => { currentItem.value = row; approvalComment.value = ''; showDetailDialog.value = true }
const handleApprove = async (row: ProjectApproval) => { try { await ElMessageBox.confirm('确定审批通过吗？', '审批确认', { type: 'success' }); await post(`/approvals/project/${row.id}/approve`, { comment: approvalComment.value || '同意' }); ElMessage.success('已通过'); showDetailDialog.value = false; loadList() } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as { message?: string })?.message || '操作失败') } }
const handleReject = async (row: ProjectApproval) => { if (!approvalComment.value.trim()) { ElMessage.warning('请填写驳回意见'); return }; try { await ElMessageBox.confirm('确定驳回吗？', '驳回确认', { type: 'warning' }); await post(`/approvals/project/${row.id}/reject`, { comment: approvalComment.value }); ElMessage.success('已驳回'); showDetailDialog.value = false; loadList() } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as { message?: string })?.message || '操作失败') } }
const handleTransfer = async (row: ProjectApproval) => { try { const { value: target } = await ElMessageBox.prompt('请输入转交给谁', '转交审批', { inputPlaceholder: '用户名' }); if (!target) return; await post(`/approvals/project/${row.id}/forward`, { target }); ElMessage.success(`已转交 ${target}`); showDetailDialog.value = false; loadList() } catch (e: unknown) { if (e !== 'cancel') ElMessage.error((e as { message?: string })?.message || '操作失败') } }
const handleSearch = () => {}
const resetFilter = () => { filter.keyword = ''; filter.subType = ''; filter.stage = ''; filter.dateRange = [] }
const formatDate = (d: string | null) => d ? new Date(d).toISOString().slice(0, 10) : '-'
const formatDateTime = (d: string) => d ? new Date(d).toLocaleString('zh-CN', { hour12: false }).slice(0, 16) : '-'
const formatMoney = (v: number | string) => { const n = parseFloat(String(v)); return isNaN(n) ? '0.00' : n.toFixed(2) }

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
.text-muted { color: #c0c4cc; }
.ml-8 { margin-left: 8px; }
.ml-12 { margin-left: 12px; }
.mr-4 { margin-right: 4px; }
</style>
