<template>
  <el-dialog v-model="visible" title="完成工单" width="1440px" destroy-on-close @closed="resetForm">
    <!-- 场景选择 -->
    <el-radio-group v-model="action" class="action-selector" size="large">
      <el-radio-button value="complete">✅ 现场处理完毕</el-radio-button>
      <el-radio-button value="convert">🔧 现场无法处理 (转返修)</el-radio-button>
    </el-radio-group>

    <!-- 情景A: 现场处理完毕 -->
    <template v-if="action === 'complete'">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="120px" style="margin-top:16px">
        <el-form-item label="处理结果" prop="result_notes">
          <el-input v-model="form.result_notes" type="textarea" :rows="4" maxlength="2000" show-word-limit placeholder="描述维修过程及结果" />
        </el-form-item>

        <el-row :gutter="24">
          <el-col :span="8">
            <el-form-item label="服务费 (¥)" prop="service_fee">
              <el-input-number v-model="form.service_fee" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="配件费 (¥)" prop="parts_cost">
              <el-input-number v-model="form.parts_cost" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="合计">
              <span style="font-size:18px;font-weight:700;color:#E6A23C">¥{{ (form.service_fee + form.parts_cost).toFixed(2) }}</span>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="收款方式" prop="payment_method">
          <el-radio-group v-model="form.payment_method">
            <el-radio value="receivable">应收款挂账 (记入客户账户)</el-radio>
            <el-radio value="cash">现金收款 (记录到工单)</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 上门服务 → 客户签字 -->
        <el-form-item v-if="wo?.service_type === 'on_site'" label="客户签字" prop="customer_signature">
          <div class="signature-pad" @click="triggerSignature">
            <canvas ref="sigCanvas" width="600" height="200" @mousedown="startDraw" @mousemove="draw" @mouseup="stopDraw" @mouseleave="stopDraw"></canvas>
            <div class="signature-hint" v-if="!hasSignature">请客户在此签名确认服务完成</div>
          </div>
          <div class="sig-actions">
            <el-button size="small" @click="clearSignature">清除重签</el-button>
            <span v-if="!hasSignature" class="sig-warning">⚠ 上门服务必须提供客户签字</span>
          </div>
        </el-form-item>
      </el-form>
    </template>

    <!-- 情景B: 转返修 -->
    <template v-else>
      <el-form :model="convertForm" :rules="convertRules" ref="convertFormRef" label-width="120px" style="margin-top:16px">
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="设备型号" prop="equipment_model">
              <el-input v-model="convertForm.equipment_model" :placeholder="(wo?.equipment_model as string) || '填写设备型号'" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="序列号" prop="serial_no">
              <el-input v-model="convertForm.serial_no" :placeholder="(wo?.serial_no as string) || '填写序列号'" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="返修原因" prop="reason">
          <el-input v-model="convertForm.reason" type="textarea" :rows="4" maxlength="1000" show-word-limit placeholder="描述无法现场处理的原因" />
        </el-form-item>
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="维修方式" prop="method_type">
              <el-select v-model="convertForm.method_type" placeholder="选择维修方式" style="width:100%">
                <el-option value="free_warranty" label="免费保修" />
                <el-option value="free_contract" label="免费合同" />
                <el-option value="paid_repair" label="收费维修" />
                <el-option value="paid_replace" label="收费更换" />
                <el-option value="returned" label="退回厂家" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="预计完成" prop="expected_finish_at">
              <el-date-picker v-model="convertForm.expected_finish_at" type="date" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </template>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="onSubmit" :loading="submitting">
        {{ action === 'complete' ? '确认完成' : '转返修' }}
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { post } from '@/utils/request'
import { unwrapItem } from '@/utils/response'

const props = defineProps<{
  modelValue: boolean
  wo: Record<string, unknown> | null
}>()
const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'done'): void
}>()

const visible = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const formRef = ref()
const convertFormRef = ref()
const submitting = ref(false)
const action = ref<'complete' | 'convert'>('complete')

