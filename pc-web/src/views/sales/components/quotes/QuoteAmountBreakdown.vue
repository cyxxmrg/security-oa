<template>
  <div class="amount-breakdown">
    <div class="ab-row">
      <span>小计 (本地预览)</span>
      <span>¥ {{ formatMoney(localTotals.subtotal) }}</span>
    </div>
    <div class="ab-row">
      <span>折扣 ({{ form.discount_rate }}%)</span>
      <span class="text-danger">- ¥ {{ formatMoney(localTotals.discount_amount) }}</span>
    </div>
    <div class="ab-row">
      <span>税额 ({{ form.tax_rate }}%)</span>
      <span>¥ {{ formatMoney(localTotals.tax_amount) }}</span>
    </div>
    <div class="ab-row total">
      <span>含税总价 (本地预览)</span>
      <span>¥ {{ formatMoney(localTotals.total) }}</span>
    </div>
    <div v-if="serverTotalDiff" class="ab-row" :style="{ color: '#E6A23C' }">
      <span>后端保存值</span>
      <span>¥ {{ formatMoney(serverTotal) }} (差 {{ formatMoney(diffAmount) }})</span>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  form: { discount_rate: number; tax_rate: number }
  localTotals: { subtotal: number; discount_amount: number; tax_amount: number; total: number }
  serverTotalDiff: boolean
  serverTotal: number
  diffAmount: number
  formatMoney: (n: number | string) => string
}>()
</script>

<style lang="scss" scoped>
.amount-breakdown {
  background: #fafbfc;
  border: 1px solid #e6ebf2;
  border-radius: 8px;
  padding: 16px 20px;
  margin-top: 12px;
}
.ab-row {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 14px;
  color: #303133;
}
.ab-row.total {
  border-top: 1px solid #e6ebf2;
  margin-top: 8px;
  padding-top: 12px;
  font-size: 16px;
  font-weight: 700;
  color: #1D9E75;
}
.text-danger { color: #A32D2D; }
</style>
