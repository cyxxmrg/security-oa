<template>
  <el-dialog
    v-model="visible"
    title="登记收款"
    width="560px"
  >
    <el-form :model="form" label-width="100px" v-if="row">
      <el-descriptions :column="1" border size="small" class="mb">
        <el-descriptions-item label="客户">{{ row.customer?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="应收金额">¥{{ formatMoney(row.amount) }}</el-descriptions-item>
        <el-descriptions-item label="未收金额">¥{{ formatMoney(row.remaining_amount) }}</el-descriptions-item>
      </el-descriptions>
      <el-form-item label="本次收款">
        <el-input-number
          v-model="form.received_amount"
          :min="0"
          :max="Number(row.remaining_amount)"
          :precision="2"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="收款日期">
        <el-date-picker v-model="form.received_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
      </el-form-item>
      <el-form-item label="收款账户">
        <el-select v-model="form.account_id" placeholder="选择收款账户" style="width: 100%">
          <el-option
            v-for="a in accounts"
            :key="a.id"
            :label="`${a.name}（${a.bank || a.type}）`"
            :value="a.id"
          />
        </el-select>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="loading" @click="emit('submit')">确认登记</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Receivable, ReceiveForm } from './types'
import { formatMoney } from './types'

// v0.3.25 抽自 finance/Receivable.vue:201-225
const props = defineProps<{
  modelValue: boolean
  loading: boolean
  form: ReceiveForm
  row: Receivable | null
  accounts: { id: number; name: string; bank?: string; type?: string }[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'submit'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})
</script>

<style lang="scss" scoped>
.mb { margin-bottom: 16px; }
</style>
