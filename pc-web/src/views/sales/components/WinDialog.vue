<template>
  <el-dialog
    :model-value="visible"
    title="标记成交"
    width="1500px"
    @update:model-value="$emit('update:visible', $event)"
  >
    <el-form
      :model="formData"
      :rules="formRules"
      ref="formRef"
      label-width="100px"
    >
      <el-form-item label="商机">
        <span>{{ target?.name }}</span>
      </el-form-item>
      <el-form-item label="合同金额" prop="contract_amount">
        <el-input-number
          v-model="formData.contract_amount"
          :min="0"
          :step="1000"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="签约日期" prop="signed_at">
        <el-date-picker
          v-model="formData.signed_at"
          type="date"
          placeholder="选择日期"
          style="width: 100%"
          value-format="YYYY-MM-DD"
        />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="formData.notes" type="textarea" :rows="2" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="$emit('update:visible', false)">取消</el-button>
      <el-button type="success" :loading="submitting" @click="handleConfirm">
        确认成交
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { FormInstance, FormRules } from 'element-plus'
import type { Opportunity } from '../types'

export interface WinFormData {
  contract_amount: number
  signed_at: string
  notes: string
}

const props = defineProps<{
  visible: boolean
  submitting: boolean
  target: Opportunity | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'confirm', data: WinFormData): void
}>()

const formRef = ref<FormInstance>()
const formData = reactive<WinFormData>({
  contract_amount: 0,
  signed_at: '',
  notes: '',
})

const formRules: FormRules = {
  contract_amount: [{ required: true, message: '请输入合同金额', trigger: 'blur' }],
  signed_at: [{ required: true, message: '请选择签约日期', trigger: 'change' }],
}

watch(
  () => props.target,
  (row) => {
    if (!row) return
    formData.contract_amount = Number(row.estimated_amount || 0)
    formData.signed_at = new Date().toISOString().slice(0, 10)
    formData.notes = ''
  },
  { immediate: true },
)

const handleConfirm = async () => {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  emit('confirm', { ...formData })
}
</script>
