<template>
  <div class="page-container">
    <GanttOverviewCard
      :project-name="projectName"
      :project-start="projectStart"
      :project-end="projectEnd"
      :total-days="totalDays"
      :finished-count="finishedCount"
      :in-progress-count="inProgressCount"
      :not-started-count="notStartedCount"
      :overall-progress="overallProgress"
      :tasks-length="tasks.length"
      v-model:view-mode="viewMode"
      v-model:zoom="zoom"
      @back="$router.back()"
      @print="handlePrint"
      @export="handleExportGantt"
      @refresh="refreshData"
    />

    <div class="content-card">
      <GanttGrid
        :tasks="tasks"
        :dates="dates"
        :zoom="zoom"
        @task-click="handleTaskClick"
      />
    </div>

    <GanttTaskTable
      :tasks="tasks"
      @update="openUpdateDialog"
    />

    <GanttTaskUpdateDialog
      v-model:visible="updateVisible"
      :target="updateTarget"
      @submit="submitUpdate"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * 项目甘特图 — v0.3.14 C3 拆分版
 *
 * 子组件:
 *  - GanttOverviewCard.vue   头部 + 概览卡 + 图例 + zoom/viewMode
 *  - GanttGrid.vue          甘特主体网格
 *  - GanttTaskTable.vue     下方任务列表
 *  - GanttTaskUpdateDialog.vue  更新进度 dialog
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { get } from '@/utils/request'
import { unwrapItem } from '@/utils/response'
import { printTable, exportExcelLike } from '@/utils/exporter'
import GanttOverviewCard from './components/GanttOverviewCard.vue'
import GanttGrid from './components/GanttGrid.vue'
import GanttTaskTable from './components/GanttTaskTable.vue'
import GanttTaskUpdateDialog from './components/GanttTaskUpdateDialog.vue'
import type { GanttTask, TaskStatus } from './ganttTypes'

const route = useRoute()
const router = useRouter()
// V1.2.9n: Gantt.vue 同时被 /project/gantt/:id 路由 和 Detail.vue 嵌入引用。嵌入模式下 route.params.id 是 undefined，
// Number(undefined) === NaN 会拼出 /projects/NaN/... 让后端炸 SQLSTATE[22P02]。优先级: props.id > route.params.id
const props = defineProps<{ id?: number | string; mode?: 'standalone' | 'embedded' }>()
const projectId = computed(() => {
  const raw = props.id ?? route.params.id
  const n = Number(raw)
  return Number.isFinite(n) && n > 0 ? n : null
})

const viewMode = ref<'day' | 'week' | 'month'>('day')
const zoom = ref(1)

const projectName = ref('项目甘特图')
const projectStart = ref('2025-09-01')
const projectEnd = ref('2026-09-30')
const projectStatus = ref('')
const projectProgress = ref(0)
const tasks = ref<GanttTask[]>([])
const loading = ref(false)
const projectNotFound = ref(false)

const loadProject = async () => {
  if (!projectId.value) {
    // V1.2.9n: 嵌入模式下 props.id 可能为 undefined, 不发请求避免 NaN 炸后端
    projectNotFound.value = true
    loading.value = false
    return
  }
  loading.value = true
  try {
    const res = await get(`/projects/${projectId.value}`)
    // V0.6.3: res = {code, data: <entity>}
    const p = unwrapItem<Record<string, unknown>>(res)
    projectName.value = p.name || '项目甘特图'
    projectStatus.value = p.status || ''
    projectProgress.value = p.progress || 0
    if (p.start_date) projectStart.value = p.start_date.slice(0, 10)
    if (p.end_date) projectEnd.value = p.end_date.slice(0, 10)
    projectNotFound.value = false
  } catch (e: unknown) {
    // V1.2.9k: 404 → 项目不存在 (DB wipe 后或 URL 拼错), 不再 ElMessage 红条
    const status = e?.response?.status ?? e?.status
    if (status === 404 || /不存在|not found/i.test(e?.message || '')) {
      projectNotFound.value = true
      ElMessage.warning('项目不存在或已被删除, 即将返回列表')
      setTimeout(() => router.replace('/project/list'), 1500)
    } else {
      ElMessage.error(e?.message || '加载项目失败')
    }
  } finally {
    loading.value = false
  }
}

