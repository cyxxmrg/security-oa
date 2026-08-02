<template>
  <div class="tab-content">
    <h3 class="section-title">项目阶段流程</h3>
    <el-card shadow="never" class="stage-stepper">
      <el-steps
        :active="currentStageIndex"
        finish-status="success"
        align-center
        class="custom-steps"
      >
        <el-step
          v-for="(stage, idx) in stages"
          :key="stage"
          :title="STAGE_LABEL_MAP[stage] || stage"
          description=""
          class="clickable-step"
          style="cursor:pointer"
          @click="openRecordDialog(stage)"
        >
          <template #icon>
            <div class="step-icon" :class="{ active: idx <= currentStageIndex, current: idx === currentStageIndex }">
              <el-icon v-if="idx < currentStageIndex"><Check /></el-icon>
              <span v-else>{{ idx + 1 }}</span>
            </div>
          </template>
        </el-step>
      </el-steps>
    </el-card>

    <el-row :gutter="16" class="stage-detail">
      <el-col :span="14">
        <h3 class="section-title">当前阶段 - {{ stageLabel }}</h3>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="阶段名称">{{ stageLabel }}</el-descriptions-item>
          <el-descriptions-item label="负责人">{{ managerName }}</el-descriptions-item>
          <el-descriptions-item label="开始时间">{{ formatDate(project.start_date) }}</el-descriptions-item>
          <el-descriptions-item label="计划完成">{{ formatDate(project.end_date) }}</el-descriptions-item>
          <el-descriptions-item label="阶段状态">
            <el-tag :type="statusTagType(project.status)" size="small">
              {{ statusLabel(project.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="完成进度">
            <el-progress :percentage="displayProgress" :stroke-width="10" />
          </el-descriptions-item>
          <el-descriptions-item label="阶段说明" :span="2">{{ project.description || '-' }}</el-descriptions-item>
        </el-descriptions>

        <h4 class="sub-title">合同付款节点 ({{ paymentNodes.length }})</h4>
        <el-table v-if="paymentNodes.length > 0" :data="paymentNodes" border size="default">
          <el-table-column type="index" label="#" width="55" align="center" />
          <el-table-column prop="name" label="节点名称" min-width="160" />
          <el-table-column prop="percentage" label="占比" width="90" align="center">
            <template #default="{ row }">{{ row.percentage }}%</template>
          </el-table-column>
          <el-table-column prop="amount" label="金额(元)" width="120" align="right">
            <template #default="{ row }">¥ {{ Number(row.amount).toLocaleString() }}</template>
          </el-table-column>
          <el-table-column prop="planned_date" label="计划日期" width="120" align="center">
            <template #default="{ row }">{{ formatDate(row.planned_date) }}</template>
          </el-table-column>
          <el-table-column prop="actual_date" label="实际日期" width="120" align="center">
            <template #default="{ row }">{{ row.actual_date ? formatDate(row.actual_date) : '-' }}</template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="paymentStatusTag(row.status)" size="small">
                {{ paymentStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else description="暂无付款节点" :image-size="60" />
      </el-col>

      <el-col :span="10">
        <h4 class="sub-title">交付物</h4>
        <el-table v-if="deliverables.length > 0" :data="deliverables" border size="default">
          <el-table-column type="index" label="#" width="55" align="center" />
          <el-table-column prop="name" label="交付物名称" min-width="160" />
          <el-table-column prop="type" label="类型" width="100" align="center" />
          <el-table-column prop="submitter" label="提交人" width="100" />
          <el-table-column prop="date" label="提交时间" width="140" />
          <el-table-column label="操作" width="120" align="center">
            <template #default="{ row }">
              <el-button text type="primary" @click="$emit('preview', row)">预览</el-button>
              <el-button text type="primary" @click="$emit('download', row)">下载</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else description="暂无交付物" :image-size="60" />

        <h4 class="sub-title">阶段流转记录 ({{ timeline.length }})</h4>
        <el-timeline v-if="timeline.length > 0">
          <el-timeline-item
            v-for="(t, i) in timeline"
            :key="i"
            :timestamp="t.time"
            placement="top"
            :type="i === 0 ? 'primary' : 'info'"
          >
            <el-card shadow="hover" class="hist-card">
              <div class="hist-head">
                <el-tag size="small" :type="i === 0 ? 'primary' : 'info'">{{ t.stage }}</el-tag>
                <span class="hist-action">{{ t.action }}</span>
              </div>
              <div class="hist-content">{{ t.content }}</div>
              <div v-if="t.operator && t.operator !== '-'" class="hist-op">操作人: {{ t.operator }}</div>
            </el-card>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-else description="暂无流转记录" :image-size="60" />
      </el-col>
    </el-row>
  </div>

  <!-- 阶段记录对话框 -->
  <el-dialog v-model="showRecordDialog" :title="'记录阶段 - ' + recordStageLabel" width="500px" :close-on-click-modal="false">
    <el-form ref="formRef" :model="recordForm" label-width="100px">
      <el-form-item label="阶段">
        <el-tag effect="dark" type="primary">{{ recordStageLabel }}</el-tag>
      </el-form-item>
      <el-form-item label="操作类型" prop="action">
        <el-radio-group v-model="recordForm.action">
          <el-radio value="enter">进入</el-radio>
          <el-radio value="complete">完成</el-radio>
          <el-radio value="skip">跳过</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="备注" prop="note">
        <el-input v-model="recordForm.note" type="textarea" :rows="3" placeholder="填写阶段备注说明" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="showRecordDialog = false">取消</el-button>
      <el-button type="primary" :loading="submittingStage" @click="submitRecord">确认记录</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import { post } from '@/utils/request'
import {
  type Project, type PaymentNode, type TimelineEntry,
  STAGES, STAGE_LABEL_MAP, STAGE_INDEX_MAP,
  getManagerName, statusLabel, formatDate, type TagType,
} from '../types'

const props = defineProps<{
  project: Project
  tracking: { current_stage_index?: number; timeline: TimelineEntry[]; payment: { nodes: PaymentNode[] } }
  displayProgress: number
  paidAmount: string
  managerName?: string
}>()

// 阶段手动记录 (V1.2.12i)
const showRecordDialog = ref(false)
const submittingStage = ref(false)
const recordTargetStage = ref('')
const recordStageLabel = computed(() => STAGE_LABEL_MAP[recordTargetStage.value] || recordTargetStage.value)
const recordForm = ref({ action: 'enter', note: '' })
const openRecordDialog = (stageKey: string) => {
  recordTargetStage.value = stageKey
  recordForm.value = { action: 'enter', note: '' }
  showRecordDialog.value = true
}
const submitRecord = async () => {
  submittingStage.value = true
  try {
    await post(`/projects/${props.project.id}/stage-logs`, {
      stage_key: recordTargetStage.value,
      action: recordForm.value.action,
      note: recordForm.value.note || null,
    })
    ElMessage.success('阶段已记录')
    showRecordDialog.value = false
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || '记录失败')
  } finally { submittingStage.value = false }
}

defineEmits<{
  (e: 'preview', row: Record<string, unknown>): void
  (e: 'download', row: Record<string, unknown>): void
}>()

const stages = STAGES
const paymentNodes = computed(() => props.tracking.payment.nodes || [])
const timeline = computed(() => props.tracking.timeline || [])

const stageLabel = computed(() => {
  return STAGE_LABEL_MAP[props.project.stage || ''] || props.project.stage || '-'
})

const currentStageIndex = computed(() => {
  if (typeof props.tracking.current_stage_index === 'number') return props.tracking.current_stage_index
  return STAGE_INDEX_MAP[props.project.stage || ''] ?? 0
})

// 交付物 — 当前 stageDetail 字段是 mock，先用空数组。V1.1 接入交付物接口后替换
const deliverables = computed<Record<string, unknown>[]>(() => [])

const statusTagType = (s?: string): TagType => {
  if (s === 'completed') return 'success'
  if (s === 'in_progress') return 'warning'
  if (s === 'suspended') return 'danger'
  return 'info'
}

const paymentStatusLabel = (s?: string) => {
  const map: Record<string, string> = { paid: '已付', pending: '待付', overdue: '逾期', partial: '部分付' }
  return map[s || ''] || s || '-'
}
const paymentStatusTag = (s?: string): TagType => {
  if (s === 'paid') return 'success'
  if (s === 'overdue') return 'danger'
  if (s === 'pending') return 'warning'
  return 'info'
}
</script>

<style scoped>
.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  margin: 0 0 16px 0;
  padding-left: 8px;
  border-left: 3px solid #0C447C;
}
.sub-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin: 24px 0 12px 0;
}
.stage-stepper {
  margin-bottom: 24px;
  background: #fafbfc;
}
.custom-steps {
  padding: 24px 12px 16px;
}
.custom-steps :deep(.el-step__title) {
  font-size: 13px !important;
  font-weight: 600;
  color: #303133;
  margin-top: 8px;
}
.custom-steps :deep(.el-step__title.is-process) {
  color: #0C447C;
  font-weight: 700;
}
.custom-steps :deep(.el-step__title.is-finish) {
  color: #1D9E75;
}
.custom-steps :deep(.el-step__head) {
  padding-bottom: 0;
}
.step-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e4e7ed;
  color: #909399;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.3s;
}
.step-icon.active {
  background: #0C447C;
  color: #fff;
}
.step-icon.current {
  background: #185FA5;
  color: #fff;
  box-shadow: 0 0 0 4px rgba(24, 95, 165, 0.15);
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 4px rgba(24, 95, 165, 0.15); }
  50% { box-shadow: 0 0 0 8px rgba(24, 95, 165, 0.05); }
}
.stage-detail {
  margin-top: 16px;
}
.hist-card {
  margin-bottom: 4px;
}
.hist-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}
.hist-action {
  font-size: 12px;
  color: #606266;
  font-weight: 600;
}
.hist-content {
  font-size: 13px;
  color: #303133;
  line-height: 1.5;
}
.hist-op {
  font-size: 11px;
  color: #909399;
  margin-top: 4px;
}
</style>
