<template>
  <el-row :gutter="16" class="stat-row">
    <el-col :span="8" v-for="(stat, idx) in stats" :key="idx">
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
  </el-row>
</template>

<script setup lang="ts">
import type { ResignationStat } from './types'
defineProps<{ stats: ResignationStat[] }>()
</script>

<style lang="scss" scoped>
.stat-row { margin: 0 !important; row-gap: 16px; }
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

  .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .stat-info { flex: 1; }
  .stat-value { font-size: 28px; font-weight: 700; line-height: 1.2; }
  .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
}
</style>
