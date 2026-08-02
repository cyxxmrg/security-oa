<template>
  <div class="stats-row">
    <div
      v-for="s in cards"
      :key="s.label"
      class="stat-card"
      :style="{ borderColor: s.color }"
    >
      <div class="stat-icon" :style="{ background: s.color + '15', color: s.color }">
        <el-icon :size="20"><component :is="s.icon" /></el-icon>
      </div>
      <div>
        <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
        <div class="stat-label">{{ s.label }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Document, Bell, Warning, Tickets } from '@element-plus/icons-vue'
import type { Requirement } from './types'

// v0.3.23 抽自 purchase/Requirement.vue:40-50 + 376-381
const props = defineProps<{
  stats: { pending: number; approved: number; rejected: number; cancelled: number; total: number }
  list: Requirement[]
}>()

const cards = computed(() => [
  { label: '需求总数', value: props.stats.total, icon: Document, color: '#0C447C' },
  { label: '待审核', value: props.stats.pending, icon: Bell, color: '#BA7517' },
  { label: '紧急需求', value: props.list.filter((r) => r.priority === 'urgent').length, icon: Warning, color: '#A32D2D' },
  { label: '关联项目数', value: new Set(props.list.map((r) => r.project_id).filter(Boolean)).size, icon: Tickets, color: '#1D9E75' },
])
</script>

<style lang="scss" scoped>
.stats-row {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 12px; margin-bottom: 16px;
}
.stat-card {
  display: flex; align-items: center; gap: 12px; padding: 12px 16px;
  background: #fff; border: 1px solid #ebeef5; border-left: 4px solid;
  border-radius: 6px; transition: all 0.2s;
  &:hover { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); }
  .stat-value { font-size: 20px; font-weight: 700; line-height: 1.2; }
  .stat-label { font-size: 12px; color: #909399; margin-top: 2px; }
}
</style>
