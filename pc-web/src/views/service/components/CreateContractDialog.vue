<template>
  <el-dialog v-model="visible" title="新建维保合同" width="1440px" destroy-on-close @closed="resetForm">
    <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
      <el-row :gutter="24">
        <el-col :span="8">
          <el-form-item label="合同编号" prop="contract_no">
            <el-input v-model="form.contract_no" placeholder="如: WH-2026-001" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="客户" prop="customer_id">
            <el-select v-model="form.customer_id" filterable placeholder="选择客户" style="width:100%">
              <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="合同金额" prop="amount">
            <el-input-number v-model="form.amount" :min="0" :precision="2" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="24">
        <el-col :span="8">
          <el-form-item label="起始日期" prop="start_date">
            <el-date-picker v-model="form.start_date" type="date" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="截止日期">
            <el-date-picker v-model="form.end_date" type="date" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="巡检频率">
            <el-select v-model="form.inspection_frequency" placeholder="选择频率" style="width:100%">
              <el-option label="每周" value="weekly" />
              <el-option label="每两周" value="biweekly" />
              <el-option label="每月" value="monthly" />
              <el-option label="每季度" value="quarterly" />
              <el-option label="每半年" value="semiannual" />
              <el-option label="每年" value="yearly" />
              <el-option label="自定义" value="custom" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="维保范围">
        <el-input v-model="form.scope" type="textarea" :rows="3" maxlength="1000" placeholder="描述维保服务范围" />
      </el-form-item>

      <el-form-item label="合同附件">
        <el-upload
          ref="uploadRef"
          accept="image/jpeg,image/png,application/pdf"
          :auto-upload="false"
          :show-file-list="false"
          :on-change="onFileChange"
          :limit="1"
        >
          <template #trigger>
            <el-button type="primary" plain>{{ form.contract_file ? '重新选择' : '选择文件' }}</el-button>
          </template>
          <template v-if="form.contract_file" #tip>
            <div style="display:inline-flex;align-items:center;gap:8px;margin-left:12px">
              <el-tag closable @close="removeFile" type="info">{{ form.contract_file_name }}</el-tag>
              <span v-if="filePreviewUrl" style="display:inline-flex;align-items:center;gap:4px;font-size:13px">
                <el-link :href="filePreviewUrl" target="_blank" type="primary" :underline="false">预览</el-link>
              </span>
            </div>
          </template>
        </el-upload>
        <div style="color:#999;font-size:12px;margin-top:4px">支持 JPG/PNG/PDF 格式，建议 10MB 以内</div>
      </el-form-item>

      <el-form-item label="备注">
        <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="500" />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="onSubmit" :loading="submitting">提交</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapItem, unwrapList } from '@/utils/response'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: boolean): void; (e: 'done'): void }>()
const visible = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const formRef = ref()
const submitting = ref(false)
const customers = ref<{ id: number; name: string }[]>([])

const form = ref({
  contract_no: '',
  customer_id: null as number | null,
  amount: 0,
  start_date: '',
  end_date: '',
  inspection_frequency: 'monthly',
  scope: '',
  notes: '',
  contract_file: '',
  contract_file_name: '',
})

const uploadRef = ref()
const filePreviewUrl = ref('')

const onFileChange = (file: { raw?: File; url?: string; name: string; size: number }) => {
  const maxSize = 10 * 1024 * 1024 // 10MB
  if (file.size > maxSize) {
    ElMessage.warning('文件大小不能超过 10MB')
    return false
  }
  form.value.contract_file_name = file.name
  const reader = new FileReader()
  reader.onload = (e) => {
    form.value.contract_file = (e.target?.result as string) || ''
    filePreviewUrl.value = form.value.contract_file
  }
  if (file.raw) reader.readAsDataURL(file.raw)
  return false
}

const removeFile = () => {
  form.value.contract_file = ''
  form.value.contract_file_name = ''
  filePreviewUrl.value = ''
  uploadRef.value?.clearFiles()
}

const rules = {
  contract_no: [{ required: true, message: '请填写合同编号', trigger: 'blur' }],
  customer_id: [{ required: true, message: '请选择客户', trigger: 'change' }],
  amount: [{ required: true, message: '请填写合同金额', trigger: 'blur' }],
  start_date: [{ required: true, message: '请选择起始日期', trigger: 'change' }],
}

const resetForm = () => {
  form.value = {
    contract_no: '', customer_id: null, amount: 0,
    start_date: '', end_date: '', inspection_frequency: 'monthly',
    scope: '', notes: '', contract_file: '', contract_file_name: '',
  }
  filePreviewUrl.value = ''
  uploadRef.value?.clearFiles()
  formRef.value?.clearValidate()
}

const loadCustomers = async () => {
  try {
    const res = await get('/customers', { per_page: 200 })
    customers.value = (unwrapList(res) as Record<string, unknown>[]).map(c => ({ id: Number(c.id), name: String(c.name || '') }))
  } catch { customers.value = [] }
}

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    await post('/inspections/dev/create-contract', form.value)
    ElMessage.success('维保合同已创建')
    visible.value = false
    emit('done')
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '创建失败')
  } finally { submitting.value = false }
}

onMounted(() => loadCustomers())
</script>
