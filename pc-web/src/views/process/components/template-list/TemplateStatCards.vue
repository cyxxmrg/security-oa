<template>
  <div class="stat-cards">
    <div class="stat-card stat-card--primary">
      <div class="stat-card__icon"><el-icon :size="32"><Files /></el-icon></div>
      <div class="stat-card__info">
        <span class="stat-card__label">模板总数</span>
        <span class="stat-card__value">{{ stats.total }}</span>
        <span class="stat-card__rate">覆盖 {{ stats.industryCount }} 种项目类型</span>
      </div>
    </div>
    <div class="stat-card stat-card--success">
      <div class="stat-card__icon"><el-icon :size="32"><CircleCheckFilled /></el-icon></div>
      <div class="stat-card__info">
        <span class="stat-card__label">启用中</span>
        <span class="stat-card__value">{{ stats.active }}</span>
        <span class="stat-card__rate">占比 {{ activeRate }}%</span>
      </div>
    </div>
    <div class="stat-card stat-card--warning">
      <div class="stat-card__icon"><el-icon :size="32"><OfficeBuilding /></el-icon></div>
      <div class="stat-card__info">
        <span class="stat-card__label">已配置类型</span>
        <span class="stat-card__value">{{ stats.industryCount }}</span>
        <span class="stat-card__rate">支持五大项目类型</span>
      </div>
    </div>
    <div class="stat-card stat-card--info">
      <div class="stat-card__icon"><el-icon :size="32"><Calendar /></el-icon></div>
      <div class="stat-card__info">
        <span class="stat-card__label">今日新增</span>
        <span class="stat-card__value">{{ stats.todayNew }}</span>
        <span class="stat-card__rate">{{ todayLabel }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Files, CircleCheckFilled, OfficeBuilding, Calendar } from '@element-plus/icons-vue'
import type { TemplateStats } from './types'

// v0.3.25 抽自 process/TemplateList.vue:15-49
const props = defineProps<{
  stats: TemplateStats
}>()

const activeRate = computed(() => {
  if (!props.stats.total) return 0
  return Math.round((props.stats.active / props.stats.total) * 100)
})

const todayLabel = computed(() => {
  const d = new Date()
  return `${d.getMonth() + 1}月${d.getDate()}日`
})
</script>

<style lang="scss" scoped>
.stat-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}
.stat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 18px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  border-left: 3px solid;
  &--primary { border-left-color: #0C447C; .stat-card__icon { color: #0C447C; } }
  &--success { border-left-color: #1D9E75; .stat-card__icon { color: #1D9E75; } }
  &--warning { border-left-color: #BA7517; .stat-card__icon { color: #BA7517; } }
  &--info    { border-left-color: #534AB7; .stat-card__icon { color: #534AB7; } }
  &__icon {
    width: 48px; height: 48px;
    border-radius: 8px;
    background: rgba(12, 68, 124, 0.06);
    display: flex; align-items: center; justify-content: center;
  }
  &__info { display: flex; flex-direction: column; }
  &__label { font-size: 12px; color: #909399; }
  &__value { font-size: 22px; font-weight: 700; color: #303133; line-height: 1.2; margin: 2px 0; }
  &__rate { font-size: 11px; color: #909399; }
}
</style>
