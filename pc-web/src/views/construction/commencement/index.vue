<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">开工单</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="handleReset">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建开工单</el-button>
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
        <el-form-item label="项目">
          <el-select
            v-model="searchForm.project_id"
            placeholder="全部项目"
            clearable
            filterable
            style="width: 220px"
          >
            <el-option
              v-for="p in projectOptions"
              :key="p.id"
              :label="`${p.code ? p.code + ' - ' : ''}${p.name || ''}`"
              :value="p.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 140px">
            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="编号 / 备注" clearable style="width: 220px" />
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
        <el-table-column prop="code" label="开工编号" width="160" fixed show-overflow-tooltip>
          <template #default="{ row }">
            <el-link type="primary" :underline="false" @click="goDetail(row as CommencementOrder)">{{ row.code || '-' }}</el-link>
          </template>
        </el-table-column>
        <el-table-column label="项目" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.project?.name || row.project?.code || row.project_id || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="团队" width="140" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.team?.name || row.team_id || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="计划" width="220" align="center">
          <template #default="{ row }">
            <div class="date-cell">
              <span>{{ row.planned_start || '-' }}</span>
              <span class="arrow">→</span>
              <span>{{ row.planned_end || '-' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="worker_count" label="人数" width="70" align="center" />
        <el-table-column label="工时预估" width="100" align="right">
          <template #default="{ row }">{{ row.estimated_hours || 0 }} h</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="plain" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160" align="center" show-overflow-tooltip />
        <el-table-column label="操作" width="280" fixed="right" align="center">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="goDetail(row as CommencementOrder)">详情</el-button>
            <el-button
              v-if="row.status === 'draft'"
              link
              type="warning"
              :icon="Edit"
              @click="handleEdit(row as CommencementOrder)"
            >编辑</el-button>
            <el-button
              v-if="row.status === 'draft'"
              link
              type="success"
              :icon="Check"
              @click="handleSubmitForApproval(row as CommencementOrder)"
            >提交审批</el-button>
            <el-button
              v-if="row.status === 'approved'"
              link
              type="primary"
              :icon="VideoPlay"
              @click="handleStart(row as CommencementOrder)"
            >开工</el-button>
            <el-button
              v-if="row.status === 'in_progress'"
              link
              type="success"
              :icon="CircleCheck"
              @click="handleComplete(row as CommencementOrder)"
            >完工</el-button>
            <el-button
              v-if="row.status === 'draft'"
              link
              type="danger"
              :icon="Delete"
              @click="handleDelete(row as CommencementOrder)"
            >删除</el-button>
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

    <OrderFormDialog
      v-model:visible="showFormDialog"
      :project-options="projectOptions"
      :team-options="teamOptions"
      :editing="editingOrder"
      @save="handleSave"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, View, Edit, Check, Delete, VideoPlay, CircleCheck } from '@element-plus/icons-vue'
import { commencementApi, teamApi } from '@/api/construction'
import { getProjectList } from '@/api/modules'
import { unwrapList } from '@/utils/response'
import OrderFormDialog from './components/OrderFormDialog.vue'

const route = useRoute()
const router = useRouter()

// 状态：draft/approved/in_progress/completed/cancelled
const statusOptions = [
  { value: 'draft',       label: '草稿' },
  { value: 'approved',    label: '已审批' },
  { value: 'in_progress', label: '施工中' },
  { value: 'completed',   label: '已完工' },
  { value: 'cancelled',   label: '已取消' },
]

type ElTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

interface CommencementOrder {
  id: number
  code?: string
  status?: string
  project_id?: number | null
  project?: { id: number; name?: string; code?: string } | null
  team_id?: number | null
  team?: { id: number; name?: string } | null
  planned_start?: string | null
  planned_end?: string | null
  worker_count?: number
  estimated_hours?: number
  remark?: string | null
  created_at?: string
  [key: string]: unknown
}

interface ProjectOption { id: number; code?: string; name?: string }
interface TeamOption { id: number; name?: string; [key: string]: unknown }

const statusLabel = (s: string) => statusOptions.find(x => x.value === s)?.label || s || '-'
const statusTagType = (s: string): ElTagType => {
  const map: Record<string, ElTagType> = {
    draft: 'info', approved: 'success', in_progress: 'warning', completed: 'success', cancelled: 'danger',
  }
  return map[s] || 'info'
}

const loading = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<CommencementOrder[]>([])
const projectOptions = ref<ProjectOption[]>([])
const teamOptions = ref<TeamOption[]>([])

const searchForm = reactive<{ project_id: number | null; status: string; keyword: string }>({
  project_id: null, status: '', keyword: '',
})

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.project_id) arr = arr.filter(r => Number(r.project_id) === Number(searchForm.project_id))
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter(r =>
      (r.code || '').toLowerCase().includes(kw) ||
      (r.remark || '').toLowerCase().includes(kw) ||
      (r.project?.name || '').toLowerCase().includes(kw) ||
      (r.team?.name || '').toLowerCase().includes(kw)
    )
  }
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const kpis = computed(() => {
  const total = list.value.length
  const inProgress = list.value.filter(r => r.status === 'in_progress').length
  const completed = list.value.filter(r => r.status === 'completed').length
  return [
    { label: '开工单总数', value: total, color: '#0C447C' },
    { label: '施工中',     value: inProgress, color: '#E6A23C' },
    { label: '已完工',     value: completed, color: '#67c23a' },
  ]
})

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 500, page: 1 }
    if (searchForm.project_id) params.project_id = searchForm.project_id
    if (searchForm.status)     params.status = searchForm.status
    if (searchForm.keyword)    params.keyword = searchForm.keyword
    const res: unknown = await commencementApi.list(params)
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadProjects = async () => {
  try {
    const res: unknown = await getProjectList({ per_page: 500 })
    projectOptions.value = unwrapList(res).map((p: ProjectOption) => ({ id: p.id, code: p.code, name: p.name }))
  } catch {
    projectOptions.value = []
  }
}

