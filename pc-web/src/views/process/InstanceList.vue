<template>
  <div class="page-container">
    <div class="page-header">
      <div class="title-area">
        <span class="page-title">工序实例</span>
        <el-tag effect="light" type="info">{{ total }} 个实例</el-tag>
      </div>
      <div class="header-actions">
        <el-button type="primary" :icon="Plus" @click="handleCreate">新建工序实例</el-button>
      </div>
    </div>

    <div class="content-card">
      <InstanceStatCards :stats="stats" @click="quickFilter" />

      <InstanceFilterBar
        :form="searchForm"
        :project-options="projectOptions"
        :status-options="statusOptions"
        @search="handleSearch"
        @reset="handleReset"
      />

      <InstanceTable
        :list="list"
        :loading="loading"
        @view="handleView"
        @view-project="handleViewProject"
        @accept="handleAccept"
        @reject="handleReject"
        @progress="handleProgress"
      />

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </div>

    <InstanceActionDialog
      v-model="actionDialog.visible"
      :loading="actionDialog.loading"
      :title="actionDialog.title"
      :reason-label="actionDialog.reasonLabel"
      :reason-key="actionDialog.reasonKey"
      :reason-options="actionDialog.reasonOptions"
      :comment-label="actionDialog.commentLabel"
      :comment-key="actionDialog.commentKey"
      :comment-placeholder="actionDialog.commentPlaceholder"
      :form="actionDialog.form"
      :rules="actionDialog.rules"
      @submit="submitAction"
    />

    <ProgressUpdateDialog
      v-model="progressDialog.visible"
      :loading="progressDialog.loading"
      :form="progressDialog.form"
      @submit="submitProgress"
    />

    <CreateInstanceDialog
      v-model="createDialog.visible"
      :loading="createDialog.loading"
      :form="createDialog.form"
      :rules="createRules"
      :project-options="projectOptions"
      :user-options="userOptions"
      :template-options="templateOptions"
      @submit="submitCreate"
    />

    <InstanceDetailDialog
      v-model:visible="detailDialog.visible"
      :id="detailDialog.id"
      @done="loadList"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { processApi, getProjectList, getUserList } from '@/api/modules'
import { unwrapList, unwrapPaginate } from '@/utils/response'

import InstanceStatCards from './components/instance-list/InstanceStatCards.vue'
import InstanceFilterBar from './components/instance-list/InstanceFilterBar.vue'
import InstanceTable from './components/instance-list/InstanceTable.vue'
import InstanceActionDialog from './components/instance-list/InstanceActionDialog.vue'
import ProgressUpdateDialog from './components/instance-list/ProgressUpdateDialog.vue'
import InstanceDetailDialog from './InstanceDetailDialog.vue'
import CreateInstanceDialog from './components/instance-list/CreateInstanceDialog.vue'

import type { Instance, InstanceStats, SearchForm, ProjectOption, UserOption, TemplateOption } from './components/instance-list/types'
import { STATUS_OPTIONS, REJECT_REASONS } from './components/instance-list/types'

// v0.3.19 拆 InstanceList.vue 712→380（-47%）
// 子组件: StatCards / FilterBar / Table / ActionDialog / ProgressUpdateDialog / CreateInstanceDialog

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)

const searchForm = reactive<SearchForm>({
  project_id: null,
  status: '',
  is_overdue: false,
})

const list = ref<Instance[]>([])
const projectOptions = ref<ProjectOption[]>([])
const userOptions = ref<UserOption[]>([])
const templateOptions = ref<TemplateOption[]>([])

const statusOptions = STATUS_OPTIONS

// ========== 加载 ==========
const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: page.value,
      page_size: pageSize.value,
    }
    if (searchForm.project_id) params.project_id = searchForm.project_id
    if (searchForm.status) params.status = searchForm.status
    if (searchForm.is_overdue) params.is_overdue = 1
    const res = await processApi.instanceList(params)
    // V0.6.3: res = {code, data: paginator}
    const pag = unwrapPaginate(res)
    const p = pag as unknown as { list: Record<string, unknown>[]; total?: number; meta?: { total?: number } }
    list.value = p.list as unknown as Instance[]
    total.value = Number(p.total ?? p.meta?.total ?? 0)
    loadStats()
  } catch (e) {
    console.error('加载工序实例列表失败', e)
    ElMessage.error('加载工序实例列表失败')
  } finally {
    loading.value = false
  }
}

