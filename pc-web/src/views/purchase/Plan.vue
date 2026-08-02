<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">采购计划管理</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="handleReset">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建计划</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="计划编号 / 标题" clearable style="width: 220px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 140px">
            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
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

      <PlanTable
        :list="pagedList"
        :loading="loading"
        :page="page"
        :page-size="pageSize"
        :total="filteredList.length"
        @view="handleView"
        @edit="handleEdit"
        @delete="handleDelete"
        @convert-to-order="handleConvertToOrder"
        @page-change="(p: number) => page = p"
        @size-change="(s: number) => { pageSize = s; page = 1 }"
      />
    </div>

    <!-- 新建 / 编辑 计划 -->
    <el-dialog v-model="showFormDialog" :title="formMode === 'create' ? '新建采购计划' : '编辑采购计划'" width="1500px" :close-on-click-modal="false">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="110px">
        <el-form-item label="计划标题" prop="title">
          <el-input v-model="formData.title" placeholder="如：海康摄像头批量采购" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="关联需求">
              <el-select v-model="formData.requirement_id" placeholder="可选" filterable clearable style="width: 100%">
                <el-option v-for="r in requirementOptions" :key="r.id" :label="`${r.code}（${r.title}）`" :value="r.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="优先级" prop="priority">
              <el-select v-model="formData.priority" placeholder="请选择" style="width: 100%">
                <el-option v-for="p in priorityOptions" :key="p.value" :label="p.label" :value="p.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="金额" prop="total_amount">
              <el-input-number v-model="formData.total_amount" :min="0" :step="1000" :precision="2" style="width: 100%" placeholder="元" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="计划日期" prop="plan_date">
              <el-date-picker v-model="formData.plan_date" type="date" placeholder="选择日期" style="width: 100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="备注">
          <el-input v-model="formData.remark" type="textarea" :rows="3" placeholder="可选" maxlength="500" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showFormDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 详情 dialog -->
    <el-dialog v-model="showDetailDialog" title="采购计划详情" width="1500px">
      <div v-if="currentRow" class="detail-view">
        <PurchaseFlowStepper entity-type="plan" :entity-status="currentRow.status" />
        <PurchaseFlowHistory entity-type="plan" :entity-id="currentRow.id" />
        <div class="detail-row"><span class="label">计划编号</span><span class="value">{{ currentRow.code }}</span></div>
        <div class="detail-row"><span class="label">标题</span><span class="value">{{ currentRow.title }}</span></div>
        <div class="detail-row"><span class="label">关联需求</span><span class="value link-text" @click="handleViewRequirement(currentRow)">{{ currentRow.requirement?.code || currentRow.requirement_id || '-' }}</span></div>
        <div class="detail-row"><span class="label">金额</span><span class="value" style="color:#1D9E75;font-weight:600">¥ {{ formatMoney(currentRow.total_amount) }}</span></div>
        <div class="detail-row"><span class="label">计划日期</span><span class="value">{{ currentRow.plan_date ? String(currentRow.plan_date).slice(0, 10) : '-' }}</span></div>
        <div class="detail-row"><span class="label">优先级</span><span class="value">{{ priorityLabel(currentRow.priority) }}</span></div>
        <div class="detail-row"><span class="label">状态</span><span class="value"><el-tag :type="statusTagType(currentRow.status)" effect="plain" size="small">{{ statusLabel(currentRow.status) }}</el-tag></span></div>
        <div class="detail-row"><span class="label">备注</span><span class="value">{{ currentRow.remark || '-' }}</span></div>
        <div class="detail-row"><span class="label">创建时间</span><span class="value">{{ currentRow.created_at }}</span></div>
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
import { unwrapList } from '@/utils/response'
import { purchaseFlow } from '@/api/purchase-flow'
import PlanTable from './components/plan/PlanTable.vue'
import PlanFilterBar from './components/plan/PlanFilterBar.vue'
import PlanStatCards from './components/plan/PlanStatCards.vue'
import PurchaseFlowStepper from '@/components/PurchaseFlowStepper.vue'
import PurchaseFlowHistory from '@/components/PurchaseFlowHistory.vue'


// === 类型（替代 any）===
interface PurchasePlan {
  id: number
  code: string
  title: string
  status: string
  priority: string
  total_amount?: number | string
  plan_date?: string
  requirement_id?: number | null
  project_id?: number | null
  remark?: string
  requirement?: { id?: number; code: string } | null
  approver_id?: number | null
  approved_at?: string | null
  approve_remark?: string | null
  created_at?: string
  [key: string]: unknown
}
interface RequirementOption {
  id: number
  code: string
  title: string
  material?: string
  [key: string]: unknown
}
interface SupplierLite {
  id: number
  [key: string]: unknown
}
interface PlanPayload {
  title: string
  total_amount: number
  plan_date: string | null
  priority: string
  remark: string | null
  requirement_id?: number
  project_id?: number
}
type ElTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

