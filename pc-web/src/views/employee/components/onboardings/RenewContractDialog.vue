<template>
  <el-dialog
    v-model="visible"
    title="续签合同"
    width="600px"
    destroy-on-close
  >
    <el-form
      :model="form"
      :rules="rules"
      ref="formRef"
      label-width="100px"
    >
      <el-form-item label="员工">
        <el-input :value="row?.name" disabled />
      </el-form-item>
      <el-form-item label="当前合同">
        <el-input
          :value="`${row?.contract_start_date || '—'} 至 ${row?.contract_end_date || '—'}`"
          disabled
        />
      </el-form-item>
      <el-form-item label="新合同起始" prop="contract_start_date">
        <el-date-picker
          v-model="form.contract_start_date"
          type="date"
          placeholder="选择日期"
          style="width: 100%"
          value-format="YYYY-MM-DD"
        />
      </el-form-item>
      <el-form-item label="新合同结束" prop="contract_end_date">
        <el-date-picker
          v-model="form.contract_end_date"
          type="date"
          placeholder="选择日期"
          style="width: 100%"
          value-format="YYYY-MM-DD"
        />
      </el-form-item>
      <el-form-item label="合同文件">
        <el-upload
          :http-request="(opt: Record<string, unknown>) => emit('upload', opt)"
          :show-file-list="false"
          accept="image/*,.pdf,.doc,.docx"
        >
          <el-button :icon="UploadFilled">上传新合同</el-button>
        </el-upload>
        <div v-if="form.contract_file_name" class="uploaded-tip">已上传: {{ form.contract_file_name }}</div>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('submit')">确认续签</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { UploadFilled } from '@element-plus/icons-vue'
import type { Onboarding } from './types'

// v0.3.22 抽自 employee/Onboardings.vue:444-486
const props = defineProps<{
  modelValue: boolean
  submitting: boolean
  form: {
    contract_start_date: string
    contract_end_date: string
    contract_file_id: number | null
    contract_file_name: string
  }
  rules: Record<string, unknown>
  row: Onboarding | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'submit'): void
  (e: 'upload', opt: Record<string, unknown>): void
}>()

const formRef = ref()
defineExpose({ formRef })

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})
</script>

<style lang="scss" scoped>
.uploaded-tip {
  font-size: 12px;
  color: #1D9E75;
  margin-top: 6px;
}
</style>
