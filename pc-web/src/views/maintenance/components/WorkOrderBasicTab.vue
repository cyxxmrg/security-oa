<template>
  <div class="info-grid">
    <div class="info-item">
      <div class="info-label">客户</div>
      <div class="info-value">{{ wo.customer_name || '—' }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">项目</div>
      <div class="info-value">{{ wo.project_name || '—' }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">联系人</div>
      <div class="info-value">{{ wo.contact_name || '—' }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">联系电话</div>
      <div class="info-value">{{ wo.contact_phone || '—' }}</div>
    </div>
    <div class="info-item" v-if="wo.address">
      <div class="info-label">地址</div>
      <div class="info-value">{{ wo.address }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">优先级</div>
      <div class="info-value">
        <el-tag :type="wo.priority_color" effect="dark">{{ wo.priority_label }}</el-tag>
      </div>
    </div>
    <div class="info-item">
      <div class="info-label">服务类型</div>
      <div class="info-value">{{ wo.service_type || '上门' }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">创建时间</div>
      <div class="info-value">{{ formatDate(wo.created_at) }}</div>
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

@media (max-width: 768px) {
  .info-grid { grid-template-columns: 1fr; }
}
</style>
