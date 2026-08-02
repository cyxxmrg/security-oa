<script setup lang="ts">
/**
 * AttendanceCard — 今日考勤 5 宫格 (v0.3.14 C1)
 */
defineProps<{
  data: {
    normal: number
    late: number
    absent: number
    field: number
    leave: number
  }
}>()

const emit = defineEmits<{
  (e: 'viewAll'): void
}>()
</script>

<template>
  <el-card shadow="never">
    <template #header>
      <div class="card-header">
        <span class="card-title">今日考勤</span>
        <el-button text type="primary" @click="emit('viewAll')">考勤管理</el-button>
      </div>
    </template>
    <div class="attendance-overview">
      <div class="att-item">
        <div class="att-value success">{{ data.normal }}</div>
        <div class="att-label">正常出勤</div>
      </div>
      <div class="att-item">
        <div class="att-value warning">{{ data.late }}</div>
        <div class="att-label">迟到</div>
      </div>
      <div class="att-item">
        <div class="att-value danger">{{ data.absent }}</div>
        <div class="att-label">缺勤</div>
      </div>
      <div class="att-item">
        <div class="att-value info">{{ data.field }}</div>
        <div class="att-label">外勤</div>
      </div>
      <div class="att-item">
        <div class="att-value">{{ data.leave }}</div>
        <div class="att-label">请假</div>
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
.attendance-overview {
  display: grid; grid-template-columns: repeat(5, 1fr);
  gap: 12px; padding: 8px 0;
}
.att-item {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 12px 4px; border-radius: 6px;
  transition: background 0.2s;
}
.att-item:hover { background: #f5f7fa; }
.att-value {
  font-size: 22px; font-weight: 700; color: #303133; line-height: 1.2;
}
.att-value.success { color: #1D9E75; }
.att-value.warning { color: #BA7517; }
.att-value.danger  { color: #A32D2D; }
.att-value.info    { color: #185FA5; }
.att-label { font-size: 12px; color: #909399; }
</style>
