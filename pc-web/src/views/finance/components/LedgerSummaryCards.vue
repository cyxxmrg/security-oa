<template>
  <el-card shadow="never" class="summary-card">
    <el-row :gutter="16">
      <el-col :span="6"><SummaryStat label="累计应付" :value="summary.total_amount" color="#0C447C" /></el-col>
      <el-col :span="6"><SummaryStat label="已付款" :value="summary.total_paid" color="#67c23a" /></el-col>
      <el-col :span="6"><SummaryStat label="未付余额" :value="summary.total_balance" color="#e6a23c" /></el-col>
      <el-col :span="6"><SummaryStat label="应付单数 / 逾期" :value="`${summary.payable_count} / ${summary.overdue_count}`" color="#f56c6c" /></el-col>
    </el-row>
  </el-card>
</template>

<script setup lang="ts">
import { h } from 'vue'
import type { LedgerSummaryLike } from '../types'

defineProps<{
  summary: LedgerSummaryLike
}>()

interface StatProps {
  label: string
  value: string | number
  color: string
}

const SummaryStat = {
  props: ['label', 'value', 'color'],
  setup(p: StatProps) {
    return () => h('div', { style: 'text-align:center;padding:8px 0' }, [
      h('div', { style: 'color:#999;font-size:12px' }, p.label),
      h('div', { style: `font-size:22px;font-weight:700;color:${p.color}` }, p.value),
    ])
  },
}
</script>

<style scoped>
.summary-card { margin-bottom: 16px; }
</style>