const loadLogs = async () => {
  if (!projectId.value) {
    // V1.2.9n: 嵌入模式无 id 时直接跳过, 避免发出 /projects/NaN/...
    return
  }
  try {
    const res = await get(`/projects/${projectId.value}/construction-logs`, { per_page: 50 })
    // V0.6.3: res = {code, data: paginator}
    const d = res?.data ?? {}
    const items = Array.isArray(d?.data) ? d.data : []
    tasks.value = items.map((log: Record<string, unknown>, i: number) => {
      const start = log.work_date ? log.work_date.slice(0, 10) : projectStart.value
      return {
        name: log.content ? log.content.slice(0, 30) + (log.content.length > 30 ? '...' : '') : `施工日志 ${i + 1}`,
        category: '施工',
        startDate: start,
        endDate: start,
        duration: 1,
        progress: 100,
        owner: log.user?.name || '-',
        status: 'done' as TaskStatus,
        startOffset: 0,
      }
    })
  } catch (e) {
    console.error('加载施工日志失败', e)
  }
}

onMounted(() => {
  loadProject()
  loadLogs()
})

const totalDays = computed(() => {
  const start = new Date(projectStart.value)
  const end = new Date(projectEnd.value)
  return Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24))
})

const finishedCount = computed(() => tasks.value.filter((t) => t.status === 'done').length)
const inProgressCount = computed(() => tasks.value.filter((t) => t.status === 'in-progress').length)
const notStartedCount = computed(() => tasks.value.filter((t) => t.status === 'todo').length)
const overallProgress = computed(() => {
  if (!tasks.value.length) return 0
  const sum = tasks.value.reduce((s, t) => s + t.progress, 0)
  return Math.round(sum / tasks.value.length)
})

const dates = computed(() => {
  const arr: { day: number; month: number; date: Date; full: string }[] = []
  const start = new Date(projectStart.value)
  for (let i = 0; i < totalDays.value; i++) {
    const d = new Date(start.getTime() + i * 24 * 60 * 60 * 1000)
    arr.push({
      day: d.getDate(),
      month: d.getMonth() + 1,
      date: d,
      full: d.toISOString().split('T')[0],
    })
  }
  return arr
})

const handleTaskClick = (task: GanttTask) => {
  ElMessage.info(`查看任务：${task.name}`)
}

const updateVisible = ref(false)
const updateTarget = ref<GanttTask | null>(null)
const openUpdateDialog = (t: GanttTask) => {
  updateTarget.value = t
  updateVisible.value = true
}
const submitUpdate = (payload: { name: string; progress: number; status: TaskStatus; note: string }) => {
  if (!updateTarget.value) return
  // 乐观更新
  const idx = tasks.value.findIndex((t) => t === updateTarget.value)
  if (idx >= 0) {
    tasks.value[idx] = { ...tasks.value[idx], progress: payload.progress, status: payload.status }
  }
  updateVisible.value = false
  ElMessage.success(`已更新「${payload.name}」进度到 ${payload.progress}%`)
  // TODO: 实际调后端 API
}

const handleExportGantt = () => {
  const tasks = (ganttData.value && ganttData.value.tasks) || (Array.isArray(ganttData.value) ? ganttData.value : [])
  if (!tasks.length) {
    ElMessage.warning('暂无甘特图数据可导出')
    return
  }
  const headers = ['任务', '开始日期', '结束日期', '进度', '负责人', '状态']
  const rows = tasks.map((t: Record<string, unknown>) => [
    t.name || t.text || '-',
    t.start || t.start_date || '-',
    t.end || t.end_date || '-',
    (t.progress || 0) + '%',
    t.assignee?.name || t.assignee || '-',
    t.status || '-',
  ])
  exportExcelLike(headers, rows, '甘特图任务', { title: '项目甘特图任务清单' })
}
const handlePrint = () => {
  const tasks = (ganttData.value && ganttData.value.tasks) || (Array.isArray(ganttData.value) ? ganttData.value : [])
  if (!tasks.length) {
    ElMessage.warning('暂无甘特图数据可打印')
    return
  }
  const headers = ['任务', '开始日期', '结束日期', '进度', '负责人', '状态']
  const rows = tasks.map((t: Record<string, unknown>) => [
    t.name || t.text || '-',
    t.start || t.start_date || '-',
    t.end || t.end_date || '-',
    (t.progress || 0) + '%',
    t.assignee?.name || t.assignee || '-',
    t.status || '-',
  ])
  printTable('甘特图任务', headers, rows, { orientation: 'landscape' })
}
const refreshData = () => ElMessage.success('数据已刷新')
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px 20px;
  background: #f5f7fa;
  min-height: 100%;
}
.content-card {
  position: relative;
  margin-bottom: 12px;
}
</style>
