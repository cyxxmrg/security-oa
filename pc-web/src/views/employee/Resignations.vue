<template>
  <div class="page-container">
    <ResignationStatCards :stats="stats" />

    <ResignationFilterBar
      v-model:active-status="activeStatus"
      v-model:keyword="filters.keyword"
      @tab-change="handleTabChange"
      @search="loadList"
      @create="openCreate"
    />

    <el-card class="content-card" shadow="never">
      <template #header>
        <div class="card-header">
          <span class="card-header__bar" />
          <span class="card-header__title">离职申请</span>
          <span class="card-header__count">{{ pagination.total }}</span>
          <span class="card-header__suffix">条</span>
        </div>
      </template>
      <ResignationTable
        :list="list"
        :loading="loading"
        :page="pagination.page"
        :page-size="pagination.pageSize"
        :total="pagination.total"
        @detail="openDetail"
        @submit="handleSubmit"
        @complete="openComplete"
        @cancel="handleCancel"
        @page-change="(p: number) => { pagination.page = p; loadList() }"
        @size-change="(s: number) => { pagination.pageSize = s; pagination.page = 1; loadList() }"
      />
    </el-card>

    <CreateResignationDialog
      v-model:visible="createDialogVisible"
      :form="createForm"
      :users="activeUserList"
      :preview-visible="previewVisible"
      :preview="preview"
      :previewing="previewing"
      :submitting="submitting"
      @preview="loadSettlementPreview"
      @save-draft="saveDraft"
      @submit-form="submitForm"
    />

    <CompleteResignationDialog
      v-model:visible="completeDialogVisible"
      :form="completeForm"
      :row="completeRow"
      :submitting="submitting"
      @submit="submitComplete"
      @upload-cert="handleCompleteFileUpload"
    />

    <ResignationDetailDialog v-model:visible="detailDialogVisible" :row="detailRow" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { EditPen, Document, CircleCheck } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { resignations } from '@/api/modules'
import { todayStr } from './components/resignations/types'
import ResignationStatCards from './components/resignations/ResignationStatCards.vue'
import ResignationFilterBar from './components/resignations/ResignationFilterBar.vue'
import ResignationTable from './components/resignations/ResignationTable.vue'
import CreateResignationDialog, { type ResignationForm } from './components/resignations/CreateResignationDialog.vue'
import CompleteResignationDialog, { type CompleteForm } from './components/resignations/CompleteResignationDialog.vue'
import ResignationDetailDialog from './components/resignations/ResignationDetailDialog.vue'

// 离职申请列表项
interface ResignationItem {
  id: number
  status?: string
  user_id?: number
  user?: { name?: string; department?: string }
  resign_date?: string
  resign_type?: string
  reason?: string
  [key: string]: unknown
}

// 员工（用户列表项）
interface EmployeeUser {
  id: number
  name?: string
  is_active?: boolean
  [key: string]: unknown
}

// 离职结算预览
interface SettlementPreview {
  base_salary?: number
  daily_salary?: number
  expected_days?: number
  actual_days?: number
  total_amount?: number
  final_salary?: number
  leave_balance?: number
  severance_pay?: number
}

// 上传回调参数
interface UploadOpt {
  file: File
  onSuccess?: (res: unknown) => void
  onError?: (err: Error) => void
}

// 办结 payload
interface CompletePayload {
  all_assets_returned: boolean
  paid_date: string
  paid_method: string | null
  certificate_file_id?: number
}

// API 错误
interface ApiError {
  message?: string
  response?: { data?: { message?: string } }
}

// 分页列表响应
interface PaginatedResponse<T> {
  data?: T[] | { data?: T[]; items?: T[]; total?: number }
  total?: number
  meta?: { total?: number }
  [key: string]: unknown
}

// 列表请求参数
interface ListParams {
  page?: number
  per_page?: number
  status?: string
  keyword?: string
  month?: string
}

// ============== 状态卡片 ==============
const stats = reactive([
  { label: '草稿',     value: '0', icon: EditPen,     color: '#909399' },
  { label: '待审批',   value: '0', icon: Document,    color: '#BA7517' },
  { label: '本月办结', value: '0', icon: CircleCheck, color: '#1D9E75' },
])

async function loadStats() {
  try {
    const r = await resignations.list({ per_page: 1, status: 'draft' }) as PaginatedResponse<unknown>
    // V0.6.3+ 响应: {code, data: {data, total, current_page, ...}}, 后端 paginate 包装
    const total = Number(r?.data?.total ?? r?.total ?? 0)
    stats[0].value = String(total)
  } catch (e) { /* ignore */ }
  try {
    const r = await resignations.list({ per_page: 1, status: 'pending' }) as PaginatedResponse<unknown>
    const total = Number(r?.data?.total ?? r?.total ?? 0)
    stats[1].value = String(total)
  } catch (e) { /* ignore */ }
  try {
    const now = new Date()
    const ym = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
    const r = await resignations.list({ per_page: 1, status: 'completed', month: ym }) as PaginatedResponse<unknown>
    const total = Number(r?.data?.total ?? r?.total ?? 0)
    stats[2].value = String(total)
  } catch (e) { /* ignore */ }
}

