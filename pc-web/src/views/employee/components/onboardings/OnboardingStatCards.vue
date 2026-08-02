<template>
  <el-row :gutter="16" class="stat-row">
    <el-col :span="6" v-for="(stat, idx) in stats" :key="idx">
      <div class="stat-card" :style="{ borderTop: `3px solid ${stat.color}` }">
        <div class="stat-icon" :style="{ background: stat.color + '15', color: stat.color }">
          <el-icon :size="24"><component :is="stat.icon" /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
      </div>
    </el-col>
    <el-col :span="6">
      <div class="stat-card placeholder-card">
        <div class="stat-icon" style="background: #f0f4fa; color: #0C447C;">
          <el-icon :size="24"><Promotion /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-label" style="font-size: 14px; color: #606266; font-weight: 500;">
            快捷操作
          </div>
          <div style="margin-top: 6px; display: flex; gap: 6px;">
            <el-button size="small" type="primary" @click="emit('create')">入职办理</el-button>
            <el-button size="small" @click="emit('export')">导出</el-button>
          </div>
        </div>
      </div>
    </el-col>
  </el-row>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { Promotion, User, Clock, BellFilled } from '@element-plus/icons-vue'

// v0.3.22 抽自 employee/Onboardings.vue:4-32
const stats = reactive([
  { label: '在职总数', value: '0', icon: User, color: '#0C447C' },
  { label: '合同 30 天内到期', value: '0', icon: Clock, color: '#BA7517' },
  { label: '试用期 7 天内到期', value: '0', icon: BellFilled, color: '#534AB7' },
])
defineExpose({ stats })

const emit = defineEmits<{
  (e: 'create'): void
  (e: 'export'): void
}>()
</script>

<style lang="scss" scoped>
.stat-row {
  margin: 0 !important;
  row-gap: 16px;
}
.stat-row :deep(.el-col) { padding: 0 8px; }
.stat-row :deep(.el-col:first-child) { padding-left: 0; }
.stat-row :deep(.el-col:last-child)  { padding-right: 0; }

.stat-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  position: relative;
  height: 96px;

  &.placeholder-card { border-top: 3px solid #0C447C; }

  .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .stat-info { flex: 1; min-width: 0; }
  .stat-value {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
    font-family: 'DIN Pro', -apple-system, BlinkMacSystemFont, monospace;
    font-variant-numeric: tabular-nums;
  }
  .stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
  }
}
</style>
