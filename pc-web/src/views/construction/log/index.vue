<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">施工日志</span>
      <div class="header-actions">
        <ScopeToggle @change="loadList" />
        <el-button :icon="Refresh" plain @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Calendar" @click="goDailyReport">每日上报</el-button>
      </div>
    </div>

    <!-- 筛选区 -->
    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="开工单">
          <el-select
            v-model="searchForm.commencement_id"
            placeholder="全部"
            clearable
            filterable
            style="width: 220px"
          >
            <el-option
              v-for="o in commencementOptions"
              :key="o.id"
              :label="`${o.code} - ${o.project?.name || ''}`"
              :value="o.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始"
            end-placeholder="结束"
            value-format="YYYY-MM-DD"
            style="width: 260px"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="草稿" value="draft" />
            <el-option label="已提交" value="submitted" />
            <el-option label="已审核" value="approved" />
          </el-select>
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
        <el-table-column label="日期" width="120" align="center" fixed>
          <template #default="{ row }">
            {{ (row.work_date || '').slice(0, 10) }}
          </template>
        </el-table-column>
        <el-table-column prop="weather" label="天气" width="70" align="center" />
        <el-table-column label="项目" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.project?.name || (row.commencement?.project?.name) || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="施工团队" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.team?.name || row.team_name || row.team?.team_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="工序" width="120" align="center">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ row.process_name || row.process?.name || row.process_id || '-' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="worker_count" label="工人" width="70" align="center" />
        <el-table-column label="工时" width="80" align="right">
          <template #default="{ row }">{{ row.work_hours || 0 }} 个</template>
        </el-table-column>
        <el-table-column label="进度" width="160" align="center">
          <template #default="{ row }">
            <el-progress :percentage="row.progress || 0" :stroke-width="10" style="width: 120px" />
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="plain" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="issues" label="问题" min-width="200" show-overflow-tooltip />
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="handleView(row as ConstructionLog)">查看</el-button>
            <el-button
              v-if="row.status === 'draft'"
              link
              type="success"
              :icon="Upload"
              @click="handleSubmit(row as ConstructionLog)"
            >提交</el-button>
            <el-button
              v-if="row.status !== 'approved'"
              link
              type="warning"
              :icon="Edit"
              @click="handleEdit(row as ConstructionLog)"
            >编辑</el-button>
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

    <LogFormDialog
      v-model:visible="showFormDialog"
      :project-options="projectOptions"
      :process-options="processOptions"
      :team-options="teamOptions"
      :editing="editingLog"
      :readonly="isViewOnly"
      @save="handleSave"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Calendar, Refresh, Search, View, Edit, Upload } from '@element-plus/icons-vue'
import { logApi, commencementApi, workProcessApi } from '@/api/construction'
import { get } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import ScopeToggle from '@/components/ScopeToggle.vue'
import LogFormDialog from './components/LogFormDialog.vue'

type ElTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

interface ConstructionLog {
  id?: number
  date?: string
  work_date?: string
  weather?: string
  commencement_id?: number | null
  commencement?: { code?: string; project?: { name?: string } } | null
  project?: { name?: string } | null
  process_name?: string
  process_id?: number | null
  process?: { name?: string } | null
  worker_count?: number | string
  work_hours?: number | string
  progress?: number | string
  status?: string
  issues?: string
  [key: string]: unknown
}
interface CommencementOption {
  id: number
  code?: string
  project?: { name?: string } | null
  [key: string]: unknown
}
interface ProcessOption {
  id: number
  name?: string
  [key: string]: unknown
}

const router = useRouter()
const route = useRoute()

const statusLabel = (s: string) => ({
  draft: '草稿', submitted: '已提交', approved: '已审核',
} as Record<string, string>)[s] || s || '-'
const statusTagTypeMap: Record<string, ElTagType> = { draft: 'info', submitted: 'warning', approved: 'success' }
const statusTagType = (s: string): ElTagType => statusTagTypeMap[s] || 'info'

