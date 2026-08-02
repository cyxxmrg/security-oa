<template>
  <div class="stat-row" v-loading="statsLoading">
    <div class="stat-card total" :style="{ borderLeftColor: '#0C447C' }">
      <div class="stat-info">
        <div class="stat-value" :style="{ color: '#0C447C' }">{{ stats.total }}</div>
        <div class="stat-label">累计验收（次）</div>
      </div>
      <div class="stat-icon" :style="{ background: '#E6F1FB', color: '#0C447C' }">
        <el-icon :size="24"><DataAnalysis /></el-icon>
      </div>
    </div>

    <div class="stat-card pass" :style="{ borderLeftColor: '#1D9E75' }">
      <div class="stat-info">
        <div class="stat-value" :style="{ color: '#1D9E75' }">{{ stats.pass }}</div>
        <div class="stat-label">合格数</div>
      </div>
      <div class="stat-icon" :style="{ background: '#E1F5EE', color: '#1D9E75' }">
        <el-icon :size="24"><CircleCheck /></el-icon>
      </div>
    </div>

    <div class="stat-card fail" :style="{ borderLeftColor: '#A32D2D' }">
      <div class="stat-info">
        <div class="stat-value" :style="{ color: '#A32D2D' }">{{ stats.fail }}</div>
        <div class="stat-label">不合格数</div>
      </div>
      <div class="stat-icon" :style="{ background: '#FCEBEB', color: '#A32D2D' }">
        <el-icon :size="24"><CircleClose /></el-icon>
      </div>
    </div>

    <div class="stat-card rectify" :style="{ borderLeftColor: '#BA7517' }">
      <div class="stat-info">
        <div class="stat-value" :style="{ color: passRateColor }">
          {{ passRateText }}<span class="rate-unit">%</span>
        </div>
        <div class="stat-label">合格率</div>
      </div>
      <div class="stat-icon" :style="{ background: '#FAEEDA', color: '#BA7517' }">
        <el-icon :size="24"><TrendCharts /></el-icon>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { DataAnalysis, CircleCheck, CircleClose, TrendCharts } from '@element-plus/icons-vue'
import type { InspectionStats } from './types'

// v0.3.25 抽自 process/InspectionList.vue:3-46
const props = defineProps<{
  stats: InspectionStats
  statsLoading: boolean
}>()

const passRate = computed(() => {
  if (!props.stats.total) return 0
  return Math.round((props.stats.pass / props.stats.total) * 100)
})
const passRateText = computed(() => passRate.value.toString())
const passRateColor = computed(() => {
  const r = passRate.value
  if (r >= 80) return '#1D9E75'
  if (r >= 50) return '#BA7517'
  return '#A32D2D'
})
</script>

<style lang="scss" scoped>
.stat-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}
.stat-card {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 18px;
  background: #fff;
  border-radius: 8px;
  border-left: 3px solid;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .stat-info { display: flex; flex-direction: column; }
  .stat-value { font-size: 26px; font-weight: 700; line-height: 1.2; }
  .stat-label { font-size: 12px; color: #909399; margin-top: 2px; }
  .stat-icon {
    width: 48px; height: 48px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
  }
  .rate-unit { font-size: 14px; color: #909399; margin-left: 2px; }
}
</style>
