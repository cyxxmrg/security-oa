<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="用车申请"
    width="1440px"
    destroy-on-close
    :close-on-click-modal="false"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="选择车辆">
            <el-select v-model="form.vehicle_id" placeholder="选择车辆（可选）" filterable clearable style="width:100%">
              <el-option v-for="v in vehicles" :key="v.id" :value="v.id" :label="`${v.plate_no} ${v.brand || ''} ${v.model || ''}`" :disabled="v.status === 'in_use' || v.status === 'maintenance' || v.status === 'retired'">
                <div style="display:flex;justify-content:space-between;width:100%">
                  <span>{{ v.plate_no }} {{ v.brand || '' }} {{ v.model || '' }}</span>
                  <span v-if="v.status === 'in_use'" style="color:#e6a23c;font-size:12px">使用中</span>
                  <span v-else-if="v.status === 'maintenance'" style="color:#909399;font-size:12px">维修中</span>
                  <span v-else-if="v.status === 'retired'" style="color:#A32D2D;font-size:12px">已停用</span>
                  <span v-else style="color:#67c23a;font-size:12px">可用</span>
                </div>
              </el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="用车日期" prop="usage_date">
            <el-date-picker v-model="form.usage_date" type="date" placeholder="选择日期" style="width:100%" value-format="YYYY-MM-DD" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="开始时间" prop="start_time">
            <el-time-picker v-model="form.start_time" placeholder="开始" style="width:100%" value-format="HH:mm" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="结束时间" prop="end_time">
            <el-time-picker v-model="form.end_time" placeholder="结束" style="width:100%" value-format="HH:mm" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="目的地" prop="destination">
            <el-input v-model="form.destination" placeholder="请输入目的地" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="乘车人数" prop="passengers">
            <el-input-number v-model="form.passengers" :min="1" :max="20" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="是否自驾">
            <el-radio-group v-model="form.self_drive">
              <el-radio :value="false">否（需要司机）</el-radio>
              <el-radio :value="true">是（自驾）</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="用车事由" prop="purpose">
        <el-input v-model="form.purpose" type="textarea" :rows="3" placeholder="请详细说明用车事由" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">提交申请</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'

defineProps<{ visible: boolean }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void; (e: 'success'): void }>()

interface VehicleOpt { id: number; plate_no: string; brand?: string; model?: string; status: string }

const formRef = ref()
const submitting = ref(false)
const vehicles = ref<VehicleOpt[]>([])

const form = reactive({
  vehicle_id: null as number | null,
  usage_date: '', start_time: '', end_time: '',
  destination: '', purpose: '', passengers: 1, self_drive: false,
})
const rules = {
  usage_date:  [{ required: true, message: '请选择用车日期', trigger: 'change' }],
  start_time:  [{ required: true, message: '请选择开始时间', trigger: 'change' }],
  end_time:    [{ required: true, message: '请选择结束时间', trigger: 'change' }],
  destination: [{ required: true, message: '请输入目的地', trigger: 'blur' }],
  purpose:     [{ required: true, message: '请输入用车事由', trigger: 'blur' }],
  passengers:  [{ required: true, message: '请输入乘车人数', trigger: 'blur' }],
}

async function loadVehicles() {
  try {
    const r = await get('/vehicles')
    const items = (r as { data?: VehicleOpt[] })?.data ?? []
    vehicles.value = Array.isArray(items) ? items : []
  } catch { vehicles.value = [] }
}

const handleSubmit = async () => {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  submitting.value = true
  try {
    const payload = { ...form, vehicle_id: form.vehicle_id || undefined }
    await post('/vehicles/usage', payload)
    ElMessage.success('用车申请已提交，等待审批')
    Object.assign(form, { vehicle_id: null, usage_date: '', start_time: '', end_time: '', destination: '', purpose: '', passengers: 1, self_drive: false })
    emit('update:visible', false)
    emit('success')
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '提交失败')
  } finally { submitting.value = false }
}

onMounted(loadVehicles)
</script>