<template>
  <div class="page-container">
    <div class="page-header">
      <el-button :icon="ArrowLeft" @click="$router.back()" circle />
      <span class="page-title">新建返修</span>
    </div>

    <div class="content-card">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
        <el-form-item label="来源" prop="source_type">
          <el-radio-group v-model="form.source_type">
            <el-radio-button label="customer" value="customer">📞 客户送修</el-radio-button>
            <el-radio-button label="work_order" value="work_order">🛠️ 维修工单</el-radio-button>
            <el-radio-button label="internal" value="internal">🏢 内部送修</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item v-if="form.source_type === 'work_order'" label="选择工单" prop="source_id">
          <el-select v-model="form.source_id" filterable remote :remote-method="searchWorkOrders" :loading="searching" placeholder="搜索工单号/客户/故障" style="width: 100%" @change="onWorkOrderSelect">
            <el-option v-for="wo in workOrderOptions" :key="wo.id" :label="`${wo.code} - ${wo.contact_name || wo.customer_name} - ${wo.fault_description?.slice(0,30)}`" :value="wo.id" />
          </el-select>
        </el-form-item>

        <el-form-item label="客户" prop="contact_name">
          <el-input v-model="form.contact_name" placeholder="客户姓名" style="width: 100%" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="form.contact_phone" style="width: 100%" />
        </el-form-item>
        <el-form-item label="地址">
          <el-input v-model="form.address" style="width: 100%" />
        </el-form-item>
        <el-form-item label="客户档案">
          <el-select v-model="form.customer_id" filterable clearable placeholder="关联已有客户 (可选)" style="width: 100%">
            <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>

        <el-divider content-position="left">设备信息</el-divider>
        <el-form-item label="品牌">
          <el-input v-model="form.equipment_brand" placeholder="如: 海康威视" />
        </el-form-item>
        <el-form-item label="型号">
          <el-input v-model="form.equipment_model" placeholder="如: DS-2CD2T47" />
        </el-form-item>
        <el-form-item label="序列号">
          <el-input v-model="form.serial_no" />
        </el-form-item>

        <el-divider content-position="left">故障信息</el-divider>
        <el-form-item label="故障类型">
          <el-select v-model="form.fault_type" style="width: 100%">
            <el-option label="硬件故障" value="硬件" />
            <el-option label="软件故障" value="软件" />
            <el-option label="外观损坏" value="外观" />
            <el-option label="性能问题" value="性能" />
            <el-option label="其他" value="其他" />
          </el-select>
        </el-form-item>
        <el-form-item label="故障描述" prop="fault_description">
          <el-input v-model="form.fault_description" type="textarea" :rows="4" maxlength="2000" show-word-limit />
        </el-form-item>
        <el-form-item label="严重程度">
          <el-radio-group v-model="form.severity">
            <el-radio label="low" value="low">低</el-radio>
            <el-radio label="medium" value="medium">中</el-radio>
            <el-radio label="high" value="high">高</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-divider content-position="left">维修方式预估</el-divider>
        <el-form-item label="维修方式">
          <el-select v-model="form.method_type" style="width: 100%">
            <el-option label="🆓 免费（保内）" value="free_warranty" />
            <el-option label="🆓 免费（合同）" value="free_contract" />
            <el-option label="💰 付费（维修）" value="paid_repair" />
            <el-option label="💰 付费（换新）" value="paid_replace" />
            <el-option label="↩️ 退回（不修）" value="returned" />
          </el-select>
        </el-form-item>
        <el-form-item label="预计完成">
          <el-date-picker v-model="form.expected_finish_at" type="datetime" placeholder="预计完成时间" format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
        </el-form-item>
        <el-form-item label="是否保内">
          <el-switch v-model="form.is_warranty" />
        </el-form-item>
        <el-form-item label="保内截止">
          <el-date-picker v-model="form.warranty_until" type="date" placeholder="保内截止日期" value-format="YYYY-MM-DD" style="width: 100%" />
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

const loadCustomers = async () => {
  try {
    // /customers 返回 paginator
    const res = await get('/customers', { per_page: 100 })
    customers.value = unwrapList(res)
  } catch { customers.value = [] }
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

const onWorkOrderSelect = async (woId: number) => {
  // 自动带过来工单的字段
  const wo = workOrderOptions.value.find(w => w.id === woId)
  if (!wo) return
  form.value.contact_name = wo.contact_name || form.value.contact_name
  form.value.contact_phone = wo.contact_phone || form.value.contact_phone
  form.value.address = wo.address || form.value.address
  form.value.equipment_brand = wo.equipment_brand || form.value.equipment_brand
  form.value.equipment_model = wo.equipment_model || form.value.equipment_model
  form.value.serial_no = wo.serial_no || form.value.serial_no
  form.value.fault_description = wo.fault_description || form.value.fault_description
  ElMessage.success('已自动从工单带过来字段')
}

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    // V0.6.3: res = {code, data: <new ro>}
    const res = await post('/repair-orders', form.value)
    const ro = unwrapItem(res) || {}
    ElMessage.success(`返修单 ${ro?.code || '新'} 已创建`)
    if (ro?.id) {
      router.push(`/maintenance/repairs/${ro.id}`)
    } else {
      // 兜底: 跳到列表
      router.push('/maintenance/repairs')
    }
  } catch (e: unknown) {
    ElMessage.error(e?.message || '创建失败')
  } finally { submitting.value = false }
}

onMounted(() => loadCustomers())
</script>

<style scoped lang="scss">
.page-container { padding: 20px; }
.page-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.page-title { font-size: 20px; font-weight: 600; }
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
:deep(.el-form-item) { max-width: 600px; }
@media (max-width: 768px) {
  .page-container { padding: 12px; }
  :deep(.el-form-item) { max-width: 100%; }
}
</style>
