<template>
  <div class="stat-row">
    <div class="stat-card stat-card--total">
      <div class="stat-card__icon"><el-icon><Money /></el-icon></div>
      <div class="stat-card__body">
        <div class="stat-card__label">应收总额</div>
        <div class="stat-card__value">¥{{ formatMoney(totals.amount) }}</div>
        <div class="stat-card__sub">共 {{ totals.count }} 笔</div>
      </div>
    </div>
    <div class="stat-card stat-card--received">
      <div class="stat-card__icon"><el-icon><CircleCheckFilled /></el-icon></div>
      <div class="stat-card__body">
        <div class="stat-card__label">已收款</div>
        <div class="stat-card__value">¥{{ formatMoney(totals.received) }}</div>
        <div class="stat-card__sub">回款率 {{ totals.receivedRate }}%</div>
      </div>
    </div>
    <div class="stat-card stat-card--pending">
      <div class="stat-card__icon"><el-icon><Clock /></el-icon></div>
      <div class="stat-card__body">
        <div class="stat-card__label">待收款</div>
        <div class="stat-card__value">¥{{ formatMoney(totals.remaining) }}</div>
        <div class="stat-card__sub">未到账金额</div>
      </div>
    </div>
    <div class="stat-card stat-card--overdue">
      <div class="stat-card__icon"><el-icon><WarningFilled /></el-icon></div>
      <div class="stat-card__body">
        <div class="stat-card__label">逾期金额</div>
        <div class="stat-card__value">¥{{ formatMoney(totals.overdue) }}</div>
        <div class="stat-card__sub">{{ totals.overdueCount }} 笔逾期</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Money, CircleCheckFilled, Clock, WarningFilled } from '@element-plus/icons-vue'
import type { ReceivableTotals } from './types'
import { formatMoney } from './types'

// v0.3.25 抽自 finance/Receivable.vue:3-37
defineProps<{
  totals: ReceivableTotals
}>()
</script>

<style lang="scss" scoped>
.stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.stat-card {
  display: flex; align-items: center; gap: 14px;
  padding: 18px 20px;
  background: #fff; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  border-left: 3px solid;
  &--total    { border-left-color: #0C447C; }
  &--received { border-left-color: #1D9E75; }
  &--pending  { border-left-color: #BA7517; }
  &--overdue  { border-left-color: #A32D2D; }
  &__icon {
    width: 48px; height: 48px; border-radius: 8px;
    background: rgba(12, 68, 124, 0.06);
    display: flex; align-items: center; justify-content: center;
    color: #0C447C; font-size: 24px;
  }
  &--received &__icon { background: rgba(29, 158, 117, 0.06); color: #1D9E75; }
  &--pending  &__icon { background: rgba(186, 117, 23, 0.06); color: #BA7517; }
  &--overdue  &__icon { background: rgba(163, 45, 45, 0.06); color: #A32D2D; }
  &__body { display: flex; flex-direction: column; }
  &__label { font-size: 12px; color: #909399; }
  &__value { font-size: 22px; font-weight: 700; color: #303133; line-height: 1.3; }
  &__sub { font-size: 11px; color: #909399; }
}
</style>
