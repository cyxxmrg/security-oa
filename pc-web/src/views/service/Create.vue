<template>
  <div class="page-container">
    <div class="page-header">
      <h2>创建维修工单</h2>
    </div>
    <div class="content-card" v-loading="loading">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" style="max-width: 820px">
        <el-form-item label="客户选择" prop="customer_id">
          <el-select v-model="form.customer_id" placeholder="请选择客户" filterable style="width: 100%">
            <el-option
              v-for="c in customerOptions"
              :key="c.id"
              :label="c.name"
              :value="c.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="关联项目">
          <el-select v-model="form.project_id" placeholder="请选择关联项目（可选）" clearable filterable style="width: 100%">
            <el-option
              v-for="p in projectOptions"
              :key="p.id"
              :label="p.code ? `${p.name}（${p.code}）` : p.name"
              :value="p.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="关联设备">
          <el-select v-model="form.customer_device_id" placeholder="请选择关联设备（可选）" clearable filterable style="width: 100%">
            <el-option
              v-for="d in deviceOptions"
              :key="d.id"
              :label="`${d.name}（SN: ${d.serial_no || '无'}）`"
              :value="d.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="故障描述" prop="fault_description">
          <el-input v-model="form.fault_description" type="textarea" :rows="4" placeholder="请详细描述故障现象、发生时间、影响范围" maxlength="1000" show-word-limit />
        </el-form-item>
        <el-form-item label="紧急程度" prop="urgency">
          <el-radio-group v-model="form.urgency">
            <el-radio value="normal">普通</el-radio>
            <el-radio value="urgent">紧急</el-radio>
            <el-radio value="critical">特急</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="维修类型" prop="service_type">
          <el-radio-group v-model="form.service_type">
            <el-radio value="warranty">质保期内</el-radio>
            <el-radio value="out_of_warranty">质保期外</el-radio>
            <el-radio value="contract">维保合同</el-radio>
            <el-radio value="paid">付费维修</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="SLA 时限">
          <el-input-number v-model="form.sla_hours" :min="1" :max="720" :step="1" placeholder="小时" style="width: 200px" />
          <div class="form-tip">响应+处理的总时限（小时），留空使用默认值</div>
        </el-form-item>
        <el-form-item label="现场照片">
          <el-upload action="#" list-type="picture-card" :auto-upload="false" :limit="5" :on-exceed="handleExceed">
            <el-icon><Plus /></el-icon>
          </el-upload>
          <div class="upload-tip">最多上传5张现场照片（演示用，未实际存储）</div>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="submitting" @click="handleSubmit">提交工单</el-button>
          <el-button @click="handleCancel">取消</el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList } from '@/utils/response'

const router = useRouter()
const formRef = ref()
const loading = ref(false)
const submitting = ref(false)

const customerOptions = ref<Record<string, unknown>[]>([])
const projectOptions = ref<Record<string, unknown>[]>([])
const deviceOptions = ref<Record<string, unknown>[]>([])

const form = reactive({
  customer_id: null as number | null,
  project_id: null as number | null,
  customer_device_id: null as number | null,
  fault_description: '',
  urgency: 'normal',
  service_type: 'warranty',
  sla_hours: 24,
  fault_photos: [] as string[],
})

const rules = {
  customer_id:        [{ required: true, message: '请选择客户', trigger: 'change' }],
  fault_description:  [{ required: true, message: '请输入故障描述', trigger: 'blur' }],
  urgency:            [{ required: true, message: '请选择紧急程度', trigger: 'change' }],
  service_type:       [{ required: true, message: '请选择维修类型', trigger: 'change' }],
}

const handleExceed = () => { ElMessage.warning('最多上传5张照片') }

async function loadCustomers() {
  try {
    const res = await get('/customers', { per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    customerOptions.value = unwrapList(res)
  } catch (e) { console.warn('[loadCustomers]', e) }
}

async function loadProjects() {
  try {
    // V0.6.3: res = {code, data: <projects>}
    const res = await get('/expenses/projects')
    projectOptions.value = unwrapList(res)
  } catch (e) { console.warn('[loadProjects]', e) }
}

// 客户变化时加载该客户的设备
watch(() => form.customer_id, async (cid) => {
  form.customer_device_id = null
  if (!cid) { deviceOptions.value = []; return }
  try {
    const res = await get(`/customers/${cid}/devices`)
    deviceOptions.value = res.data || res || []
  } catch (e) {
    console.warn('[loadDevices]', e)
    deviceOptions.value = []
  }
})

async function handleSubmit() {
  await formRef.value.validate()
  submitting.value = true
  try {
    const payload: Record<string, unknown> = {
      customer_id:        form.customer_id,
      project_id:         form.project_id || null,
      customer_device_id: form.customer_device_id || null,
      fault_description:  form.fault_description.trim(),
      fault_photos:       form.fault_photos,
      urgency:            form.urgency,
      service_type:       form.service_type,
      sla_hours:          form.sla_hours || null,
    }
    // V0.6.3: res = {code, data: <new order>}
    const res = await post('/service/orders', payload)
    const d = res?.data ?? {}
    const orderNo = d?.order_no || ''
    ElMessage.success(`工单 ${orderNo} 创建成功`)
    router.push({ path: '/service', query: { view: String(d?.id || '') } })
  } catch (e: unknown) {
    ElMessage.error(e?.response?.data?.message || e.message || '创建失败')
  } finally {
    submitting.value = false
  }
}

function handleCancel() { router.push('/service') }

onMounted(() => {
  loadCustomers()
  loadProjects()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header {
  margin-bottom: 16px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
}
.content-card {
  background: #fff; border-radius: 8px; padding: 24px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.upload-tip, .form-tip {
  font-size: 12px; color: #999; margin-top: 4px;
}
</style>