const loadTeams = async () => {
  try {
    const res: unknown = await teamApi.list({ per_page: 500, status: 'active' })
    teamOptions.value = unwrapList(res)
  } catch {
    teamOptions.value = []
  }
}

const handleSearch = () => { page.value = 1 }
const handleReset = () => {
  searchForm.project_id = null
  searchForm.status = ''
  searchForm.keyword = ''
  page.value = 1
  loadList()
}

const goDetail = (row: CommencementOrder) => router.push(`/construction/commencement/${row.id}`)

const showFormDialog = ref(false)
const editingOrder = ref<CommencementOrder | null>(null)

const handleAdd = () => {
  editingOrder.value = null
  showFormDialog.value = true
}

const handleEdit = (row: CommencementOrder) => {
  if (row.status !== 'draft') {
    ElMessage.warning('仅「草稿」状态可编辑')
    return
  }
  editingOrder.value = row
  showFormDialog.value = true
}

const handleSave = async (payload: Record<string, unknown>) => {
  try {
    if (editingOrder.value?.id) {
      await commencementApi.update(editingOrder.value.id, payload)
      ElMessage.success('已更新')
    } else {
      await commencementApi.create(payload)
      ElMessage.success('已创建')
    }
    showFormDialog.value = false
    editingOrder.value = null
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

// V1.2.7h: 开工单审批统一进审批中心, 此处只能"提交审批" (draft -> pending_approval)
const handleSubmitForApproval = async (row: CommencementOrder) => {
  if (row.status !== 'draft') {
    ElMessage.warning('仅「草稿」状态可提交审批')
    return
  }
  try {
    await ElMessageBox.confirm(
      `提交「${row.code}」进入审批流程？提交后将由审批中心处理。`,
      '提交审批',
      { type: 'success', confirmButtonText: '确认提交', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    await commencementApi.submit(row.id)
    ElMessage.success('已提交审批, 请前往审批中心处理')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

const handleStart = async (row: CommencementOrder) => {
  try {
    await ElMessageBox.confirm(
      `确认开工「${row.code}」？开工后状态变为「施工中」。`,
      '开工确认',
      { type: 'warning', confirmButtonText: '确认开工', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    await commencementApi.start(row.id)
    ElMessage.success('已开始施工')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

const handleComplete = async (row: CommencementOrder) => {
  try {
    await ElMessageBox.confirm(
      `确认完工「${row.code}」？完工后不能再修改。`,
      '完工确认',
      { type: 'success', confirmButtonText: '确认完工', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    await commencementApi.complete(row.id)
    ElMessage.success('已完工')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

const handleDelete = async (row: CommencementOrder) => {
  if (row.status !== 'draft') {
    ElMessage.warning('仅「草稿」状态可删除')
    return
  }
  try {
    await ElMessageBox.confirm(
      `确认删除开工单「${row.code}」？该操作不可恢复。`,
      '删除确认',
      { type: 'warning', confirmButtonText: '确认删除', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    // 复用 commencementApi,直接调删除（未在 API 中显式声明,后端若有 DELETE 端点即可用）
    await commencementApi.update(row.id, { _delete: true } as Record<string, unknown>)
    ElMessage.warning('开工单删除需要后端 DELETE 端点')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

// 路由参数 ?project_id=
const applyRouteFilter = () => {
  const q = (route.query.project_id || route.query.projectId) as string | undefined
  if (q && !Number.isNaN(Number(q))) searchForm.project_id = Number(q)
}
watch(() => route.query.project_id, () => { applyRouteFilter(); page.value = 1; loadList() })

onMounted(() => {
  applyRouteFilter()
  loadProjects()
  loadTeams()
  loadList()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title {
    font-size: 18px; font-weight: 600; color: #0C447C;
    border-left: 4px solid #0C447C; padding-left: 10px;
  }
  .header-actions { display: flex; gap: 8px; }
}
.kpi-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px; }
.kpi-card {
  .kpi-label { color: #909399; font-size: 13px; }
  .kpi-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
}
.filter-bar {
  background: #fff; padding: 16px 20px; border-radius: 8px;
  margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.content-card {
  background: #fff; padding: 20px; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
.date-cell { display: flex; align-items: center; justify-content: center; gap: 4px; }
.arrow { color: #909399; }
</style>
