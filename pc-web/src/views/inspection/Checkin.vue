<template>
  <div class="page-container">
    <div class="page-header">
      <h2>{{ isCheckin ? '现场打卡' : '巡检详情' }}</h2>
      <el-button @click="$router.back()">返回</el-button>
    </div>

    <div v-if="task" v-loading="loading">
      <!-- 任务信息 -->
      <el-card shadow="never" class="info-card">
        <el-row :gutter="24">
          <el-col :span="16">
            <h3 style="margin: 0 0 8px 0">
              {{ task.plan?.name }}
              <el-tag :type="taskStatusColor(task.status)" size="default" style="margin-left: 8px">{{ taskStatusLabel(task.status) }}</el-tag>
            </h3>
            <div class="info-row"><span class="info-label">任务号:</span> {{ task.task_no }}</div>
            <div class="info-row"><span class="info-label">计划日期:</span> {{ task.scheduled_date }} {{ task.scheduled_hour }}:00</div>
            <div class="info-row"><span class="info-label">客户:</span> {{ task.customer?.name || '-' }}</div>
            <div class="info-row" v-if="task.plan?.scope"><span class="info-label">范围:</span> {{ task.plan.scope }}</div>
          </el-col>
          <el-col :span="8">
            <div v-if="task.record" class="record-info">
              <el-icon style="color: #52c41a"><CircleCheckFilled /></el-icon>
              <span>已打卡 {{ task.record.record_no }}</span>
              <div style="font-size: 12px; color: #999; margin-top: 4px">
                {{ task.record.checkin_at }}<br>
                正常 {{ task.record.normal_count }} / 异常 {{ task.record.abnormal_count }}
              </div>
            </div>
          </el-col>
        </el-row>
      </el-card>

      <!-- 打卡表单 (仅 pending/overdue/in_progress) -->
      <el-card v-if="canCheckin" shadow="never" class="action-card" style="margin-top: 16px">
        <template #header><span><el-icon><Position /></el-icon> 现场打卡</span></template>
        <el-form :model="checkinForm" label-width="120px">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="打卡地点">
                <el-input v-model="checkinForm.checkin_location" placeholder="如 海淀区中关村大街 27 号" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="纬度">
                <el-input v-model.number="checkinForm.checkin_lat" placeholder="39.9847" type="number" />
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="经度">
                <el-input v-model.number="checkinForm.checkin_lng" placeholder="116.3100" type="number" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item label="现场照片">
            <el-button size="small" :icon="Picture" @click="addPhoto">添加照片</el-button>
            <div class="photo-list">
              <el-tag v-for="(p, i) in checkinForm.checkin_photos" :key="i" closable @close="checkinForm.checkin_photos.splice(i, 1)">{{ p }}</el-tag>
            </div>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :icon="Position" :loading="checkinSubmitting" @click="onCheckin">确认打卡</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 完成 (checkout) - 有 record 但未 checkout -->
      <el-card v-if="task.record && task.record.status === 'checked_in' && canCheckout" shadow="never" class="action-card" style="margin-top: 16px">
        <template #header><span><el-icon><EditPen /></el-icon> 巡检完成</span></template>
        <el-form :model="checkoutForm" label-width="120px">
          <el-divider content-position="left">检查项 (按计划模板)</el-divider>
          <div v-for="(item, idx) in (task.plan?.checklist_template || [])" :key="idx" class="checklist-row">
            <label>{{ item.name }}<span v-if="item.required" style="color: #f5222d"> *</span></label>
            <el-input v-if="item.type === 'text'" v-model="checkoutForm.answers[item.name]" placeholder="文本" />
            <el-input-number v-else-if="item.type === 'number'" v-model.number="checkoutForm.answers[item.name]" :min="0" />
            <el-select v-else-if="item.type === 'select'" v-model="checkoutForm.answers[item.name]" placeholder="选择" style="width: 100%">
              <el-option v-for="opt in (item.options || [])" :key="opt" :label="opt" :value="opt" />
            </el-select>
            <el-input v-else v-model="checkoutForm.answers[item.name]" placeholder="拍照说明" />
          </div>
          <el-form-item label="巡检小结">
            <el-input v-model="checkoutForm.summary" type="textarea" :rows="3" placeholder="如 6 号楼监控全部正常运行" />
          </el-form-item>
          <el-form-item label="质量自评">
            <el-rate v-model="checkoutForm.rating" />
          </el-form-item>
          <el-divider content-position="left">异常清单 (可选)</el-divider>
          <el-button :icon="Plus" size="small" @click="addIssue">添加异常</el-button>
          <el-table v-if="checkoutForm.issues.length > 0" :data="checkoutForm.issues" border size="small" style="margin-top: 12px">
            <el-table-column label="设备名" min-width="140">
              <template #default="{ row }"><el-input v-model="row.equipment_name" size="small" /></template>
            </el-table-column>
            <el-table-column label="位置" min-width="140">
              <template #default="{ row }"><el-input v-model="row.equipment_location" size="small" /></template>
            </el-table-column>
            <el-table-column label="类型" width="120">
              <template #default="{ row }">
                <el-select v-model="row.issue_type" size="small" style="width: 100%">
                  <el-option v-for="(label, value) in ISSUE_TYPE_LABEL" :key="value" :label="label" :value="value" />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="严重" width="100">
              <template #default="{ row }">
                <el-select v-model="row.severity" size="small" style="width: 100%">
                  <el-option v-for="(label, value) in SEVERITY_LABEL" :key="value" :label="label" :value="value" />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="标题" min-width="160">
              <template #default="{ row }"><el-input v-model="row.title" size="small" /></template>
            </el-table-column>
            <el-table-column label="操作" width="60" align="center">
              <template #default="{ $index }"><el-button link type="danger" size="small" @click="checkoutForm.issues.splice($index, 1)">删除</el-button></template>
            </el-table-column>
          </el-table>
          <el-form-item style="margin-top: 16px">
            <el-button type="primary" :icon="Check" :loading="checkoutSubmitting" @click="onCheckout">提交完成</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 异常清单 Tab -->
      <el-card v-if="task.issues && task.issues.length > 0" shadow="never" style="margin-top: 16px">
        <template #header><span><el-icon><Warning /></el-icon> 巡检异常 (高严重度自动转工单)</span></template>
        <el-table :data="task.issues" stripe size="small">
          <el-table-column prop="issue_no" label="异常号" width="140" />
          <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
          <el-table-column prop="equipment_name" label="设备" width="120" show-overflow-tooltip />
          <el-table-column label="类型" width="100">
            <template #default="{ row }"><el-tag size="small">{{ ISSUE_TYPE_LABEL[row.issue_type] || row.issue_type }}</el-tag></template>
          </el-table-column>
          <el-table-column label="严重" width="80" align="center">
            <template #default="{ row }"><el-tag :type="severityColor(row.severity)" size="small">{{ SEVERITY_LABEL[row.severity] || row.severity }}</el-tag></template>
          </el-table-column>
          <el-table-column label="状态" width="120" align="center">
            <template #default="{ row }">
              <el-tag :type="issueStatusColor(row.status)" size="small">{{ ISSUE_STATUS_LABEL[row.status] || row.status }}</el-tag>
              <el-link v-if="row.work_order_id" type="primary" :href="`/maintenance/work-orders/${row.work_order_id}`" target="_blank" style="margin-left: 4px">工单</el-link>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Refresh, Position, Picture, EditPen, Check, CircleCheckFilled, Plus, Warning, Calendar, OfficeBuilding } from '@element-plus/icons-vue'
