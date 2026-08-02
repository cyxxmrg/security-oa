<template>
  <el-card class="info-card" shadow="never">
    <template #header>
      <div class="card-header">
        <span class="card-title">
          <el-icon><Document /></el-icon>工序信息
        </span>
        <span class="card-meta">{{ instance.template?.name || instance.template_name || '-' }}</span>
      </div>
    </template>
    <el-descriptions :column="3" border :title="''">
      <el-descriptions-item label="所属项目">
        <span class="link-text" @click="emit('viewProject', instance.project_id)">
          {{ instance.project?.name || instance.project_name || '-' }}
        </span>
      </el-descriptions-item>
      <el-descriptions-item label="工序模板">
        {{ instance.template?.name || instance.template_name || '-' }}
      </el-descriptions-item>
      <el-descriptions-item label="模板编码">
        <span class="code-text">{{ instance.template?.code || '-' }}</span>
      </el-descriptions-item>
      <el-descriptions-item label="工序编号">
        <span class="code-text">{{ instance.code || '-' }}</span>
      </el-descriptions-item>
      <el-descriptions-item label="序号">#{{ instance.sequence ?? '-' }}</el-descriptions-item>
      <el-descriptions-item label="行业">
        <el-tag size="small" effect="plain">{{ industryLabel(instance.industry) }}</el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="状态">
        <el-tag :type="statusTagType(instance.status)" effect="dark" size="small">
          {{ statusLabel(instance.status) }}
        </el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="责任人">
        {{ instance.foreman?.name || instance.foreman_name || instance.responsible_name || '-' }}
      </el-descriptions-item>
      <el-descriptions-item label="验收要点" :span="3">
        <div v-if="getAcceptancePoints()" class="multiline-text">{{ getAcceptancePoints() }}</div>
        <span v-else class="muted">-</span>
      </el-descriptions-item>
      <el-descriptions-item label="计划工期" :span="2">
        {{ formatDate(instance.planned_start_date || instance.planned_start) }}
        <span class="date-sep">→</span>
        {{ formatDate(instance.planned_end_date || instance.planned_end) }}
      </el-descriptions-item>
      <el-descriptions-item label="计划工期(天)">
        {{ instance.planned_duration_days ?? instance.planned_duration ?? '-' }}
      </el-descriptions-item>
      <el-descriptions-item label="实际工期" :span="2">
        {{ formatDate(instance.actual_start_date || instance.actual_start) || '-' }}
        <span class="date-sep">→</span>
        {{ formatDate(instance.actual_end_date || instance.actual_end) || '-' }}
      </el-descriptions-item>
      <el-descriptions-item label="实际工期(天)">
        {{ instance.actual_duration_days ?? '-' }}
      </el-descriptions-item>
      <el-descriptions-item label="施工位置" :span="3">
        {{ instance.location || '-' }}
      </el-descriptions-item>
      <el-descriptions-item label="备注/说明" :span="3">
        <div v-if="getRemark()" class="multiline-text">{{ getRemark() }}</div>
        <span v-else class="muted">-</span>
      </el-descriptions-item>
    </el-descriptions>
  </el-card>
</template>

<script setup lang="ts">
import { Document } from '@element-plus/icons-vue'
import { statusLabel, statusTagType, industryLabel, formatDate, type ProcessInstance } from './types'

// v0.3.25 抽自 process/InstanceDetail.vue:62-127
const props = defineProps<{
  instance: ProcessInstance
}>()

const emit = defineEmits<{
  (e: 'viewProject', projectId?: number | null): void
}>()

function getAcceptancePoints(): string {
  const tpl = props.instance.template
  if (!tpl) return ''
  const ac = tpl.acceptance_criteria
  if (Array.isArray(ac) && ac.length) return ac.join('；')
  const qc = tpl.quality_checkpoints
  if (Array.isArray(qc) && qc.length) return qc.join('；')
  return ''
}

function getRemark(): string {
  return props.instance.description || props.instance.remark || props.instance.acceptance_points || ''
}
</script>

<style lang="scss" scoped>
.info-card {
  background: #fff;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
}
.card-title {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
  display: flex;
  align-items: center;
  gap: 6px;
}
.card-meta { font-size: 12px; color: #909399; }
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; &:hover { text-decoration: underline; } }
.code-text { font-family: monospace; color: #0C447C; font-weight: 500; }
.muted { color: #c0c4cc; }
.date-sep { color: #c0c4cc; margin: 0 4px; }
.multiline-text {
  white-space: pre-wrap;
  line-height: 1.6;
  color: #303133;
  padding: 4px 0;
}
</style>
