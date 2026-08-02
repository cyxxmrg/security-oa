<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">工序验收</span>
      <div class="header-actions">
        <el-button type="primary" :icon="Plus" @click="$router.push('/process/instances')">新增验收</el-button>
      </div>
    </div>
    <InspectionStatCards :stats="stats" :stats-loading="statsLoading" />

    <InspectionFilterBar
      ref="filterBarRef"
      :form="searchForm"
      :project-options="projectOptions"
      :process-instance-options="processInstanceOptions"
      @search="handleSearch"
      @reset="handleReset"
    />

    <InspectionTable
      :list="list"
      :loading="loading"
      :total="pagination.total"
      :page="pagination.page"
      :per-page="pagination.per_page"
      @view="goInstance"
      @page-change="(p) => loadList(p)"
      @size-change="(s) => { pagination.per_page = s; loadList(1) }"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Plus } from '@element-plus/icons-vue'
import { processApi } from '@/api/modules'
import { getProjectList } from '@/api/modules'
import { unwrapList, unwrapPaginate } from '@/utils/response'

import InspectionStatCards from './components/inspection-list/InspectionStatCards.vue'
import InspectionFilterBar from './components/inspection-list/InspectionFilterBar.vue'
import InspectionTable from './components/inspection-list/InspectionTable.vue'

import type {
  Inspection, InspectionStats, InspectionFilters,
  ProjectOption, ProcessInstanceOption,
} from './components/inspection-list/types'
import { emptyStats } from './components/inspection-list/types'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const statsLoading = ref(false)

const stats = reactive<InspectionStats>(emptyStats())
const list = ref<Inspection[]>([])
const projectOptions = ref<ProjectOption[]>([])
const processInstanceOptions = ref<ProcessInstanceOption[]>([])

const searchForm = reactive<InspectionFilters>({
  project_id: null,
  process_instance_id: null,
  result: '',
})

const pagination = reactive({ page: 1, per_page: 20, total: 0 })

const filterBarRef = ref<InstanceType<typeof InspectionFilterBar> | null>(null)

// 本地按当前 list 统计 (没有专门的 stats 接口)
const computeLocalStats = (rows: Inspection[]) => {
  const s = emptyStats()
  s.total = pagination.total || rows.length
  s.pass = rows.filter(r => r.result === 'pass').length
  s.fail = rows.filter(r => r.result === 'fail').length
  return s
}

async function loadList(page = 1) {
  loading.value = true
  pagination.page = page
  try {
    const params: Record<string, unknown> = {
      page: pagination.page,
      per_page: pagination.per_page,
    }
    if (searchForm.project_id) params.project_id = searchForm.project_id
    if (searchForm.process_instance_id) params.process_instance_id = searchForm.process_instance_id
    if (searchForm.result) params.result = searchForm.result
    const range = filterBarRef.value?.dateRange
    if (range && range.length === 2) {
      params.start_date = range[0]
      params.end_date = range[1]
    }
    const r = await processApi.inspectionList(params)
    const pag = unwrapPaginate(r as Record<string, unknown>)
    list.value = pag.list as Inspection[]
    pagination.total = pag.total
    Object.assign(stats, computeLocalStats(list.value))
  } catch { /* toast */ }
  loading.value = false
  statsLoading.value = false
}

async function loadProjectOptions() {
  try {
    const r = await getProjectList({ per_page: 500 })
    const arr = unwrapList(r as Record<string, unknown>)
    projectOptions.value = arr.map((p: Record<string, unknown>) => ({ id: Number(p.id), name: String(p.name || p.code || '') }))
  } catch { /* 静默 */ }
}

async function loadProcessInstanceOptions() {
  try {
    const r = await processApi.instanceList({ per_page: 500 })
    const arr = unwrapList(r as Record<string, unknown>)
    processInstanceOptions.value = arr.map((i: Record<string, unknown>) => {
      const tpl = (i.template as Record<string, unknown> | undefined)?.name as string | undefined
      return {
        id: Number(i.id),
        label: `[#${i.id}] ${i.name || tpl || i.code || ''}`,
      }
    })
  } catch { /* 静默 */ }
}

function handleSearch() {
  pagination.page = 1
  loadList(1)
}

function handleReset() {
  searchForm.project_id = null
  searchForm.process_instance_id = null
  searchForm.result = ''
  if (filterBarRef.value?.dateRange) filterBarRef.value.dateRange = null
  pagination.page = 1
  loadList(1)
}

const goInstance = (row: Inspection) => {
  if (!row.process_instance_id) return
  // 实例详情页已改成 1440px 弹窗, 跳转到列表并打开 dialog
  router.push({ path: '/process/instances', query: { detail: String(row.process_instance_id) } })
}

onMounted(() => {
  // 从 query 自动填项目过滤 (项目详情 → 施工进度 tab 跳转)
  const qProjectId = Number(route.query.project_id)
  if (qProjectId) {
    searchForm.project_id = qProjectId
  }
  statsLoading.value = true
  loadProjectOptions()
  loadProcessInstanceOptions()
  loadList(1)
})
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
}
</style>