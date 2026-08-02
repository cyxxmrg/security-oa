<template>
  <div :class="embedded ? '' : 'page-container'">
    <div v-if="!embedded" class="page-header">
      <h2>巡检计划详情</h2>
      <el-button @click="$router.back()">返回</el-button>
    </div>

    <div v-if="plan" v-loading="loading">
      <!-- 顶部信息卡 -->
      <el-card shadow="never" class="info-card">
        <el-row :gutter="24">
          <el-col :span="16">
            <h3 style="margin: 0 0 8px 0">{{ plan.name }} <el-tag :type="planStatusColor(plan.status)" size="default" style="margin-left: 8px">{{ PLAN_STATUS_LABEL[plan.status] }}</el-tag></h3>
            <div class="info-row">
              <span class="info-label">计划编号:</span>
              <span>{{ plan.plan_no }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">合同:</span>
              <span>{{ plan.contract?.contract_no || '-' }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">客户:</span>
              <span>{{ plan.customer?.name || '-' }}</span>
            </div>
            <div class="info-row">
              <span class="info-label">排程:</span>
              <span>{{ FREQUENCY_LABEL[plan.frequency] }} · {{ plan.start_date }} ~ {{ plan.end_date || '无限期' }}</span>
            </div>
            <div v-if="plan.scope" class="info-row">
              <span class="info-label">范围:</span>
              <span>{{ plan.scope }}</span>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="stat-mini">
              <div class="stat-mini-label">总任务</div>
              <div class="stat-mini-value">{{ plan.total_generated }}</div>
            </div>
            <div class="stat-mini">
              <div class="stat-mini-label">已完成</div>
              <div class="stat-mini-value text-success">{{ plan.total_completed }}</div>
            </div>
            <div class="stat-mini">
              <div class="stat-mini-label">总异常</div>
              <div class="stat-mini-value text-danger">{{ plan.total_issues }}</div>
            </div>
            <div class="stat-mini">
              <div class="stat-mini-label">完成率</div>
              <div class="stat-mini-value">{{ plan.completion_rate || 0 }}%</div>
            </div>
          </el-col>
        </el-row>
      </el-card>

      <!-- Tabs -->
      <el-tabs v-model="activeTab" class="detail-tabs">
        <!-- 任务 Tab -->
        <el-tab-pane label="执行任务" name="tasks">
          <el-table :data="(plan.tasks || [])" stripe size="small">
            <el-table-column prop="task_no" label="任务号" width="140" />
            <el-table-column prop="scheduled_date" label="计划日期" width="100" />
            <el-table-column label="执行人" width="100" show-overflow-tooltip>
              <template #default="{ row }"><span>{{ row.assignee?.name || '未指派' }}</span></template>
            </el-table-column>
            <el-table-column label="设备/异常" width="120" align="center">
              <template #default="{ row }">
                <span>设备 {{ row.equipment_count }}</span>
                <el-tag v-if="row.issue_count > 0" type="danger" size="small" style="margin-left: 6px">{{ row.issue_count }} 异常</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="taskStatusColor(row.status)" size="small">{{ taskStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="completed_at" label="完成时间" width="160" show-overflow-tooltip />
            <el-table-column label="操作" width="120" align="center" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" size="small" @click="$router.push(`/inspection/tasks/checkin/${row.id}`)">详情</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 检查项模板 Tab -->
        <el-tab-pane label="检查项模板" name="checklist">
          <el-table :data="(plan.checklist_template || [])" stripe size="small" border>
            <el-table-column label="检查项" prop="name" min-width="160" />
            <el-table-column label="类型" width="100">
              <template #default="{ row }"><el-tag size="small">{{ row.type }}</el-tag></template>
            </el-table-column>
            <el-table-column label="正常值" prop="normal_value" width="120" />
            <el-table-column label="选项" min-width="200">
              <template #default="{ row }"><span>{{ (row.options || []).join(' / ') || '-' }}</span></template>
            </el-table-column>
            <el-table-column label="必填" width="80" align="center">
              <template #default="{ row }"><el-tag v-if="row.required" type="success" size="small">是</el-tag><el-tag v-else size="small">否</el-tag></template>
            </el-table-column>
            <template #empty>
              <el-empty description="该计划未配置检查项模板" />
            </template>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { inspection, PLAN_STATUS_LABEL, FREQUENCY_LABEL, TASK_STATUS_LABEL, type InspectionPlan } from '@/api/inspection'

const props = defineProps<{ planId?: number; embedded?: boolean }>()
const route = useRoute()
const loading = ref(false)
const plan = ref<InspectionPlan | null>(null)
const activeTab = ref('tasks')

const planStatusColor = (s: string) => ({ active: 'success', paused: 'warning', expired: 'info', cancelled: '' }[s] || '')
const taskStatusLabel = (s: string) => TASK_STATUS_LABEL[s as keyof typeof TASK_STATUS_LABEL] || s
const taskStatusColor = (s: string) => ({ pending: 'info', in_progress: 'warning', completed: 'success', overdue: 'danger', skipped: '', cancelled: '' }[s] || '')

const loadPlan = async () => {
  const id = props.planId || Number(route.params.id)
  if (!id) return
  loading.value = true
  try {
    const r = await inspection.getPlan(id)
    plan.value = r?.data
  } catch (e: unknown) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

onMounted(loadPlan)
</script>

<style scoped>
.page-container { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.info-card { margin-bottom: 16px; }
.info-row { margin: 6px 0; font-size: 13px; }
.info-label { color: #999; margin-right: 6px; min-width: 70px; display: inline-block; }
.stat-mini { display: inline-block; width: 48%; margin-bottom: 8px; padding: 6px 0; }
.stat-mini-label { color: #999; font-size: 12px; }
.stat-mini-value { font-size: 20px; font-weight: 600; }
.text-success { color: #52c41a; }
.text-danger { color: #f5222d; }
.detail-tabs { background: #fff; padding: 16px; border-radius: 8px; }
</style>
