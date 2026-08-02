<template>
  <div class="page-container">
    <div class="page-header">
      <h2>巡检总览</h2>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadAll">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="showPlanForm = true">新建巡检计划</el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div class="stat-cards">
      <div class="stat-card">
        <div class="stat-icon" style="background: #e1f0ff"><el-icon :size="24" color="#0C447C"><Document /></el-icon></div>
        <div class="stat-body">
          <div class="stat-label">启用计划</div>
          <div class="stat-value">{{ stats.active_plans ?? 0 }} / {{ stats.total_plans ?? 0 }}</div>
          <div class="stat-sub">合同维度的排程</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #fff7e6"><el-icon :size="24" color="#fa8c16"><Clock /></el-icon></div>
        <div class="stat-body">
          <div class="stat-label">待执行 / 逾期</div>
          <div class="stat-value" :class="{ 'text-danger': (stats.overdue_tasks ?? 0) > 0 }">
            {{ stats.pending_tasks ?? 0 }} / <span class="text-danger">{{ stats.overdue_tasks ?? 0 }}</span>
          </div>
          <div class="stat-sub">本月已生成 {{ stats.monthly_tasks ?? 0 }} 个</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #f6ffed"><el-icon :size="24" color="#52c41a"><CircleCheckFilled /></el-icon></div>
        <div class="stat-body">
          <div class="stat-label">本月已完成</div>
          <div class="stat-value">{{ stats.completed_tasks ?? 0 }}</div>
          <div class="stat-sub">本月巡检任务</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #fff1f0"><el-icon :size="24" color="#f5222d"><WarningFilled /></el-icon></div>
        <div class="stat-body">
          <div class="stat-label">未关单异常</div>
          <div class="stat-value" :class="{ 'text-danger': (stats.open_issues ?? 0) > 0 }">{{ stats.open_issues ?? 0 }}</div>
          <div class="stat-sub">本月新增 {{ stats.monthly_issues ?? 0 }} 个 · 已转工单 {{ stats.auto_work_orders ?? 0 }}</div>
        </div>
      </div>
    </div>

    <el-row :gutter="16" style="margin-top: 16px">
      <!-- 即将到来 -->
      <el-col :xs="24" :md="12">
        <el-card shadow="never" class="content-card">
          <template #header>
            <div class="card-header">
              <span><el-icon><Calendar /></el-icon> 即将到来的巡检任务</span>
              <el-button link type="primary" @click="$router.push('/inspection/tasks')">查看全部</el-button>
            </div>
          </template>
          <el-table :data="upcomingTasks" stripe size="small" v-loading="loading">
            <el-table-column prop="task_no" label="任务号" width="140" />
            <el-table-column label="客户" min-width="120" show-overflow-tooltip>
              <template #default="{ row }">
                <span>{{ row.customer?.name || '-' }}</span>
              </template>
            </el-table-column>
            <el-table-column label="计划" min-width="120" show-overflow-tooltip>
              <template #default="{ row }">
                <span>{{ row.plan?.name || '-' }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="scheduled_date" label="计划日期" width="100" />
            <el-table-column label="状态" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="taskStatusColor(row.status)" size="small">{{ taskStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="100" align="center" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" size="small" @click="goTask(row)">详情</el-button>
                <el-button v-if="row.status === 'pending' || row.status === 'overdue'" link type="success" size="small" @click="$router.push(`/inspection/tasks/checkin/${row.id}`)">打卡</el-button>
              </template>
            </el-table-column>
            <template #empty>
              <el-empty description="暂无待执行任务" />
            </template>
          </el-table>
        </el-card>
      </el-col>

      <!-- 最近异常 -->
      <el-col :xs="24" :md="12">
        <el-card shadow="never" class="content-card">
          <template #header>
            <div class="card-header">
              <span><el-icon><Warning /></el-icon> 最近异常</span>
              <el-button link type="primary" @click="$router.push('/inspection/issues')">查看全部</el-button>
            </div>
          </template>
          <el-table :data="recentIssues" stripe size="small" v-loading="loading">
            <el-table-column prop="issue_no" label="异常号" width="140" />
            <el-table-column prop="title" label="标题" min-width="140" show-overflow-tooltip />
            <el-table-column label="设备" min-width="100" show-overflow-tooltip>
              <template #default="{ row }">
                <span>{{ row.equipment_name }}</span>
              </template>
            </el-table-column>
            <el-table-column label="严重" width="70" align="center">
              <template #default="{ row }">
                <el-tag :type="severityColor(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="90" align="center">
              <template #default="{ row }">
                <el-tag :type="issueStatusColor(row.status)" size="small">{{ issueStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80" align="center" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" size="small" @click="$router.push(`/inspection/issues?view=${row.id}`)">查看</el-button>
              </template>
            </el-table-column>
            <template #empty>
              <el-empty description="暂无异常" />
            </template>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="content-card" style="margin-top: 16px">
      <template #header>
        <div class="card-header">
          <span><el-icon><List /></el-icon> 最近任务动态</span>
        </div>
      </template>
      <el-table :data="recentTasks" stripe size="small" v-loading="loading">
        <el-table-column prop="task_no" label="任务号" width="140" />
        <el-table-column label="计划" min-width="120" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.plan?.name || '-' }}</span></template>
        </el-table-column>
        <el-table-column label="客户" min-width="120" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.customer?.name || '-' }}</span></template>
        </el-table-column>
        <el-table-column prop="scheduled_date" label="计划日期" width="110" />
        <el-table-column label="执行人" width="100" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.assignee?.name || '未指派' }}</span></template>
        </el-table-column>
        <el-table-column label="设备/异常" width="120" align="center">
          <template #default="{ row }">
            <span>设备 {{ row.equipment_count }}</span>
            <el-tag v-if="row.issue_count > 0" type="danger" size="small" style="margin-left: 6px">{{ row.issue_count }} 异常</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="taskStatusColor(row.status)" size="small">{{ taskStatusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty description="暂无任务动态" />
        </template>
      </el-table>
    </el-card>

    <!-- 新建巡检计划弹窗 -->
    <el-dialog v-model="showPlanForm" title="新建巡检计划" width="1200px" :close-on-click-modal="false" destroy-on-close>
      <PlanForm embedded @done="showPlanForm = false; loadAll()" />
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Refresh, Plus, Document, Clock, CircleCheckFilled, WarningFilled, Calendar, Warning, List } from '@element-plus/icons-vue'
import { inspection, TASK_STATUS_LABEL, ISSUE_STATUS_LABEL, SEVERITY_LABEL, type InspectionTask, type InspectionIssue } from '@/api/inspection'
import { unwrapItem } from '@/utils/response' // V1.2.10 fix: request.ts 不自动解包 {code,data}
import PlanForm from './PlanForm.vue'

const router = useRouter()
const loading = ref(false)
const stats = ref<Record<string, unknown>>({})
const upcomingTasks = ref<InspectionTask[]>([])
const recentTasks = ref<InspectionTask[]>([])
const recentIssues = ref<InspectionIssue[]>([])
const showPlanForm = ref(false)

const taskStatusLabel = (s: string) => TASK_STATUS_LABEL[s as keyof typeof TASK_STATUS_LABEL] || s
const taskStatusColor = (s: string) => ({ pending: 'info', in_progress: 'warning', completed: 'success', overdue: 'danger', skipped: '', cancelled: '' }[s] || '')
const issueStatusLabel = (s: string) => ISSUE_STATUS_LABEL[s as keyof typeof ISSUE_STATUS_LABEL] || s
const issueStatusColor = (s: string) => ({ open: 'danger', work_order_created: 'warning', resolved: 'success', ignored: '' }[s] || '')
const severityLabel = (s: string) => SEVERITY_LABEL[s as keyof typeof SEVERITY_LABEL] || s
const severityColor = (s: string) => ({ low: 'info', medium: 'warning', high: 'danger', critical: 'danger' }[s] || '')

const goTask = (row: InspectionTask) => router.push(`/inspection/tasks/checkin/${row.id}`)

const loadAll = async () => {
  loading.value = true
  try {
    const d: Record<string, unknown> = unwrapItem(await inspection.overview())
    stats.value = d?.stats || {}
    upcomingTasks.value = d?.upcomingTasks || []
    recentTasks.value = d?.recentTasks || []
    recentIssues.value = d?.recentIssues || []
  } catch (e: unknown) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

onMounted(loadAll)
</script>

<style scoped>
.page-container { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; }
.stat-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.stat-card { background: #fff; border-radius: 8px; padding: 16px; display: flex; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.stat-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.stat-label { color: #999; font-size: 12px; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 600; color: #333; line-height: 1.2; }
.stat-sub { color: #999; font-size: 11px; margin-top: 4px; }
.content-card { border: none; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.text-danger { color: #f5222d; }
@media (max-width: 768px) { .stat-cards { grid-template-columns: 1fr 1fr; } }
</style>
