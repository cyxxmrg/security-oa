<template>
  <div>
    <div v-if="wo.status === 'resolved' || wo.total_cost > 0" class="cost-summary">
      <div class="cost-row">
        <span>服务费</span>
        <span class="amount">¥ {{ wo.service_fee }}</span>
      </div>
      <div class="cost-row">
        <span>配件费</span>
        <span class="amount">¥ {{ wo.parts_cost }}</span>
      </div>
      <div class="cost-row total">
        <span>合计</span>
        <span class="amount">¥ {{ wo.total_cost }}</span>
      </div>
    </div>
    <el-empty v-else description="工单未完成, 暂无费用" />
    <div v-if="wo.result_notes" class="result-notes">
      <div class="info-label">处理结果</div>
      <div class="info-value">{{ wo.result_notes }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  wo: Record<string, unknown>
}>()
</script>

<style scoped>
.cost-summary {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  background: #f5f7fa;
  border-radius: 6px;
}
.cost-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
  border-bottom: 1px solid #ebeef5;
  &.total { font-weight: 600; font-size: 16px; color: #F56C6C; }
  .amount { font-family: 'Courier New', monospace; }
}
.info-label { font-size: 12px; color: #909399; }
.info-value { font-size: 14px; color: #303133; }
</style>
