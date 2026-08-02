<template>
  <div class="page-container">
    <div class="page-header">
      <el-button :icon="ArrowLeft" @click="$router.back()" circle />
      <span class="page-title">新建工单</span>
    </div>

    <div class="content-card">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
        <el-form-item label="优先级">
          <el-radio-group v-model="form.priority">
            <el-radio-button label="low" value="low">低</el-radio-button>
            <el-radio-button label="medium" value="medium">中</el-radio-button>
            <el-radio-button label="high" value="high">高</el-radio-button>
            <el-radio-button label="urgent" value="urgent">紧急</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="联系人" prop="contact_name">
          <el-input v-model="form.contact_name" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="form.contact_phone" />
        </el-form-item>
        <el-form-item label="地址">
          <el-input v-model="form.address" />
        </el-form-item>
        <el-form-item label="客户档案">
          <el-select v-model="form.customer_id" filterable clearable placeholder="关联已有客户 (可选)" style="width: 100%">
            <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>

        <el-divider content-position="left">设备 / 故障</el-divider>
        <el-form-item label="品牌">
          <el-input v-model="form.equipment_brand" placeholder="如: 海康威视" />
        </el-form-item>
        <el-form-item label="型号">
          <el-input v-model="form.equipment_model" placeholder="如: DS-2CD2T47" />
        </el-form-item>
        <el-form-item label="序列号">
          <el-input v-model="form.serial_no" />
        </el-form-item>
        <el-form-item label="故障描述" prop="fault_description">
          <el-input v-model="form.fault_description" type="textarea" :rows="4" maxlength="2000" show-word-limit />
        </el-form-item>
        <el-form-item label="预约时间">
          <el-date-picker v-model="form.scheduled_at" type="datetime" format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
        </el-form-item>
        <el-form-item label="是否收费">
          <el-switch v-model="form.is_billable" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remarks" type="textarea" :rows="2" maxlength="1000" />
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="onSubmit" :loading="submitting">提交</el-button>
          <el-button @click="$router.back()">取消</el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapItem } from '@/utils/response'

const router = useRouter()
const formRef = ref()
const submitting = ref(false)
const customers = ref<Record<string, unknown>[]>([])

const form = ref({
  contact_name: '', contact_phone: '', address: '',
  customer_id: null as number | null,
  priority: 'medium',
  equipment_brand: '', equipment_model: '', serial_no: '',
  fault_description: '', scheduled_at: '',
  is_billable: true,
  remarks: '',
})

const rules = {
  contact_name: [{ required: true, message: '联系人必填', trigger: 'blur' }],
  fault_description: [{ required: true, message: '故障描述必填', trigger: 'blur' }],
}

const loadCustomers = async () => {
  try {
    // /customers 返回 paginator
    const res = await get('/customers', { per_page: 100 })
    customers.value = unwrapList(res)
  } catch { customers.value = [] }
}

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    // V0.6.3: res = {code, data: <new wo>}
    const res = await post('/work-orders', form.value)
    const wo = unwrapItem(res) || {}
    ElMessage.success(`工单 ${wo?.code || '新'} 已创建`)
    if (wo?.id) router.push(`/maintenance/work-orders/${wo.id}`)
    else router.push('/maintenance/work-orders')
  } catch (e: unknown) { ElMessage.error(e?.message || '创建失败') }
  finally { submitting.value = false }
}

onMounted(() => loadCustomers())
</script>

<style scoped lang="scss">
.page-container { padding: 20px; }
.page-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.page-title { font-size: 20px; font-weight: 600; }
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
:deep(.el-form-item) { max-width: 600px; }
@media (max-width: 768px) { .page-container { padding: 12px; } :deep(.el-form-item) { max-width: 100%; } }
</style>
