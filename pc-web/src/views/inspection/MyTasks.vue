<template>
  <div class="page-container">
    <div class="page-header">
      <h2>我的巡检</h2>
      <el-button :icon="Refresh" @click="loadList(1)">刷新</el-button>
    </div>

    <div class="filter-bar">
      <el-radio-group v-model="filter.scope" @change="loadList(1)">
        <el-radio-button label="all">全部</el-radio-button>
        <el-radio-button label="today">今天</el-radio-button>
        <el-radio-button label="pending">未完成</el-radio-button>
        <el-radio-button label="completed">已完成</el-radio-button>
      </el-radio-group>
    </div>

    <el-row :gutter="16">
      <el-col v-for="t in list" :key="t.id" :xs="24" :sm="12" :md="8">
        <el-card shadow="hover" class="task-card">
          <div class="task-header">
            <span class="task-no">{{ t.task_no }}</span>
            <el-tag :type="taskStatusColor(t.status)" size="small">{{ taskStatusLabel(t.status) }}</el-tag>
          </div>
          <div class="task-title">{{ t.plan?.name || '巡检任务' }}</div>
          <div class="task-meta">
            <div><el-icon><Calendar /></el-icon> {{ t.scheduled_date }} {{ t.scheduled_hour }}:00</div>
            <div><el-icon><OfficeBuilding /></el-icon> {{ t.customer?.name || '-' }}</div>
          </div>
          <div class="task-actions">
            <el-button v-if="['pending','overdue','in_progress'].includes(t.status)" type="primary" size="small" :icon="Position" @click="$router.push(`/inspection/tasks/checkin/${t.id}`)">前往打卡</el-button>
            <el-button v-else size="small" @click="$router.push(`/inspection/tasks/checkin/${t.id}`)">查看详情</el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>
    <el-empty v-if="!loading && list.length === 0" description="暂无巡检任务" style="margin-top: 60px" />
    <div class="pagination-wrap">
      <el-pagination background layout="total, prev, pager, next" :total="pagination.total" :current-page="pagination.page" :page-size="pagination.per_page" @current-change="(p) => loadList(p)" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Refresh, Calendar, OfficeBuilding, Position } from '@element-plus/icons-vue'
import { inspection, TASK_STATUS_LABEL, type InspectionTask } from '@/api/inspection'

const loading = ref(false)
const list = ref<InspectionTask[]>([])
const pagination = reactive({ total: 0, page: 1, per_page: 12 })
const filter = reactive<{ scope: string }>({ scope: 'all' })

const taskStatusLabel = (s: string) => TASK_STATUS_LABEL[s as keyof typeof TASK_STATUS_LABEL] || s
const taskStatusColor = (s: string) => ({ pending: 'info', in_progress: 'warning', completed: 'success', overdue: 'danger', skipped: '', cancelled: '' }[s] || '')

const loadList = async (page = 1) => {
  pagination.page = page
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: pagination.per_page, page }
    if (filter.scope === 'today') params.today = true
    if (filter.scope === 'pending') params.status = 'pending'
    if (filter.scope === 'completed') params.status = 'completed'
    const r = await inspection.myTasks(params)
    const d = r?.data ?? {}
    list.value = d.data || []
    pagination.total = d.total || 0
  } finally {
    loading.value = false
  }
}

onMounted(loadList)
</script>

<style scoped>
.page-container { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.filter-bar { margin-bottom: 16px; }
.task-card { margin-bottom: 12px; }
.task-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.task-no { font-family: monospace; color: #999; font-size: 12px; }
.task-title { font-weight: 600; margin-bottom: 8px; }
.task-meta { color: #666; font-size: 13px; line-height: 1.8; }
.task-meta > div { display: flex; align-items: center; gap: 4px; }
.task-actions { margin-top: 12px; text-align: right; }
.pagination-wrap { margin-top: 16px; text-align: center; }
</style>
