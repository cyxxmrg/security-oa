<template>
  <div class="stats-row">
    <div
      v-for="s in cards"
      :key="s.key"
      class="stat-card"
      @click="emit('click', s.key)"
    >
      <div class="stat-icon" :style="{ background: s.bg, color: s.color }">
        <el-icon :size="20"><component :is="s.icon" /></el-icon>
      </div>
      <div class="stat-info">
        <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
        <div class="stat-label">{{ s.label }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { DataLine, CircleCheck, CircleClose, Warning } from '@element-plus/icons-vue'
import type { InstanceStat, InstanceStats, Instance } from './types'

// v0.3.19 抽自 process/InstanceList.vue:396-401
const props = defineProps<{
  stats: InstanceStats
  activeKey?: string
}>()

const emit = defineEmits<{
  (e: 'click', key: keyof InstanceStats): void
}>()

const cards = computed<InstanceStat[]>(() => [
  { key: 'in_progress', label: '进行中', value: props.stats.in_progress, color: '#0C447C', bg: 'rgba(12, 68, 124, 0.08)', icon: DataLine },
  { key: 'accepted',    label: '已验收', value: props.stats.accepted,    color: '#1D9E75', bg: 'rgba(29, 158, 117, 0.08)', icon: CircleCheck },
  { key: 'rejected',    label: '已驳回', value: props.stats.rejected,    color: '#A32D2D', bg: 'rgba(163, 45, 45, 0.08)', icon: CircleClose },
  { key: 'overdue',     label: '超期',   value: props.stats.overdue,     color: '#D85A30', bg: 'rgba(216, 90, 48, 0.08)',  icon: Warning },
])
</script>

<style lang="scss" scoped>
.stats-row {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 12px; margin-bottom: 18px;
  .stat-card {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; border-radius: 8px;
    background: #fff; border: 1px solid #ebeef5;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    &:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
      border-color: transparent;
    }
    .stat-icon {
      width: 40px; height: 40px;
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
    }
    .stat-info { flex: 1; }
    .stat-value {
      font-size: 22px; font-weight: 700; line-height: 1.1;
    }
    .stat-label {
      font-size: 12px; color: #909399; margin-top: 2px;
    }
  }
}
</style>