// === 状态选项（与后端 draft/submitted/approved/rejected/cancelled 一致） ===
const statusOptions = [
  { value: 'draft', label: '草稿' },
  { value: 'submitted', label: '已提交' },
  { value: 'approved', label: '已通过' },
  { value: 'rejected', label: '已驳回' },
  { value: 'cancelled', label: '已取消' }
]

const priorityOptions = [
  { value: 'low', label: '低' },
  { value: 'medium', label: '中' },
  { value: 'high', label: '高' },
  { value: 'urgent', label: '紧急' }
]

// 优先级 label 字典函数（v0.3.9 修 Plan.vue console 错误）
const priorityLabel = (v: string) => priorityOptions.find(o => o.value === v)?.label || v

// === 需求下拉（用于关联）===
const requirementOptions = ref<RequirementOption[]>([])

// === 列表 / 加载 ===
const loading = ref(false)
const submitting = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<PurchasePlan[]>([])

const searchForm = reactive({ keyword: '', status: '' })

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter(r => (r.code || '').toLowerCase().includes(kw) || (r.title || '').toLowerCase().includes(kw))
  }
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const statCards = computed(() => [
  { label: '计划总数', value: list.value.length, icon: Document, color: '#0C447C' },
  { label: '草稿', value: list.value.filter(l => l.status === 'draft').length, icon: List, color: '#909399' },
  { label: '已通过', value: list.value.filter(l => l.status === 'approved').length, icon: CircleCheck, color: '#1D9E75' },
  { label: '已驳回', value: list.value.filter(l => l.status === 'rejected').length, icon: CircleClose, color: '#A32D2D' }
])

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 200, page: 1 }
    if (searchForm.keyword) params.keyword = searchForm.keyword
    if (searchForm.status) params.status = searchForm.status
    const res: unknown = await purchase.getPlans(params)
    // V0.6.3 不再解包, 兼容两种形态
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadRequirements = async () => {
  try {
    const res: unknown = await purchase.getRequirements({ per_page: 200 })
    // V0.6.3 不再解包, 兼容两种形态
    requirementOptions.value = unwrapList(res).map((r: RequirementOption) => ({ id: r.id, code: r.code, title: r.material || r.title || r.code }))
  } catch {
    requirementOptions.value = []
  }
}

const handleSearch = () => { page.value = 1 }
const handleReset = () => { searchForm.keyword = ''; searchForm.status = ''; page.value = 1; loadList() }

// === 详情 ===
const showDetailDialog = ref(false)
const currentRow = ref<PurchasePlan | null>(null)
const handleView = (row: PurchasePlan) => {
  currentRow.value = row
  showDetailDialog.value = true
}
const handleViewRequirement = (row: PurchasePlan) => {
  ElMessage.info(`查看需求：${row.requirement?.code || row.requirement_id || '-'}（占位）`)
}

// === 提交审批 / 审批 ===
const submittingAction = ref(false)
const handleSubmitPlan = async (row: PurchasePlan) => {
  if (row.status !== 'draft') {
    ElMessage.warning('仅「草稿」状态的计划可提交')
    return
  }
  try {
    await ElMessageBox.confirm(`确认提交采购计划「${row.code}」进入审批流程？`, '提交确认', { type: 'info' })
  } catch { return }
  submittingAction.value = true
  try {
    await purchaseFlow.submitPlan(row.id)
    ElMessage.success('已提交审批')
    await loadList()
  } catch { /* 拦截器已提示 */ }
  finally { submittingAction.value = false }
}

// V1.2.7h: 采购计划审批统一进审批中心, 此处不再处理
// PlanTable 行内"审批"快捷按钮 — 跳转提示
const handleApproveFromTable = (row: PurchasePlan) => {
  ElMessage.info(`请前往「审批中心」处理采购计划 ${row.code} 的审批`)
}