import { inspection, TASK_STATUS_LABEL, ISSUE_STATUS_LABEL, ISSUE_TYPE_LABEL, SEVERITY_LABEL, type InspectionTask } from '@/api/inspection'

const route = useRoute()
const loading = ref(false)
const task = ref<InspectionTask | null>(null)
const isCheckin = computed(() => {
  if (!task.value) return false
  return ['pending', 'overdue', 'in_progress'].includes(task.value.status)
})
const canCheckin = computed(() => task.value && ['pending', 'overdue', 'in_progress'].includes(task.value.status) && !task.value.record)
const canCheckout = computed(() => task.value && task.value.record && task.value.record.status === 'checked_in')

const checkinForm = reactive({ checkin_location: '', checkin_lat: undefined as number | undefined, checkin_lng: undefined as number | undefined, checkin_photos: [] as string[] })
const checkinSubmitting = ref(false)
const checkoutForm = reactive<{ answers: Record<string, unknown>; summary: string; rating: number; issues: Record<string, unknown>[] }>({ answers: {}, summary: '', rating: 5, issues: [] })
const checkoutSubmitting = ref(false)

const taskStatusLabel = (s: string) => TASK_STATUS_LABEL[s as keyof typeof TASK_STATUS_LABEL] || s
const taskStatusColor = (s: string) => ({ pending: 'info', in_progress: 'warning', completed: 'success', overdue: 'danger', skipped: '', cancelled: '' }[s] || '')
const issueStatusColor = (s: string) => ({ open: 'danger', work_order_created: 'warning', resolved: 'success', ignored: '' }[s] || '')
const severityColor = (s: string) => ({ low: 'info', medium: 'warning', high: 'danger', critical: 'danger' }[s] || '')

