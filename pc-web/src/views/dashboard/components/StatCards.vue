<script setup lang="ts">
/**
 * StatCards — 顶部 4 张统计卡 (v0.3.14 C1)
 * Stats: [{label, value, color, trend, icon, path}]
 * 单击卡片跳转到对应模块
 */
import { Top, Bottom } from '@element-plus/icons-vue'

defineProps<{
  stats: Array<{
    label: string
    value: string | number
    color: string
    trend: number
    icon: string
    path: string
  }>
}>()

const emit = defineEmits<{
  (e: 'jump', path: string): void
}>()
</script>

<template>
  <el-row :gutter="16" class="stat-row">
    <el-col :span="6" v-for="stat in stats" :key="stat.label">
      <div
        class="stat-card"
        :style="{ borderTop: `3px solid ${stat.color}` }"
        @click="emit('jump', stat.path)"
      >
        <div class="stat-icon" :style="{ background: stat.color + '15', color: stat.color }">
          <el-icon :size="24"><component :is="stat.icon" /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
        <div class="stat-trend" :class="stat.trend > 0 ? 'up' : 'down'">
          <el-icon v-if="stat.trend > 0"><Top /></el-icon>
          <el-icon v-else><Bottom /></el-icon>
          {{ Math.abs(stat.trend) }}%
        </div>
      </div>
    </el-col>
  </el-row>
</template>

<style scoped>
.stat-row { margin-bottom: 16px; }
.stat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 18px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
  position: relative;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.stat-icon {
  width: 48px; height: 48px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-info { flex: 1; min-width: 0; }
.stat-value { font-size: 22px; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 12px; color: #909399; margin-top: 2px; }
.stat-trend {
  display: flex; align-items: center; gap: 2px;
  font-size: 12px; font-weight: 500;
  padding: 2px 8px; border-radius: 10px;
}
.stat-trend.up { color: #1D9E75; background: rgba(29, 158, 117, 0.1); }
.stat-trend.down { color: #A32D2D; background: rgba(163, 45, 45, 0.1); }
</style>
