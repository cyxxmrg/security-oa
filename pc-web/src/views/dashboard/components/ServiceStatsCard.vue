<script setup lang="ts">
/**
 * ServiceStatsCard — 售后工单统计 (v0.3.14 C1)
 */
defineProps<{
  data: {
    todayNew: number
    processing: number
    todayDone: number
    monthDone: number
  }
}>()

const emit = defineEmits<{
  (e: 'viewAll'): void
}>()
</script>

<template>
  <el-card shadow="never" class="service-stats-card">
    <template #header>
      <div class="card-header">
        <span class="card-title">售后工单统计</span>
        <el-button text type="primary" @click="emit('viewAll')">详情</el-button>
      </div>
    </template>
    <div class="service-stats">
      <div class="service-stat-item">
        <span class="ss-label">今日新增</span>
        <span class="ss-value success">{{ data.todayNew }}</span>
      </div>
      <div class="service-stat-item">
        <span class="ss-label">处理中</span>
        <span class="ss-value warning">{{ data.processing }}</span>
      </div>
      <div class="service-stat-item">
        <span class="ss-label">今日完成</span>
        <span class="ss-value primary">{{ data.todayDone }}</span>
      </div>
      <div class="service-stat-item">
        <span class="ss-label">本月完成</span>
        <span class="ss-value info">{{ data.monthDone }}</span>
      </div>
    </div>
  </el-card>
</template>

<style scoped>
.card-header {
  display: flex; justify-content: space-between; align-items: center;
  width: 100%; height: 48px;
}
.card-title {
  font-size: 15px; font-weight: 600; color: #2c3e50;
  display: flex; align-items: center; gap: 8px;
}
.card-title::before {
  content: ''; display: inline-block;
  width: 3px; height: 14px;
  background: linear-gradient(180deg, #0C447C 0%, #1D9E75 100%);
  border-radius: 2px; flex-shrink: 0;
}
.service-stats {
  display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
  padding: 4px 0;
}
.service-stat-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 12px; border-radius: 6px; background: #f7f9fc;
  transition: background 0.2s;
}
.service-stat-item:hover { background: #ebeef5; }
.ss-label { font-size: 12px; color: #606266; }
.ss-value { font-size: 18px; font-weight: 700; line-height: 1; }
.ss-value.success { color: #1D9E75; }
.ss-value.warning { color: #BA7517; }
.ss-value.primary { color: #185FA5; }
.ss-value.info    { color: #909399; }
</style>