const stats = reactive<InstanceStats>({ in_progress: 0, accepted: 0, rejected: 0, overdue: 0 })
const loadStats = async () => {
  try {
    const tasks: Array<Promise<void>> = []
    const fetchStatusCount = async (status: string, key: keyof InstanceStats, isOverdue?: boolean) => {
      const p: Record<string, unknown> = { page: 1, page_size: 1 }
      p.status = status
      if (isOverdue) p.is_overdue = 1
      const r = await processApi.instanceList(p)
      // V0.6.3: res = {code, data: paginator}
      stats[key] = unwrapPaginate(r).total
    }
    tasks.push(fetchStatusCount('in_progress', 'in_progress'))
    tasks.push(fetchStatusCount('accepted', 'accepted'))
    tasks.push(fetchStatusCount('rejected', 'rejected'))
    tasks.push(fetchStatusCount('in_progress', 'overdue', true))
    await Promise.all(tasks)
  } catch (e) {
    console.warn('加载工序统计失败', e)
  }
}

const loadProjects = async () => {
  try {
    const res = await getProjectList({ page: 1, per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    projectOptions.value = unwrapList(res) as unknown as ProjectOption[]
  } catch (e) {
    console.error('加载项目列表失败', e)
  }
}

const loadUsers = async () => {
  try {
    const res = await getUserList({ page: 1, per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    userOptions.value = unwrapList(res) as unknown as UserOption[]
  } catch (e) {
    console.error('加载用户列表失败', e)
  }
}

const loadTemplates = async () => {
  try {
    const res = await processApi.templateList({ page: 1, page_size: 200 })
    // V0.6.3: res = {code, data: paginator}
    templateOptions.value = unwrapList(res) as unknown as TemplateOption[]
  } catch (e) {
    console.warn('加载工序模板列表失败(可能后端未就绪)', e)
  }
}

onMounted(() => {
  // 从 query 自动填项目过滤 (项目详情 → 施工进度 tab 跳转)
  const qProjectId = Number(route.query.project_id)
  if (qProjectId) {
    searchForm.project_id = qProjectId
  }
  loadList()
  loadProjects()
  loadUsers()
  loadTemplates()
})

// ========== 操作 ==========
const handleSearch = () => { page.value = 1; loadList() }
const handleReset = () => {
  searchForm.project_id = null
  searchForm.status = ''
  searchForm.is_overdue = false
  page.value = 1
  loadList()
}

const quickFilter = (key: keyof InstanceStats) => {
  page.value = 1
  if (key === 'overdue') {
    searchForm.is_overdue = true
    searchForm.status = ''
  } else {
    searchForm.is_overdue = false
    searchForm.status = key
  }
  loadList()
}

// ========== 详情弹窗 (v1.2.12p 详情改成弹窗模式 1440px) ==========
const detailDialog = reactive({ visible: false, id: null as number | null })
const handleView = (row: Instance) => {
  detailDialog.id = row.id
  detailDialog.visible = true
}
const handleViewProject = (row: Instance) => {
  router.push(`/project/detail/${row.project_id}`)
}

// ========== 接受/驳回对话框 ==========
type ActionKind = 'accept' | 'reject'
const actionDialog = reactive({
  visible: false,
  loading: false,
  title: '',
  kind: 'accept' as ActionKind,
  reasonLabel: '',
  reasonKey: '',
  reasonOptions: [] as string[],
  commentLabel: '备注',
  commentKey: 'comment',
  commentPlaceholder: '',
  rules: {} as Record<string, unknown>,
  form: { comment: '', reason: '' } as Record<string, unknown>,
  target: null as Instance | null,
})

const openAcceptDialog = (row: Instance) => {
  actionDialog.visible = true
  actionDialog.loading = false
  actionDialog.kind = 'accept'
  actionDialog.title = `接受工序 — ${row.template_name || '#' + row.id}`
  actionDialog.reasonLabel = ''
  actionDialog.reasonKey = ''
  actionDialog.commentLabel = '验收意见'
  actionDialog.commentKey = 'comment'
  actionDialog.commentPlaceholder = '请输入验收意见'
  actionDialog.form = { comment: '', reason: '' }
  actionDialog.target = row
}

const openRejectDialog = (row: Instance) => {
  actionDialog.visible = true
  actionDialog.loading = false
  actionDialog.kind = 'reject'
  actionDialog.title = `驳回工序 — ${row.template_name || '#' + row.id}`
  actionDialog.reasonLabel = '驳回原因'
  actionDialog.reasonKey = 'reason'
  actionDialog.reasonOptions = [...REJECT_REASONS]
  actionDialog.commentLabel = '详细说明'
  actionDialog.commentKey = 'comment'
  actionDialog.commentPlaceholder = '请详细描述驳回原因'
  actionDialog.form = { comment: '', reason: '' }
  actionDialog.target = row
}

const handleAccept = (row: Instance) => openAcceptDialog(row)
const handleReject = (row: Instance) => openRejectDialog(row)

const submitAction = async () => {
  if (!actionDialog.target) return
  if (actionDialog.kind === 'reject' && !actionDialog.form.reason) {
    ElMessage.warning('请选择驳回原因')
    return
  }
  if (!actionDialog.form.comment) {
    ElMessage.warning('请输入备注')
    return
  }
  actionDialog.loading = true
  try {
    if (actionDialog.kind === 'accept') {
      await processApi.instanceAccept(actionDialog.target.id, { comment: actionDialog.form.comment })
      ElMessage.success('已接受')
    } else {
      await processApi.instanceReject(actionDialog.target.id, {
        reason: actionDialog.form.reason,
        comment: actionDialog.form.comment,
      })
      ElMessage.success('已驳回')
    }
    actionDialog.visible = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '操作失败')
  } finally {
    actionDialog.loading = false
  }
}

// ========== 进度更新 ==========
const progressDialog = reactive({
  visible: false,
  loading: false,
  form: { progress: 0, comment: '' },
  target: null as Instance | null,
})

const handleProgress = (row: Instance) => {
  progressDialog.visible = true
  progressDialog.loading = false
  progressDialog.form = { progress: row.progress || 0, comment: '' }
  progressDialog.target = row
}

const submitProgress = async () => {
  if (!progressDialog.target) return
  if (progressDialog.form.progress < 0 || progressDialog.form.progress > 100) {
    ElMessage.warning('进度必须在 0-100 之间')
    return
  }
  progressDialog.loading = true
  try {
    await processApi.instanceProgress(progressDialog.target.id, {
      progress: progressDialog.form.progress,
      comment: progressDialog.form.comment,
    })
    ElMessage.success('进度已更新')
    progressDialog.visible = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '更新失败')
  } finally {
    progressDialog.loading = false
  }
}

// ========== 新建 (v1.2.12p 字段对齐后端) ==========
const createDialog = reactive({
  visible: false,
  loading: false,
  form: {
    project_id: null as number | null,
    template_id: null as number | null,
    name: '',
    foreman_id: null as number | null,
    planned_start_date: '',
    planned_end_date: '',
    location: '',
    description: '',
  },
})

const createRules = {
  project_id:          [{ required: true, message: '请选择项目', trigger: 'change' }],
  template_id:         [{ required: true, message: '请选择工序模板', trigger: 'change' }],
  name:                [{ required: true, message: '请输入工序名称', trigger: 'blur' }],
  planned_start_date:  [{ required: true, message: '请选择计划开始日期', trigger: 'change' }],
  planned_end_date:    [{ required: true, message: '请选择计划结束日期', trigger: 'change' }],
}

const handleCreate = () => {
  if (projectOptions.value.length === 0) loadProjects()
  if (templateOptions.value.length === 0) loadTemplates()
  if (userOptions.value.length === 0) loadUsers()
  createDialog.form = {
    project_id: null,
    template_id: null,
    name: '',
    foreman_id: null,
    planned_start_date: '',
    planned_end_date: '',
    location: '',
    description: '',
  }
  createDialog.visible = true
  createDialog.loading = false
}

const submitCreate = async () => {
  if (createDialog.form.planned_start_date && createDialog.form.planned_end_date
      && createDialog.form.planned_start_date > createDialog.form.planned_end_date) {
    ElMessage.warning('计划结束日期不能早于开始日期')
    return
  }
  createDialog.loading = true
  try {
    const payload: Record<string, unknown> = {
      project_id:          createDialog.form.project_id,
      template_id:         createDialog.form.template_id,
      name:                createDialog.form.name,
      foreman_id:          createDialog.form.foreman_id || null,
      planned_start_date:  createDialog.form.planned_start_date,
      planned_end_date:    createDialog.form.planned_end_date,
      location:            createDialog.form.location || null,
      description:         createDialog.form.description || null,
    }
    await processApi.instanceCreate(payload)
    ElMessage.success('创建成功')
    createDialog.visible = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '创建失败')
  } finally {
    createDialog.loading = false
  }
}
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
}
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .title-area { display: flex; align-items: center; gap: 10px; }
  .page-title {
    font-size: 18px; font-weight: 600; color: #0C447C;
    border-left: 4px solid #0C447C; padding-left: 10px;
  }
  .header-actions { display: flex; gap: 8px; }
}
.content-card {
  background: #fff; padding: 20px; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
