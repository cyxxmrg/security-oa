<template>
  <el-dialog v-model="visible" title="新建返修" width="1440px" destroy-on-close @closed="resetForm">
    <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
      <el-row :gutter="24">
        <el-col :span="12">
          <el-form-item label="来源" prop="source_type">
            <el-radio-group v-model="form.source_type">
              <el-radio-button value="customer">客户送修</el-radio-button>
              <el-radio-button value="work_order">维修工单</el-radio-button>
              <el-radio-button value="internal">内部送修</el-radio-button>
            </el-radio-group>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item v-if="form.source_type === 'work_order'" label="选择工单" prop="source_id">
            <el-select v-model="form.source_id" filterable remote :remote-method="searchWorkOrders" :loading="searching" placeholder="搜索工单" style="width:100%" @change="onWorkOrderSelect">
              <el-option v-for="wo in workOrderOptions" :key="wo.id" :label="`${wo.code} - ${wo.contact_name||wo.customer_name} - ${(wo.fault_description||'').slice(0,30)}`" :value="wo.id" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="24">
        <el-col :span="8">
          <el-form-item label="客户" prop="contact_name">
            <el-input v-model="form.contact_name" placeholder="客户姓名" />
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

      <el-form-item label="客户档案">
        <el-select v-model="form.customer_id" filterable clearable placeholder="关联已有客户 (可选)" style="width:100%">
          <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
        </el-select>
      </el-form-item>

      <el-divider content-position="left">设备信息</el-divider>

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

      <el-divider content-position="left">故障信息</el-divider>

      <el-row :gutter="24">
        <el-col :span="8">
          <el-form-item label="故障类型">
            <el-select v-model="form.fault_type" style="width:100%">
              <el-option label="硬件故障" value="硬件" />
              <el-option label="软件故障" value="软件" />
              <el-option label="外观损坏" value="外观" />
              <el-option label="性能问题" value="性能" />
              <el-option label="其他" value="其他" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="严重程度">
            <el-radio-group v-model="form.severity">
              <el-radio value="low">低</el-radio>
              <el-radio value="medium">中</el-radio>
              <el-radio value="high">高</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="是否保内">
            <el-switch v-model="form.is_warranty" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="故障描述" prop="fault_description">
        <el-input v-model="form.fault_description" type="textarea" :rows="3" maxlength="2000" show-word-limit />
      </el-form-item>

      <el-divider content-position="left">维修方式预估</el-divider>

      <el-row :gutter="24">
        <el-col :span="8">
          <el-form-item label="维修方式">
            <el-select v-model="form.method_type" style="width:100%">
              <el-option label="免费（保内）" value="free_warranty" />
              <el-option label="免费（合同）" value="free_contract" />
              <el-option label="付费（维修）" value="paid_repair" />
              <el-option label="付费（换新）" value="paid_replace" />
              <el-option label="退回（不修）" value="returned" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="预计完成">
            <el-date-picker v-model="form.expected_finish_at" type="datetime" placeholder="预计完成时间" format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item v-if="form.is_warranty" label="保内截止">
            <el-date-picker v-model="form.warranty_until" type="date" placeholder="保内截止日期" value-format="YYYY-MM-DD" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>

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
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapItem } from '@/utils/response'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: boolean): void; (e: 'done'): void }>()
const visible = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const formRef = ref()
const submitting = ref(false)
const customers = ref<Record<string, unknown>[]>([])
const workOrderOptions = ref<Record<string, unknown>[]>([])
const searching = ref(false)

const form = ref({
  source_type: 'customer',
  source_id: null as number | null,
  customer_id: null as number | null,
  contact_name: '',
  contact_phone: '',
  address: '',
  equipment_brand: '',
  equipment_model: '',
  serial_no: '',
  fault_type: '硬件',
  fault_description: '',
  severity: 'medium',
  method_type: 'paid_repair',
  expected_finish_at: '',
  is_warranty: false,
  warranty_until: '',
  remarks: '',
})

const rules = {
  contact_name: [{ required: true, message: '客户姓名必填', trigger: 'blur' }],
  fault_description: [{ required: true, message: '故障描述必填', trigger: 'blur' }],
}

const onWorkOrderSelect = (val: number) => {
  const wo = workOrderOptions.value.find(w => Number(w.id) === val)
  if (!wo) return
  form.value.contact_name = (wo.contact_name as string) || form.value.contact_name
  form.value.contact_phone = (wo.contact_phone as string) || form.value.contact_phone
  form.value.address = (wo.address as string) || form.value.address
  form.value.customer_id = (wo.customer_id as number) || form.value.customer_id
  form.value.equipment_brand = (wo.equipment_brand as string) || form.value.equipment_brand
  form.value.equipment_model = (wo.equipment_model as string) || form.value.equipment_model
  form.value.serial_no = (wo.serial_no as string) || form.value.serial_no
  form.value.fault_description = (wo.fault_description as string) || form.value.fault_description
}

const resetForm = () => {
  form.value = {
    source_type: 'customer', source_id: null, customer_id: null,
    contact_name: '', contact_phone: '', address: '',
    equipment_brand: '', equipment_model: '', serial_no: '',
    fault_type: '硬件', fault_description: '', severity: 'medium',
    method_type: 'paid_repair', expected_finish_at: '',
    is_warranty: false, warranty_until: '', remarks: '',
  }
  formRef.value?.clearValidate()
}

const loadCustomers = async () => {
  try { const res = await get('/customers', { per_page: 100 }); customers.value = unwrapList(res) }
  catch { customers.value = [] }
}

const searchWorkOrders = async (kw: string) => {
  if (!kw) return
  searching.value = true
  try {
    const res = await get('/work-orders', { keyword: kw, per_page: 20, status: 'in_progress' })
    workOrderOptions.value = unwrapList(res)
  } catch { workOrderOptions.value = [] }
  finally { searching.value = false }
}

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    await post('/repair-orders', form.value)
    ElMessage.success('返修单已创建')
    visible.value = false
    emit('done')
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '创建失败')
  } finally { submitting.value = false }
}

onMounted(() => loadCustomers())
</script>
