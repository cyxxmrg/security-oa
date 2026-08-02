<template>
  <div class="page-container">
    <div class="page-header">
      <h2>执行任务</h2>
      <el-button :icon="Refresh" @click="loadList(1)">刷新</el-button>
    </div>

    <div class="filter-bar">
      <el-input v-model="filter.keyword" placeholder="任务号/客户" clearable style="width: 200px" @keyup.enter="loadList(1)" />
      <el-select v-model="filter.status" placeholder="状态" clearable style="width: 120px" @change="loadList(1)">
        <el-option v-for="(label, value) in TASK_STATUS_LABEL" :key="value" :label="label" :value="value" />
      </el-select>
      <el-date-picker v-model="filter.dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width: 240px" @change="loadList(1)" />
      <el-button type="primary" :icon="Search" @click="loadList(1)">搜索</el-button>
      <el-button @click="resetFilter">重置</el-button>
    </div>

    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column prop="task_no" label="任务号" width="140" />
        <el-table-column label="计划" min-width="160" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.plan?.name || '-' }}</span></template>
        </el-table-column>
        <el-table-column label="客户" min-width="120" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.customer?.name || '-' }}</span></template>
        </el-table-column>
        <el-table-column prop="scheduled_date" label="计划日期" width="100" />
        <el-table-column label="执行人" width="100" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.assignee?.name || '未指派' }}</span></template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="taskStatusColor(row.status)" size="small">{{ taskStatusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="completed_at" label="完成时间" width="160" show-overflow-tooltip />
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button v-if="['pending','overdue','in_progress'].includes(row.status)" link type="success" size="small" @click="$router.push(`/inspection/tasks/checkin/${row.id}`)">打卡</el-button>
            <el-button v-if="row.status === 'pending' || row.status === 'overdue'" link type="warning" size="small" @click="handleSkip(row)">跳过</el-button>
            <el-button link type="primary" size="small" @click="$router.push(`/inspection/tasks/checkin/${row.id}`)">详情</el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty description="暂无任务" />
        </template>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination background layout="total, prev, pager, next" :total="pagination.total" :current-page="pagination.page" :page-size="pagination.per_page" @current-change="(p) => loadList(p)" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import { inspection, TASK_STATUS_LABEL, type InspectionTask } from '@/api/inspection'

const loading = ref(false)
const list = ref<InspectionTask[]>([])
const pagination = reactive({ total: 0, page: 1, per_page: 20 })
const filter = reactive<{ keyword: string; status: string; dateRange: [string, string] | null }>({ keyword: '', status: '', dateRange: null })

const taskStatusLabel = (s: string) => TASK_STATUS_LABEL[s as keyof typeof TASK_STATUS_LABEL] || s
const taskStatusColor = (s: string) => ({ pending: 'info', in_progress: 'warning', completed: 'success', overdue: 'danger', skipped: '', cancelled: '' }[s] || '')

const loadList = async (page = 1) => {
  pagination.page = page
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      per_page: pagination.per_page,
      page,
      status: filter.status || undefined,
    }
    if (filter.dateRange && filter.dateRange.length === 2) {
      params.date_from = filter.dateRange[0]
      params.date_to = filter.dateRange[1]
    }
    const r = await inspection.listTasks(params)
    const d = r?.data ?? {}
    list.value = d.data || []
    pagination.total = d.total || 0
  } finally {
    loading.value = false
  }
}

const resetFilter = () => {
  filter.keyword = ''
  filter.status = ''
  filter.dateRange = null
  loadList(1)
}

const handleSkip = async (row: InspectionTask) => {
  try {
    const { value: reason } = await ElMessageBox.prompt('请输入跳过原因', '跳过任务', { inputType: 'textarea' })
    if (!reason) return
    await inspection.skipTask(row.id, reason)
    ElMessage.success('已跳过')
    loadList(pagination.page)
  } catch (e: unknown) {
    if (e !== 'cancel') ElMessage.error(e?.message || '操作失败')
  }
}

onMounted(loadList)
</script>

<style scoped>
.page-container { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.content-card { background: #fff; padding: 16px; border-radius: 8px; }
.pagination-wrap { margin-top: 16px; text-align: right; }
</style>