// V0.6.2.2: 已审批计划 → 转采购订单 (一键)
const handleConvertToOrder = async (row: PurchasePlan) => {
  if (row.status !== 'approved') {
    ElMessage.warning('仅「已审批」的计划可转订单')
    return
  }
  let supplierId: number | null = null
  try {
    // 拉供应商列表
    const { supplier } = await import('@/api/supplier')
    const sr: unknown = await supplier.list({ per_page: 200 })
    const sups = unwrapList(sr) as SupplierLite[]
    if (!sups.length) {
      ElMessage.warning('暂无供应商, 请先在供应商库添加')
      return
    }
    supplierId = sups[0].id
  } catch (e) {
    ElMessage.warning('加载供应商失败')
    return
  }
  try {
    await ElMessageBox.confirm(
      `将已审批计划「${row.code}」转为采购订单，使用供应商 #${supplierId}，金额 ¥${formatMoney(row.total_amount)} ？`,
      '转采购订单',
      { type: 'success', confirmButtonText: '确认转订单' }
    )
  } catch { return }
  submittingAction.value = true
  try {
    const r: unknown = await purchaseFlow.createOrder({
      plan_id: row.id,
      supplier_id: supplierId,
      title: row.title,
      total_amount: Number(row.total_amount) || 0,
      path: 'manual',
      notes: '从已审批计划自动生成',
    })
    const order = (r as { data?: { code?: string } })?.data
    const code = order?.code || 'PO-'
    ElMessage.success(`已生成订单号 ${code}`)
    await loadList()
  } catch { /* 拦截器已提示 */ }
  finally { submittingAction.value = false }
}

// === 新建 / 编辑 ===
const showFormDialog = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const formRef = ref()
const formData = reactive({
  id: 0,
  code: '',
  title: '',
  requirement_id: null as number | null,
  project_id: null as number | null,
  total_amount: 0,
  plan_date: '',
  priority: 'medium',
  remark: ''
})
const formRules = {
  title: [{ required: true, message: '请输入计划标题', trigger: 'blur' }],
  total_amount: [{ required: true, type: 'number' as const, min: 0, message: '请输入金额', trigger: 'blur' }],
  plan_date: [{ required: true, message: '请选择计划采购日期', trigger: 'change' }],
  priority: [{ required: true, message: '请选择优先级', trigger: 'change' }]
}

const resetForm = () => {
  Object.assign(formData, {
    id: 0, code: '', title: '', requirement_id: null, project_id: null,
    total_amount: 0, plan_date: '', priority: 'medium', remark: ''
  })
}

const handleAdd = () => {
  formMode.value = 'create'
  resetForm()
  showFormDialog.value = true
}

const handleEdit = (row: PurchasePlan) => {
  if (row.status !== 'draft') {
    ElMessage.warning('仅「草稿」状态可编辑')
    return
  }
  formMode.value = 'edit'
  Object.assign(formData, {
    id: row.id,
    code: row.code || '',
    title: row.title || '',
    requirement_id: row.requirement_id || null,
    project_id: row.project_id || null,
    total_amount: Number(row.total_amount || 0),
    plan_date: row.plan_date ? String(row.plan_date).slice(0, 10) : '',
    priority: row.priority || 'medium',
    remark: row.remark || ''
  })
  showFormDialog.value = true
}

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    const payload: PlanPayload = {
      title: formData.title,
      total_amount: Number(formData.total_amount) || 0,
      plan_date: formData.plan_date || null,
      priority: formData.priority || 'medium',
      remark: formData.remark || null
    }
    if (formData.requirement_id) payload.requirement_id = formData.requirement_id
    if (formData.project_id) payload.project_id = formData.project_id
    if (formMode.value === 'create') {
      await purchase.createPlan(payload)
      ElMessage.success('采购计划创建成功')
    } else {
      await purchase.updatePlan(formData.id, payload)
      ElMessage.success('采购计划已更新')
    }
    showFormDialog.value = false
    page.value = 1
    await loadList()
  } catch { /* 拦截器已提示 */ }
  finally { submitting.value = false }
}

// === 删除 ===
const handleDelete = async (row: PurchasePlan) => {
  if (row.status === 'approved') {
    ElMessage.warning('已审批的计划不可删除')
    return
  }
  try {
    await ElMessageBox.confirm(
      `确认删除采购计划「${row.code || row.title}」？该操作不可恢复。`,
      '删除确认',
      { type: 'warning', confirmButtonText: '确认删除', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    await purchase.deletePlan(row.id)
    ElMessage.success('已删除')
    await loadList()
    if (pagedList.value.length === 0 && page.value > 1) page.value -= 1
  } catch { /* 拦截器已提示 */ }
}

// === 工具 ===
const formatMoney = (n?: number | string) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const statusLabel = (s: string) => statusOptions.find(o => o.value === s)?.label || s || '-'
const STATUS_TAG_MAP: Record<string, ElTagType> = {
  draft: 'info', submitted: 'warning', approved: 'success', rejected: 'danger', cancelled: 'info',
}
const statusTagType = (s: string): ElTagType => STATUS_TAG_MAP[s] || 'info'

onMounted(() => { loadList(); loadRequirements() })
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
.detail-view {
  .detail-row {
    display: flex; padding: 10px 0; border-bottom: 1px dashed #ebeef5;
    .label { width: 110px; color: #909399; font-size: 13px; }
    .value { flex: 1; color: #303133; font-size: 14px; }
    &:last-child { border-bottom: none; }
  }
}
</style>