const loading = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<ConstructionLog[]>([])
const projectOptions = ref<Record<string, unknown>[]>([])
const teamOptions = ref<Record<string, unknown>[]>([])
const commencementOptions = ref<CommencementOption[]>([])
const processOptions = ref<ProcessOption[]>([])
const dateRange = ref<[string, string] | null>(null)

const searchForm = reactive<{ commencement_id: number | null; status: string }>({
  commencement_id: null, status: '',
})

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.commencement_id) arr = arr.filter(r => Number(r.commencement_id) === Number(searchForm.commencement_id))
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 500, page: 1 }
    if (searchForm.commencement_id) params.commencement_id = searchForm.commencement_id
    if (searchForm.status) params.status = searchForm.status
    if (dateRange.value?.[0]) params.date_from = dateRange.value[0]
    if (dateRange.value?.[1]) params.date_to = dateRange.value[1]
    const res = await logApi.list(params)
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadCommencementOptions = async () => {
  try {
    const res = await commencementApi.list({ per_page: 500 })
    commencementOptions.value = unwrapList(res)
  } catch {
    commencementOptions.value = []
  }
}

const loadTeamOptions = async () => {
  try {
    const res = await get('/construction/teams', { per_page: 500, status: 'active' })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    teamOptions.value = d as Record<string, unknown>[]
  } catch { teamOptions.value = [] }
}

const loadProjectOptions = async () => {
  try {
    const res = await get('/projects', { per_page: 500 })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    projectOptions.value = d as Record<string, unknown>[]
  } catch {
    projectOptions.value = []
  }
}

const loadProcessOptions = async () => {
  try {
    const res = await workProcessApi.list({ per_page: 500 })
    processOptions.value = unwrapList(res)
  } catch {
    processOptions.value = []
  }
}

const handleSearch = () => { page.value = 1; loadList() }
const handleReset = () => {
  searchForm.commencement_id = null
  searchForm.status = ''
  dateRange.value = null
  page.value = 1
  loadList()
}

const goDailyReport = () => router.push('/construction/log/daily')

const showFormDialog = ref(false)
const editingLog = ref<ConstructionLog | null>(null)
const isViewOnly = ref(false)

const handleView = (row: ConstructionLog) => {
  editingLog.value = row
  isViewOnly.value = true
  showFormDialog.value = true
}
const handleEdit = (row: ConstructionLog) => {
  editingLog.value = row
  isViewOnly.value = false
  showFormDialog.value = true
}

const handleSubmit = async (row: ConstructionLog) => {
  if (row.id == null) return
  try {
    await logApi.submit(row.id)
    ElMessage.success('已提交')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

const handleSave = async (payload: ConstructionLog, action: 'draft' | 'submit') => {
  try {
    let id: number
    const cur = editingLog.value
    if (cur?.id) {
      id = ((await logApi.update(cur.id, payload)) as unknown as { id?: number })?.id ?? cur.id
      ElMessage.success('已更新')
    } else {
      const res = (await logApi.create(payload)) as unknown as { id?: number; data?: { id?: number } }
      id = res?.id ?? res?.data?.id ?? 0
      ElMessage.success('已创建')
    }
    if (action === 'submit' && id) {
      await logApi.submit(id)
      ElMessage.success('已提交')
    }
    showFormDialog.value = false
    editingLog.value = null
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

// 路由 ?commencement_id= 自动筛选
watch(() => route.query.commencement_id, () => {
  const q = route.query.commencement_id as string | undefined
  if (q && !Number.isNaN(Number(q))) {
    searchForm.commencement_id = Number(q)
    page.value = 1
    loadList()
  }
})

onMounted(() => {
  loadCommencementOptions()
  loadTeamOptions()
  loadProjectOptions()
  loadProcessOptions()
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
.filter-bar {
  background: #fff; padding: 16px 20px; border-radius: 8px;
  margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.content-card {
  background: #fff; padding: 20px; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