// ============== 通用 ==============
const loading = ref(false)
const submitting = ref(false)
const previewing = ref(false)
const list = ref<ResignationItem[]>([])
const userList = ref<EmployeeUser[]>([])

const filters = reactive({ keyword: '' })
const activeStatus = ref('draft')
const pagination = reactive({ page: 1, pageSize: 10, total: 0 })

const activeUserList = computed(() => userList.value.filter((u: EmployeeUser) => u.is_active !== false))

async function loadList() {
  loading.value = true
  try {
    const params: ListParams = {
      page: pagination.page,
      per_page: pagination.pageSize,
    }
    if (activeStatus.value) params.status = activeStatus.value
    if (filters.keyword) params.keyword = filters.keyword
const data = await resignations.list(params) as PaginatedResponse<ResignationItem>
    // V0.6.3+ 响应: {code, data: {data: [...], total, current_page, ...}}
    const payload = (data?.data ?? data ?? {}) as { data?: ResignationItem[]; items?: ResignationItem[]; total?: number } | ResignationItem[]
    const items = Array.isArray(payload) ? payload : (payload?.data ?? payload?.items ?? [])
    list.value = items
    pagination.total = Number(
      (payload as { total?: number })?.total ?? data?.data?.total ?? data?.total ?? data?.meta?.total ?? 0
    )
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

function handleTabChange() {
  pagination.page = 1
  loadList()
}

// ============== 详情 ==============
const detailDialogVisible = ref(false)
const detailRow = ref<ResignationItem | null>(null)
async function openDetail(row: ResignationItem) {
  try {
    const data = await resignations.show(row.id) as PaginatedResponse<ResignationItem>
    detailRow.value = (data?.data as ResignationItem) || data || row
  } catch (e) {
    detailRow.value = row
  }
  detailDialogVisible.value = true
}

// ============== 发起离职 ==============
const createDialogVisible = ref(false)
const previewVisible = ref(false)
const preview = reactive<SettlementPreview>({})

const createForm = reactive<ResignationForm>({
  user_id: null,
  resign_date: '',
  notice_date: '',
  last_work_day: '',
  resign_type: '',
  reason: '',
  handover_to_user_id: null,
  handover_note: '',
  assets: [{ name: '笔记本电脑', returned: true, note: '完好' }],
  final_salary: 0,
  leave_balance: 0,
  severance_pay: 0,
  social_security_cutoff: '',
})

function openCreate() {
  Object.assign(createForm, {
    user_id: null,
    resign_date: '',
    notice_date: todayStr(),
    last_work_day: '',
    resign_type: '',
    reason: '',
    handover_to_user_id: null,
    handover_note: '',
    assets: [{ name: '笔记本电脑', returned: true, note: '完好' }],
    final_salary: 0,
    leave_balance: 0,
    severance_pay: 0,
    social_security_cutoff: '',
  })
  previewVisible.value = false
  Object.keys(preview).forEach(k => delete preview[k])
  createDialogVisible.value = true
}

async function loadSettlementPreview() {
  if (!createForm.user_id || !createForm.resign_date) {
    ElMessage.warning('请先选择员工与离职日期')
    return
  }
  previewing.value = true
  try {
    const r = await resignations.settlementPreview({ user_id: createForm.user_id, resign_date: createForm.resign_date }) as PaginatedResponse<SettlementPreview>
    const d = (r?.data as SettlementPreview) || r || {}
    Object.assign(preview, {
      base_salary: d.base_salary ?? 0,
      daily_salary: d.daily_salary ?? 0,
      expected_days: d.expected_days ?? 0,
      actual_days: d.actual_days ?? 0,
      total_amount: d.total_amount ?? 0,
    })
    if (d.final_salary != null) createForm.final_salary = Number(d.final_salary)
    if (d.leave_balance != null) createForm.leave_balance = Number(d.leave_balance)
    if (d.severance_pay != null) createForm.severance_pay = Number(d.severance_pay)
    previewVisible.value = true
    ElMessage.success('已自动计算')
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '计算失败')
  } finally {
    previewing.value = false
  }
}

function buildPayload() {
  return {
    user_id: createForm.user_id,
    resign_date: createForm.resign_date,
    notice_date: createForm.notice_date || null,
    last_work_day: createForm.last_work_day,
    resign_type: createForm.resign_type,
    reason: createForm.reason,
    handover_to_user_id: createForm.handover_to_user_id || null,
    handover_note: createForm.handover_note || null,
    assets: createForm.assets.filter(a => a.name),
    final_salary: createForm.final_salary || 0,
    leave_balance: createForm.leave_balance || 0,
    severance_pay: createForm.severance_pay || 0,
    social_security_cutoff: createForm.social_security_cutoff || null,
  }
}

async function saveDraft() {
  if (!createForm.user_id) { ElMessage.warning('请选择员工'); return }
  if (!createForm.resign_type) { ElMessage.warning('请选择离职类型'); return }
  submitting.value = true
  try {
    await resignations.create(buildPayload())
    ElMessage.success('草稿已保存')
    createDialogVisible.value = false
    loadList()
    loadStats()
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '保存失败')
  } finally {
    submitting.value = false
  }
}

