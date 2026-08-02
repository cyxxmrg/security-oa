<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="办结离职"
    width="600px"
    destroy-on-close
    :close-on-click-modal="false"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
      <el-form-item label="员工" v-if="row">
        <span>{{ row.user?.name || '—' }}</span>
      </el-form-item>
      <el-form-item label="资产已归还" prop="all_assets_returned">
        <el-switch v-model="form.all_assets_returned" />
      </el-form-item>
      <el-form-item label="支付日期" prop="paid_date">
        <el-date-picker v-model="form.paid_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
      </el-form-item>
      <el-form-item label="支付方式">
        <el-select v-model="form.paid_method" placeholder="选择支付方式" style="width: 100%">
          <el-option label="银行转账" value="transfer" />
          <el-option label="现金"       value="cash" />
          <el-option label="支付宝"     value="alipay" />
          <el-option label="微信"       value="wechat" />
        </el-select>
      </el-form-item>
      <el-form-item label="离职证明">
        <el-upload
          :http-request="(opt: Record<string, unknown>) => emit('uploadCert', opt)"
          :show-file-list="false"
          accept=".pdf,.jpg,.png"
        >
          <el-button>上传证明</el-button>
        </el-upload>
        <span v-if="form.certificate_file_name" style="margin-left: 8px; color: #67c23a">
          {{ form.certificate_file_name }}
        </span>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('submit')">确认办结</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { Resignation } from './types'

export interface CompleteForm {
  all_assets_returned: boolean
  paid_date: string
  paid_method: string
  certificate_file_id: number | null
  certificate_file_name: string
}

defineProps<{
  visible: boolean
  form: CompleteForm
  row: Resignation | null
  submitting: boolean
}>()

const formRef = ref()
const rules = {
  all_assets_returned: [{
    required: true,
    validator: (_: Record<string, unknown>, v: string | null, cb: (e?: Error) => void) => v ? cb() : cb(new Error('请确认资产已归还')),
    trigger: 'change',
  }],
  paid_date: [{ required: true, message: '请选择支付日期', trigger: 'change' }],
}

defineExpose({ formRef })

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit'): void
  (e: 'uploadCert', opt: Record<string, unknown>): void
}>()
</script>
