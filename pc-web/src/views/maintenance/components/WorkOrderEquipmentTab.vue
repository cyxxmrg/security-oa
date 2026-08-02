<template>
  <div class="info-grid">
    <div class="info-item">
      <div class="info-label">品牌</div>
      <div class="info-value">{{ wo.equipment_brand || '—' }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">型号</div>
      <div class="info-value">{{ wo.equipment_model || '—' }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">序列号</div>
      <div class="info-value">
        <code v-if="wo.serial_no">{{ wo.serial_no }}</code>
        <span v-else>—</span>
      </div>
    </div>
    <div class="info-item full">
      <div class="info-label">故障描述</div>
      <div class="info-value description">{{ wo.fault_description }}</div>
    </div>
    <div class="info-item" v-if="wo.scheduled_at">
      <div class="info-label">预约时间</div>
      <div class="info-value">{{ formatDate(wo.scheduled_at) }}</div>
    </div>
    <div class="info-item" v-if="wo.started_at">
      <div class="info-label">开始时间</div>
      <div class="info-value">{{ formatDate(wo.started_at) }}</div>
    </div>
    <div class="info-item" v-if="wo.completed_at">
      <div class="info-label">完成时间</div>
      <div class="info-value">{{ formatDate(wo.completed_at) }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  wo: Record<string, unknown>
  formatDate: (s: string) => string
}>()
</script>

<style scoped>
.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  &.full { grid-column: 1 / -1; }
}
.info-label { font-size: 12px; color: #909399; }
.info-value { font-size: 14px; color: #303133; }
.info-value.description {
  background: #f5f7fa;
  padding: 12px;
  border-radius: 4px;
  white-space: pre-wrap;
}

@media (max-width: 768px) {
  .info-grid { grid-template-columns: 1fr; }
}
</style>
