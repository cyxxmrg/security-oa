<template>
  <div v-if="summary" class="summary-cards">
    <!-- 总览卡 -->
    <el-alert
      :title="`总预算 ¥${formatMoney(summary.total_budget)} | 实际 ¥${formatMoney(summary.total_actual)} | 余额 ¥${formatMoney(summary.balance)} | 使用率 ${ratePercent(summary.usage_rate)}%`"
      :type="alertType"
      :closable="false"
      show-icon
      class="overview-alert"
    />

    <!-- 4 大分类卡 -->
    <div class="category-grid">
      <el-card
        v-for="cat in categories"
        :key="cat.key"
        :body-style="{ padding: '16px' }"
        shadow="hover"
        class="cat-card"
      >
        <div class="cat-header">
          <span class="cat-title" :style="{ color: cat.color }">{{ cat.label }}</span>
          <span class="cat-rate" :style="{ color: getRateColor(categoryOf(cat.key).usage_rate) }">
            {{ ratePercent(categoryOf(cat.key).usage_rate) }}%
          </span>
        </div>
        <div class="cat-amounts">
          <div class="amount-row">
            <span class="label">预算</span>
            <span class="value">¥ {{ formatMoney(categoryOf(cat.key).budget) }}</span>
          </div>
          <div class="amount-row">
            <span class="label">实际</span>
            <span class="value">¥ {{ formatMoney(categoryOf(cat.key).actual) }}</span>
          </div>
          <div class="amount-row">
            <span class="label">余额</span>
            <span class="value" :style="{ color: categoryOf(cat.key).balance < 0 ? '#f56c6c' : '#67c23a' }">
              ¥ {{ formatMoney(categoryOf(cat.key).balance) }}
            </span>
          </div>
        </div>
        <el-progress
          :percentage="ratePercent(categoryOf(cat.key).usage_rate)"
          :color="getRateColor(categoryOf(cat.key).usage_rate)"
          :stroke-width="8"
          :show-text="false"
          class="cat-progress"
        />
      </el-card>
    </div>

    <!-- 实际成本来源明细 -->
    <el-card shadow="never" class="source-card">
      <template #header>
        <span style="font-weight:600">实际成本来源明细</span>
      </template>
      <el-row :gutter="12">
        <el-col v-for="src in sources" :key="src.key" :span="Math.floor(24 / sources.length)">
          <div class="source-item">
            <div class="source-label">{{ src.label }}</div>
            <div class="source-value">¥ {{ formatMoney(sourceOf(src.key)) }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- 底部操作按钮 -->
    <div class="action-bar">
      <el-button :icon="Refresh" type="primary" @click="emit('revise', summary)">修订</el-button>
      <el-button :icon="Document" @click="emit('view-detail', summary)">明细</el-button>
      <el-button :icon="Clock" @click="emit('view-history', summary)">历史</el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Refresh, Document, Clock } from '@element-plus/icons-vue'
import { BudgetSummary, CategoryAmount } from '../types'

// categoryOf 计算后的固定数值形状 (与 CategoryAmount 区分, 此处字段已 Number 化)
interface CategoryComputed {
  budget: number
  actual: number
  balance: number
  usage_rate: number
}

const props = defineProps<{
  summary: BudgetSummary
}>()

const emit = defineEmits<{
  (e: 'revise', summary: BudgetSummary): void
  (e: 'view-detail', summary: BudgetSummary): void
  (e: 'view-history', summary: BudgetSummary): void
}>()

// 4 大分类：material/labor/outsource/other
const categories = [
  { key: 'material',  label: '材料费', color: '#0C447C' },
  { key: 'labor',     label: '人工费', color: '#1D9E75' },
  { key: 'outsource', label: '外包费', color: '#E6A23C' },
  { key: 'other',     label: '其他费', color: '#909399' },
]

// 实际成本来源
const sources = [
  { key: 'purchase_in',     label: '采购入库' },
  { key: 'stock_out',       label: '领料出库' },
  { key: 'expense_labor',   label: '报销-人工' },
  { key: 'expense_outsource', label: '报销-外包' },
  { key: 'expense_other',   label: '报销-其他' },
]

const categoryOf = (key: string): CategoryComputed => {
  const cats = props.summary?.categories
  const c = (cats?.[key] ?? (props.summary?.[key] as CategoryAmount | undefined) ?? {}) as CategoryAmount
  return {
    budget:    Number(c.budget    || c.budget_amount || 0),
    actual:    Number(c.actual    || c.actual_amount || 0),
    balance:   Number(c.balance   ?? (Number(c.budget || 0) - Number(c.actual || 0))),
    usage_rate: Number(c.usage_rate ?? (Number(c.budget || 0) > 0 ? Number(c.actual || 0) / Number(c.budget || 0) : 0)),
  }
}

const sourceOf = (key: string) => {
  return Number(props.summary?.actual_sources?.[key] || props.summary?.[key] || 0)
}

const formatMoney = (n?: number | string | null) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const ratePercent = (rate?: number | string | null) => {
  const r = Number(rate || 0)
  return Math.min(Math.max(Math.round(r * 100), 0), 100)
}
const getRateColor = (rate?: number | string | null): string => {
  const r = Number(rate || 0)
  if (r >= 1.0) return '#f56c6c'
  if (r >= 0.9) return '#e6a23c'
  return '#67c23a'
}

const alertType = computed<'success' | 'warning' | 'error'>(() => {
  const r = Number(props.summary?.usage_rate || 0)
  if (r >= 1.0) return 'error'
  if (r >= 0.9) return 'warning'
  return 'success'
})
</script>

<style lang="scss" scoped>
.summary-cards { display: flex; flex-direction: column; gap: 12px; }
.overview-alert { font-weight: 500; }
.category-grid {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
}
.cat-card {
  .cat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
  .cat-title { font-size: 14px; font-weight: 600; }
  .cat-rate  { font-size: 18px; font-weight: 700; }
  .cat-amounts { margin-bottom: 10px; }
  .amount-row {
    display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px;
    .label { color: #909399; }
    .value { color: #303133; font-weight: 500; }
  }
  .cat-progress { margin-top: 4px; }
}
.source-card {
  .source-item {
    background: #f5f7fa; border-radius: 6px; padding: 12px; text-align: center;
    .source-label { color: #909399; font-size: 12px; margin-bottom: 6px; }
    .source-value { color: #0C447C; font-size: 16px; font-weight: 600; }
  }
}
.action-bar {
  display: flex; justify-content: flex-end; gap: 8px;
  background: #fff; padding: 12px 16px; border-radius: 6px;
  border: 1px solid #ebeef5;
}
</style>
