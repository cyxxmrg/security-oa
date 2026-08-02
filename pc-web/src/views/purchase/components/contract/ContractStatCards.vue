<template>
  <div class="stats-row">
    <div class="stat-card" v-for="s in cards" :key="s.label" :style="{ borderColor: s.color }">
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
import { Document, Money, CircleCheck } from '@element-plus/icons-vue'
import { formatMoney } from './types'

interface Stats { total: number; signed: number; shipping: number; total_amount: number }

const props = defineProps<{ stats: Stats }>()

const cards = computed(() => [
  { label: '合同总数',   value: props.stats.total,                          icon: Document,    color: '#0C447C' },
  { label: '已签订',     value: props.stats.signed,                         icon: CircleCheck, color: '#1D9E75' },
  { label: '合同总金额', value: '¥ ' + formatMoney(props.stats.total_amount), icon: Money,       color: '#BA7517' },
  { label: '运输中',     value: props.stats.shipping,                       icon: Money,       color: '#534AB7' },
])
</script>

<style lang="scss" scoped>
.stats-row { display: flex; gap: 16px; margin-bottom: 16px; }
.stat-card {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #fff;
  border-radius: 8px;
  border-left: 3px solid #909399;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
}
.stat-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.stat-value { font-size: 22px; font-weight: 700; }
.stat-label { font-size: 12px; color: #909399; }
</style>
