<script setup lang="ts">
/**
 * CustomerStatsCards — 客户 4 张统计卡 (v0.3.14 C2)
 */
import type { Component } from 'vue'

defineProps<{
  stats: { total: number; vip: number; project_total: number; new_this_month: number }
  // 让父组件传 component
  iconMap: Record<string, Component>
}>()
</script>

<template>
  <el-row :gutter="16" class="stat-row">
    <el-col :span="6" v-for="(s, i) in cards" :key="i">
      <div class="stat-card" :style="{ borderLeft: `3px solid ${s.color}` }">
        <div class="stat-icon" :style="{ background: s.bg, color: s.color }">
          <el-icon :size="22"><component :is="iconMap[s.iconKey]" /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
          <div class="stat-label">{{ s.label }}</div>
        </div>
      </div>
    </el-col>
  </el-row>
</template>

<script lang="ts">
import { computed } from 'vue'
export default {
  computed: {
    cards() {
      return [
        { label: '客户总数',   value: this.stats.total,         color: '#0C447C', bg: 'rgba(12,68,124,0.1)',  iconKey: 'OfficeBuilding' },
        { label: 'VIP 客户',  value: this.stats.vip,           color: '#BA7517', bg: 'rgba(186,117,23,0.1)', iconKey: 'User' },
        { label: '合作中项目', value: this.stats.project_total, color: '#1D9E75', bg: 'rgba(29,158,117,0.1)', iconKey: 'Money' },
        { label: '本月新增',  value: this.stats.new_this_month, color: '#534AB7', bg: 'rgba(83,74,183,0.1)',  iconKey: 'TrendCharts' },
      ]
    }
  }
}
</script>

<style scoped>
.stat-row { margin-bottom: 16px; }
.stat-card {
  display: flex; align-items: center; gap: 12px;
  padding: 16px 18px; background: #fff; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.stat-icon {
  width: 48px; height: 48px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-info { flex: 1; min-width: 0; }
.stat-value { font-size: 22px; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 12px; color: #909399; margin-top: 2px; }
</style>