// ---- 情景A: 现场处理 ----
const form = ref({
  result_notes: '',
  service_fee: 0,
  parts_cost: 0,
  payment_method: 'receivable',
  customer_signature: '',
})

const rules = {
  result_notes: [{ required: true, message: '请填写处理结果', trigger: 'blur' }],
}

// ---- 情景B: 转返修 ----
const convertForm = ref({
  reason: '',
  method_type: 'paid_repair',
  expected_finish_at: '',
  equipment_model: '',
  serial_no: '',
})

const convertRules = {
  reason: [{ required: true, message: '请填写返修原因', trigger: 'blur' }],
}

// ---- 签字板 (上门服务) ----
const sigCanvas = ref<HTMLCanvasElement | null>(null)
const hasSignature = ref(false)
let isDrawing = false

const startDraw = (e: MouseEvent) => {
  isDrawing = true
  const ctx = sigCanvas.value?.getContext('2d')
  if (!ctx) return
  ctx.beginPath()
  ctx.moveTo(e.offsetX, e.offsetY)
}
const draw = (e: MouseEvent) => {
  if (!isDrawing) return
  const ctx = sigCanvas.value?.getContext('2d')
  if (!ctx) return
  ctx.lineTo(e.offsetX, e.offsetY)
  ctx.strokeStyle = '#000'
  ctx.lineWidth = 2
  ctx.stroke()
  hasSignature.value = true
}
const stopDraw = () => { isDrawing = false }
const triggerSignature = () => {}
const clearSignature = () => {
  const canvas = sigCanvas.value
  if (!canvas) return
  canvas.getContext('2d')?.clearRect(0, 0, canvas.width, canvas.height)
  hasSignature.value = false
  form.value.customer_signature = ''
}

const resetForm = () => {
  form.value = { result_notes: '', service_fee: 0, parts_cost: 0, payment_method: 'receivable', customer_signature: '' }
  convertForm.value = { reason: '', method_type: 'paid_repair', expected_finish_at: '', equipment_model: '', serial_no: '' }
  action.value = 'complete'
  formRef.value?.clearValidate()
}

// ---- 提交 ----
const onSubmit = async () => {
  if (action.value === 'complete') {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return
    submitting.value = true
    try {
      const payload: Record<string, unknown> = {
        result_notes: form.value.result_notes,
        service_fee: form.value.service_fee,
        parts_cost: form.value.parts_cost,
        payment_method: form.value.payment_method,
      }
      if (hasSignature.value && sigCanvas.value) {
        payload.customer_signature = sigCanvas.value.toDataURL('image/png')
      }
      await post(`/work-orders/${props.wo?.id}/resolve`, payload)
      ElMessage.success('工单已完成')
      visible.value = false
      emit('done')
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
               || (e as { message?: string })?.message
               || '操作失败'
      ElMessage.error(msg)
    } finally { submitting.value = false }
  } else {
    const valid = await convertFormRef.value?.validate().catch(() => false)
    if (!valid) return
    submitting.value = true
    try {
      await post(`/work-orders/${props.wo?.id}/convert-to-repair`, convertForm.value)
      ElMessage.success('已转返修处理')
      visible.value = false
      emit('done')
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
               || (e as { message?: string })?.message
               || '操作失败'
      ElMessage.error(msg)
    } finally { submitting.value = false }
  }
}
</script>

<style scoped lang="scss">
.action-selector {
  display: flex;
  justify-content: center;
  margin-bottom: 8px;
  :deep(.el-radio-button__inner) { font-size: 15px; padding: 10px 24px; }
}
.signature-pad {
  border: 2px dashed #d9d9d9;
  border-radius: 8px;
  cursor: crosshair;
  position: relative;
  canvas { display: block; }
  .signature-hint {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    color: #bbb;
    font-size: 14px;
    pointer-events: none;
  }
}
.sig-actions {
  display: flex; align-items: center; gap: 12px; margin-top: 4px;
  .sig-warning { color: #E6A23C; font-size: 13px; }
}
</style>
