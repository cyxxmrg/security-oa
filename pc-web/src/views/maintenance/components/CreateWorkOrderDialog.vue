<template>
  <el-dialog
    v-model="visible"
    title="新建工单"
    width="1440px"
    destroy-on-close
    @closed="resetForm"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
      <!-- 收费方式 + 优先级 -->
      <el-row :gutter="24">
        <el-col :span="12">
          <el-form-item label="收费方式" prop="charge_type">
            <el-radio-group v-model="form.charge_type">
              <el-radio-button value="warranty_free">保内免费</el-radio-button>
              <el-radio-button value="contract_free">合同内免费</el-radio-button>
              <el-radio-button value="paid">收费</el-radio-button>
            </el-radio-group>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="优先级">
            <el-radio-group v-model="form.priority">
              <el-radio-button value="low">低</el-radio-button>
              <el-radio-button value="medium">中</el-radio-button>
              <el-radio-button value="high">高</el-radio-button>
              <el-radio-button value="urgent">紧急</el-radio-button>
            </el-radio-group>
          </el-form-item>
        </el-col>
      </el-row>

      <!-- 收费关联: 按收费方式动态显示 -->
      <el-row v-if="form.charge_type === 'warranty_free'" :gutter="24">
        <el-col :span="12">
          <el-form-item label="关联项目" prop="project_id">
            <el-select v-model="form.project_id" filterable clearable placeholder="选择关联项目" style="width: 100%">
              <el-option v-for="p in projectOptions" :key="p.id" :label="p.name" :value="p.id" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row v-else-if="form.charge_type === 'contract_free'" :gutter="24">
        <el-col :span="12">
          <el-form-item label="关联项目" prop="project_id">
            <el-select v-model="form.project_id" filterable clearable placeholder="选择关联项目" style="width: 100%">
              <el-option v-for="p in projectOptions" :key="p.id" :label="p.name" :value="p.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="关联合同" prop="contract_id">
            <el-select v-model="form.contract_id" filterable clearable placeholder="选择关联合同" style="width: 100%">
              <el-option v-for="c in contractOptions" :key="c.id" :label="c.name" :value="c.id" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row v-else :gutter="24">
        <el-col :span="12">
          <el-form-item label="最低收费标准" prop="min_charge">
            <el-select v-model="form.min_charge" clearable placeholder="选择收费标准" style="width: 100%">
              <el-option :value="120" label="¥ 120" />
              <el-option :value="300" label="¥ 300" />
              <el-option :value="500" label="¥ 500" />
              <el-option :value="800" label="¥ 800" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <!-- 联系信息 -->
      <el-row :gutter="24">
        <el-col :span="8">
          <el-form-item label="联系人" prop="contact_name">
            <el-input v-model="form.contact_name" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="联系电话">
            <el-input v-model="form.contact_phone" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="地址">
            <el-input v-model="form.address" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="24">
        <el-col :span="12">
          <el-form-item label="客户档案">
            <el-select v-model="form.customer_id" filterable clearable placeholder="关联已有客户 (可选)" style="width: 100%">
              <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="预约时间">
            <el-date-picker v-model="form.scheduled_at" type="datetime" format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-divider content-position="left">设备 / 故障</el-divider>

      <el-row :gutter="24">
        <el-col :span="8">
          <el-form-item label="品牌">
            <el-input v-model="form.equipment_brand" placeholder="如: 海康威视" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="型号">
            <el-input v-model="form.equipment_model" placeholder="如: DS-2CD2T47" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="序列号">
            <el-input v-model="form.serial_no" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="故障描述" prop="fault_description">
        <el-input v-model="form.fault_description" type="textarea" :rows="4" maxlength="2000" show-word-limit />
      </el-form-item>

      <el-form-item label="备注">
        <el-input v-model="form.remarks" type="textarea" :rows="2" maxlength="1000" />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="onSubmit" :loading="submitting">提交</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapItem } from '@/utils/response'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'done'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const formRef = ref()
const submitting = ref(false)
const customers = ref<Record<string, unknown>[]>([])
const projectOptions = ref<{ id: number; name: string }[]>([])
const contractOptions = ref<{ id: number; name: string }[]>([])

const form = ref({
  contact_name: '', contact_phone: '', address: '',
  customer_id: null as number | null,
  project_id: null as number | null,
  contract_id: null as number | null,
  charge_type: 'paid',
  min_charge: null as number | null,
  priority: 'medium',
  equipment_brand: '', equipment_model: '', serial_no: '',
  fault_description: '', scheduled_at: '',
  remarks: '',
})

const rules = {
  contact_name: [{ required: true, message: '联系人必填', trigger: 'blur' }],
  fault_description: [{ required: true, message: '故障描述必填', trigger: 'blur' }],
}

const resetForm = () => {
  form.value = {
    contact_name: '', contact_phone: '', address: '',
    customer_id: null, project_id: null, contract_id: null,
    charge_type: 'paid', min_charge: null,
    priority: 'medium',
    equipment_brand: '', equipment_model: '', serial_no: '',
    fault_description: '', scheduled_at: '',
    remarks: '',
  }
  formRef.value?.clearValidate()
}

const loadCustomers = async () => {
  try { const res = await get('/customers', { per_page: 100 }); customers.value = unwrapList(res) }
  catch { customers.value = [] }
}

const loadProjects = async () => {
  try {
    const res = await get('/projects', { per_page: 200, status: 'in_progress' })
    const list = unwrapList(res)
    projectOptions.value = list.map((p: Record<string, unknown>) => ({ id: Number(p.id), name: String(p.name || '') }))
  } catch { projectOptions.value = [] }
}

const loadContracts = async () => {
  try {
    const res = await get('/project-contracts', { per_page: 200 })
    const list = unwrapList(res)
    contractOptions.value = list.map((c: Record<string, unknown>) => ({ id: Number(c.id), name: String(c.name || c.code || '') }))
  } catch { contractOptions.value = [] }
}

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    // 清理未选中的关联字段
    const payload = { ...form.value }
    if (payload.charge_type !== 'contract_free') payload.contract_id = null
    if (payload.charge_type !== 'paid') payload.min_charge = null
    const res = await post('/work-orders', payload)
    const wo = unwrapItem(res) || {}
    ElMessage.success(`工单 ${wo?.code || '新'} 已创建`)
    visible.value = false
    emit('done')
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '创建失败')
  } finally {
    submitting.value = false
  }
}

onMounted(() => { loadCustomers(); loadProjects() })
// loadContracts 暂不调用 (/api/project-contracts 路由不存在)
</script>
