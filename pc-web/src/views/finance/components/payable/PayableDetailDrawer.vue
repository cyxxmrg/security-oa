<template>
  <el-drawer v-model="visible" title="应付详情" size="540px">
    <div v-if="row" class="detail">
      <div class="detail__head">
        <div class="detail__supplier">{{ row.supplier?.name }}</div>
        <el-tag :type="statusType(row.status)" effect="dark" round>{{ statusLabel(row.status) }}</el-tag>
      </div>
      <el-descriptions :column="2" border size="default" class="mt">
        <el-descriptions-item label="应付金额">¥{{ formatMoney(row.amount) }}</el-descriptions-item>
        <el-descriptions-item label="已付金额">¥{{ formatMoney(row.paid_amount) }}</el-descriptions-item>
        <el-descriptions-item label="未付金额">¥{{ formatMoney(row.remaining_amount) }}</el-descriptions-item>
        <el-descriptions-item label="到期日">{{ row.due_date || '-' }}</el-descriptions-item>
        <el-descriptions-item label="付款条件">{{ row.payment_term || '-' }}</el-descriptions-item>
        <el-descriptions-item label="关联项目">{{ row.project?.name || '-' }}</el-descriptions-item>
      </el-descriptions>
      <div class="detail__section-title">付款进度</div>
      <el-progress :percentage="computeRate(row)" :color="progressColor(row)" :stroke-width="10" />
      <div v-if="row.notes" class="detail__section-title">备注</div>
      <div v-if="row.notes" class="detail__notes">{{ row.notes }}</div>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Payable } from './types'
import { formatMoney, statusLabel, statusType, computeRate, progressColor } from './types'

// v0.3.25 抽自 finance/Payable.vue:227-247
const props = defineProps<{
  modelValue: boolean
  row: Payable | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})
</script>

<style lang="scss" scoped>
.detail { padding: 0 8px; }
.detail__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.detail__supplier { font-size: 18px; font-weight: 700; color: #303133; }
.mt { margin-top: 12px; }
.detail__section-title { font-size: 14px; font-weight: 600; color: #303133; margin: 20px 0 8px; padding-left: 8px; border-left: 3px solid #0C447C; }
.detail__notes { padding: 12px; background: #fafbfc; border-radius: 6px; font-size: 13px; color: #606266; line-height: 1.6; }
</style>
