<template>
  <el-row :gutter="16" class="mb-16" v-loading="loading">
    <el-col :span="8">
      <div class="stat-card">
        <div class="stat-value primary">¥{{ formatMoney(stats.totalRevenue) }}</div>
        <div class="stat-label">本月营收</div>
      </div>
    </el-col>
    <el-col :span="8">
      <div class="stat-card">
        <div class="stat-value warning">¥{{ formatMoney(stats.totalReceivable) }}</div>
        <div class="stat-label">未收应收款</div>
      </div>
    </el-col>
    <el-col :span="8">
      <div class="stat-card">
        <div class="stat-value danger">¥{{ formatMoney(stats.totalPayable) }}</div>
        <div class="stat-label">未付应付款</div>
      </div>
    </el-col>
  </el-row>
</template>

<script setup lang="ts">
import { formatMoney } from './format'
import type { FinanceOverview } from '../../types'

defineProps<{ loading: boolean; stats: FinanceOverview }>()
</script>

<style lang="scss" scoped>
.mb-16 { margin-bottom: 16px; }
.stat-card {
  background: #fff;
  border-radius: 8px;
  padding: 24px 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
  text-align: center;
}
.stat-value { font-size: 28px; font-weight: 700; }
.stat-value.primary { color: #0C447C; }
.stat-value.warning { color: #BA7517; }
.stat-value.danger  { color: #A32D2D; }
.stat-label { font-size: 13px; color: #909399; margin-top: 8px; }
</style>
