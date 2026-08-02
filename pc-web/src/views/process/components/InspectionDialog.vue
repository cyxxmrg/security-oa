<template>
  <el-dialog
    :model-value="visible"
    title="新增验收记录"
    width="560px"
    destroy-on-close
    @update:model-value="emit('update:visible', $event)"
    @closed="resetForm"
  >
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="100px"
    >
      <el-form-item label="验收结果" prop="result">
        <el-radio-group v-model="form.result">
          <el-radio-button value="pass">合格</el-radio-button>
          <el-radio-button value="fail">不合格</el-radio-button>
          <el-radio-button value="rectify">整改</el-radio-button>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="验收类型" prop="inspection_type">
        <el-select
          v-model="form.inspection_type"
          placeholder="请选择验收类型"
          style="width: 100%"
        >
          <el-option
            v-for="t in INSPECTION_TYPE_OPTIONS"
            :key="t.value"
            :label="t.label"
            :value="t.value"
          />
        </el-select>
      </el-form-item>

      <el-form-item label="验收日期" prop="inspected_at">
        <el-date-picker
          v-model="form.inspected_at"
          type="datetime"
          placeholder="选择验收时间"
          value-format="YYYY-MM-DD HH:mm:ss"
          style="width: 100%"
        />
      </el-form-item>

      <el-form-item
        v-if="form.result === 'fail' || form.result === 'rectify'"
        label="整改/缺陷"
        prop="defects"
      >
        <el-input
          v-model="form.defects"
          type="textarea"
          :rows="3"
          placeholder="请描述不合格或需要整改的具体问题"
          maxlength="500"
          show-word-limit
        />
      </el-form-item>

      <el-form-item label="验收备注">
        <el-input
          v-model="form.remark"
          type="textarea"
          :rows="3"
          placeholder="其他需要记录的信息（可选）"
          maxlength="500"
          show-word-limit
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">
        提交验收
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { INSPECTION_TYPE_OPTIONS, toBackendResult } from '../types'

export interface InspectionFormData {
  result: 'pass' | 'fail' | 'rectify'
  inspection_type: string
  inspected_at: string
  defects: string
  remark: string
}

const props = defineProps<{
  visible: boolean
  submitting: boolean
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit', data: { backendResult: string; payload: Record<string, unknown> }): void
}>()

const formRef = ref<FormInstance>()
const form = reactive<InspectionFormData>({
  result: 'pass',
  inspection_type: 'supervisor',
  inspected_at: '',
  defects: '',
  remark: '',
})

const rules: FormRules = {
  result: [{ required: true, message: '请选择验收结果', trigger: 'change' }],
  inspection_type: [{ required: true, message: '请选择验收类型', trigger: 'change' }],
  inspected_at: [{ required: true, message: '请选择验收时间', trigger: 'change' }],
}

watch(
  () => props.visible,
  (v) => {
    if (v) {
      form.result = 'pass'
      form.inspection_type = 'supervisor'
      form.inspected_at = new Date().toISOString().slice(0, 19).replace('T', ' ')
      form.defects = ''
      form.remark = ''
    }
  },
)

const resetForm = () => {
  formRef.value?.clearValidate()
  form.result = 'pass'
  form.inspection_type = 'supervisor'
  form.inspected_at = ''
  form.defects = ''
  form.remark = ''
}

const handleSubmit = async () => {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }
  if ((form.result === 'fail' || form.result === 'rectify') && !form.defects.trim()) {
    ElMessage.warning('不合格或整改必须填写缺陷描述')
    return
  }
  // 前端 rectify → 后端 partial
  const backendResult = toBackendResult(form.result)
  const payload: Record<string, unknown> = {
    inspection_type: form.inspection_type,
    inspection_date: form.inspected_at,
    result: backendResult,
    remark: form.remark || undefined,
  }
  if (form.defects && form.defects.trim()) {
    payload.issues = [form.defects.trim()]
  }
  emit('submit', { backendResult, payload })
}
</script>