async function submitForm() {
  submitting.value = true
  try {
    const data = await resignations.create(buildPayload()) as PaginatedResponse<{ id: number }>
    // V1.2.16 fix: 正确解包 id (API 返回 {code, data: {id, ...}})
    const created = data?.data as { id?: number } | undefined
    const id = created?.id ?? (data as { id?: number })?.id
    if (id) {
      try { await resignations.submit(id) } catch (e) { /* 若后端无该方法, 忽略 */ }
    }
    ElMessage.success('已提交审批')
    createDialogVisible.value = false
    activeStatus.value = 'pending'
    pagination.page = 1
    loadList()
    loadStats()
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

// ============== 提交/审批/撤回 ==============
async function handleSubmit(row: ResignationItem) {
  try {
    await resignations.submit(row.id)
    ElMessage.success('已提交审批')
    loadList()
    loadStats()
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '提交失败')
  }
}

// V1.2.7h: 离职审批统一进审批中心, 此处不再处理
async function handleCancel(row: ResignationItem) {
  try {
    await ElMessageBox.confirm('确认撤回该离职申请？撤回后将无法恢复', '撤回确认', { type: 'warning' })
  } catch { return }
  try {
    await resignations.cancel(row.id)
    ElMessage.success('已撤回')
    loadList()
    loadStats()
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '撤回失败')
  }
}

// ============== 办结 ==============
const completeDialogVisible = ref(false)
const completeRow = ref<ResignationItem | null>(null)
const completeForm = reactive<CompleteForm>({
  all_assets_returned: false,
  paid_date: '',
  paid_method: 'transfer',
  certificate_file_id: null,
  certificate_file_name: '',
})

function openComplete(row: ResignationItem) {
  completeRow.value = row
  Object.assign(completeForm, {
    all_assets_returned: false,
    paid_date: todayStr(),
    paid_method: 'transfer',
    certificate_file_id: null,
    certificate_file_name: '',
  })
  completeDialogVisible.value = true
}

async function handleCompleteFileUpload(opt: UploadOpt) {
  try {
    const fd = new FormData()
    fd.append('file', opt.file)
    const res = await post<{ id?: number; file_id?: number; data?: { id?: number }; message?: string }>('/disk/upload', fd)
    const id = res?.id || res?.file_id || res?.data?.id
    if (id) {
      completeForm.certificate_file_id = id
      completeForm.certificate_file_name = opt.file.name
      opt.onSuccess?.(res)
      ElMessage.success('已上传')
    } else {
      opt.onError?.(new Error(res?.message || '上传失败'))
    }
  } catch (e) {
    const err = e as ApiError
    opt.onError?.(err as unknown as Error)
    ElMessage.error(err?.response?.data?.message || err?.message || '上传失败')
  }
}

async function submitComplete() {
  submitting.value = true
  try {
    const payload: CompletePayload = {
      all_assets_returned: completeForm.all_assets_returned,
      paid_date: completeForm.paid_date,
      paid_method: completeForm.paid_method || null,
    }
    if (completeForm.certificate_file_id) payload.certificate_file_id = completeForm.certificate_file_id
    await resignations.complete(completeRow.value!.id, payload)
    ElMessage.success('离职已办结')
    completeDialogVisible.value = false
    loadList()
    loadStats()
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '办结失败')
  } finally {
    submitting.value = false
  }
}

// ============== 加载员工 ==============
async function loadUsers() {
  try {
    const u = await get('/employees', { per_page: 500 }) as PaginatedResponse<EmployeeUser>
    // V1.2.16 fix: API 返回 data.data[...] (paginator), 不是 data.items
    const pagData = u?.data as { data?: EmployeeUser[]; items?: EmployeeUser[] } | undefined
    userList.value = pagData?.data ?? pagData?.items ?? []
  } catch (e) {
    userList.value = []
  }
}

onMounted(async () => {
  await Promise.all([loadUsers(), loadList(), loadStats()])
})
</script>

<style lang="scss" scoped>
.page-container {
  padding: 20px 24px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: #f5f7fa;
  min-height: 100%;
}

.content-card { border: none; }
.content-card :deep(.el-card__header) { padding: 16px 20px; border-bottom: 1px solid #ebeef5; }
.content-card :deep(.el-card__body)  { padding: 16px 20px; }

.card-header { display: flex; align-items: center; gap: 8px; }
.card-header__bar { width: 4px; height: 16px; background: linear-gradient(180deg, #1D9E75, #58C499); border-radius: 2px; }
.card-header__title { font-size: 15px; font-weight: 600; color: #303133; }
.card-header__count { font-size: 13px; color: #1D9E75; font-weight: 600; margin-left: 4px; }
.card-header__suffix { font-size: 13px; color: #909399; }
</style>