const addPhoto = () => {
  const url = prompt('输入照片 URL (演示用)')
  if (url) checkinForm.checkin_photos.push(url)
}

const addIssue = () => {
  checkoutForm.issues.push({ equipment_name: '', equipment_location: '', issue_type: 'hardware', severity: 'medium', title: '', description: '' })
}

const onCheckin = async () => {
  checkinSubmitting.value = true
  try {
    await inspection.checkinTask(task.value!.id, {
      checkin_location: checkinForm.checkin_location,
      checkin_lat: checkinForm.checkin_lat,
      checkin_lng: checkinForm.checkin_lng,
      checkin_photos: checkinForm.checkin_photos,
    })
    ElMessage.success('打卡成功, 任务进入执行中状态')
    loadTask()
  } catch (e: unknown) {
    ElMessage.error(e?.message || '打卡失败')
  } finally {
    checkinSubmitting.value = false
  }
}

const onCheckout = async () => {
  if (!task.value?.record) return
  checkoutSubmitting.value = true
  try {
    await inspection.checkoutRecord(task.value.record.id, {
      checklist_answers: checkoutForm.answers,
      summary: checkoutForm.summary,
      rating: checkoutForm.rating,
      issues: checkoutForm.issues.filter((i: Record<string, unknown>) => i.title && i.equipment_name),
    })
    ElMessage.success('已完成, 高严重度异常已自动转工单')
    loadTask()
  } catch (e: unknown) {
    ElMessage.error(e?.message || '提交失败')
  } finally {
    checkoutSubmitting.value = false
  }
}

const loadTask = async () => {
  loading.value = true
  try {
    const r = await inspection.getTask(Number(route.params.id))
    task.value = r?.data
  } catch (e: unknown) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

onMounted(loadTask)
</script>

<style scoped>
.page-container { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.info-card { margin-bottom: 16px; }
.info-row { margin: 4px 0; font-size: 13px; }
.info-label { color: #999; margin-right: 6px; min-width: 80px; display: inline-block; }
.action-card { margin-top: 16px; }
.checklist-row { margin: 8px 0; display: flex; align-items: center; gap: 12px; }
.checklist-row label { min-width: 100px; text-align: right; }
.photo-list { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 6px; }
.record-info { background: #f6ffed; padding: 12px; border-radius: 4px; display: flex; flex-direction: column; align-items: center; }
</style>
