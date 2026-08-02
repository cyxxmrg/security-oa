<template>
  <el-card class="info-card" shadow="never">
    <template #header>
      <div class="card-header">
        <span class="card-title">
          <el-icon><DataLine /></el-icon>施工进度
        </span>
        <span class="card-meta">当前状态: {{ statusLabel(instance.status) }}</span>
      </div>
    </template>
    <div class="progress-block">
      <div class="progress-row">
        <el-progress
          :percentage="Number(instance.progress || 0)"
          :color="progressColor(Number(instance.progress || 0))"
          :stroke-width="18"
          :format="(p) => p + '%'"
        />
      </div>
      <div class="progress-stats">
        <div class="progress-stat">
          <span class="stat-label">当前进度</span>
          <span class="stat-value" :style="{ color: progressColor(Number(instance.progress || 0)) }">
            {{ instance.progress ?? 0 }}%
          </span>
        </div>
        <div class="progress-stat">
          <span class="stat-label">已验收次数</span>
          <span class="stat-value">{{ inspectionList.length }} 次</span>
        </div>
        <div class="progress-stat">
          <span class="stat-label">合格次数</span>
          <span class="stat-value" style="color: #1D9E75">
            {{ inspectionList.filter((i) => resultKey(i.result) === 'pass').length }} 次
          </span>
        </div>
        <div class="progress-stat">
          <span class="stat-label">不合格/整改</span>
          <span class="stat-value" style="color: #A32D2D">
            {{ inspectionList.filter((i) => ['fail', 'partial', 'rectify'].includes(resultKey(i.result))).length }} 次
          </span>
        </div>
      </div>
    </div>
  </el-card>
</template>

<script setup lang="ts">
import { DataLine } from '@element-plus/icons-vue'
import {
  statusLabel, progressColor, resultKey,
  type ProcessInstance, type Inspection,
} from '../types'

defineProps<{
  instance: ProcessInstance
  inspectionList: Inspection[]
}>()
</script>

<style scoped>
.info-card {
  border-radius: 8px;
  border: none;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
:deep(.el-card__header) {
  padding: 14px 20px;
  background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
  border-bottom: 1px solid #f0f2f5;
}
:deep(.el-card__body) { padding: 18px 20px; }
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.card-title {
  font-size: 15px;
  font-weight: 600;
  color: #2c3e50;
  display: flex;
  align-items: center;
  gap: 8px;
}
.card-title::before {
  content: '';
  display: inline-block;
  width: 3px;
  height: 14px;
  background: linear-gradient(180deg, #0C447C 0%, #1D9E75 100%);
  border-radius: 2px;
  margin-right: 4px;
}
.card-meta {
  font-size: 12px;
  color: #909399;
  font-family: 'SF Mono', Consolas, monospace;
}
.progress-block .progress-row { padding: 8px 0 18px; }
.progress-block .progress-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  background: #fafbfc;
  padding: 14px 16px;
  border-radius: 8px;
  border: 1px solid #f0f2f5;
}
.progress-stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.progress-stat .stat-label { font-size: 12px; color: #909399; }
.progress-stat .stat-value {
  font-size: 22px;
  font-weight: 700;
  color: #2c3e50;
  font-family: 'SF Mono', Consolas, monospace;
}
@media (max-width: 1280px) {
  .progress-block .progress-stats { grid-template-columns: repeat(2, 1fr) !important; }
}
</style>
