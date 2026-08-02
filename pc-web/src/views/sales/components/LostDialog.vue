<template>
  <el-dialog
    :model-value="visible"
    title="标记战败"
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
      <el-form-item label="战败原因" prop="lost_reason">
        <el-select
          v-model="formData.lost_reason"
          placeholder="请选择原因"
          style="width: 100%"
        >
          <el-option
            v-for="r in lostReasons"
            :key="r.value"
            :label="r.label"
            :value="r.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="formData.notes" type="textarea" :rows="2" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="$emit('update:visible', false)">取消</el-button>
      <el-button
        type="danger"
        :loading="submitting"
        @click="handleConfirm"
      >
        确认战败
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { FormInstance, FormRules } from 'element-plus'
import type { Opportunity } from '../types'

export interface LostFormData {
  lost_reason: string
  notes: string
}

const props = defineProps<{
  visible: boolean
  submitting: boolean
  target: Opportunity | null
  lostReasons: { value: string; label: string }[]
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'confirm', data: LostFormData): void
}>()

const formRef = ref<FormInstance>()
const formData = reactive<LostFormData>({
  lost_reason: '',
  notes: '',
})

const formRules: FormRules = {
  lost_reason: [{ required: true, message: '请选择战败原因', trigger: 'change' }],
}

watch(
  () => props.target,
  () => {
    formData.lost_reason = ''
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
