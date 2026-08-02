<template>
  <el-dialog v-model="visible" title="交付客户" width="700px" destroy-on-close>
    <el-form :model="form" ref="formRef" :rules="rules" label-width="120px">
      <el-form-item label="交付方式" prop="delivery_method">
        <el-radio-group v-model="form.delivery_method">
          <el-radio value="return_to_work_order">返回维修单</el-radio>
          <el-radio value="customer_pickup">客户自取</el-radio>
          <el-radio value="courier">快递邮寄</el-radio>
        </el-radio-group>
      </el-form-item>

      <!-- 返回维修单 / 自取 → 仅填备注 -->
      <el-form-item v-if="form.delivery_method !== 'courier'" label="备注">
        <el-input v-model="form.note" type="textarea" :rows="3" maxlength="500" :placeholder="form.delivery_method === 'return_to_work_order' ? '维修完成，已返回工单处理' : '客户已自取'" />
      </el-form-item>

      <!-- 快递邮寄 → 填物流信息 + 上传签收单 -->
      <template v-if="form.delivery_method === 'courier'">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="快递公司" prop="carrier">
              <el-select v-model="form.carrier" placeholder="选择快递公司" style="width:100%">
                <el-option value="顺丰速运" label="顺丰速运" />
                <el-option value="中通快递" label="中通快递" />
                <el-option value="圆通速递" label="圆通速递" />
                <el-option value="韵达快递" label="韵达快递" />
                <el-option value="京东物流" label="京东物流" />
                <el-option value="德邦物流" label="德邦物流" />
                <el-option value="其他" label="其他" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="运单号" prop="tracking_no">
              <el-input v-model="form.tracking_no" placeholder="填写运单号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="签收单">
          <el-upload
            ref="uploadRef"
            :auto-upload="false"
            accept="image/*"
            :on-change="onFileChange"
            :limit="1"
            :on-exceed="() => ElMessage.warning('只能上传一张签收单')"
          >
            <el-button type="primary" size="small">上传签收单照片</el-button>
            <template #tip><span class="muted" style="margin-left:8px">jpg/png，客户签收后的单据拍照上传</span></template>
          </el-upload>
          <div v-if="form.customer_signature" class="preview-wrap">
            <el-image :src="form.customer_signature" style="max-width:300px;max-height:150px;margin-top:8px;border:1px solid #e8e8e8;border-radius:4px" />
            <el-button size="small" link type="danger" @click="clearFile">删除</el-button>
          </div>
        </el-form-item>
      </template>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="success" @click="onSubmit" :loading="submitting">确认交付</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { post } from '@/utils/request'

const props = defineProps<{ modelValue: boolean; ro: Record<string, unknown> | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: boolean): void; (e: 'done'): void }>()
const visible = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const formRef = ref()
const uploadRef = ref()
const submitting = ref(false)

const form = ref({
  delivery_method: 'customer_pickup',
  carrier: '',
  tracking_no: '',
  note: '',
  customer_signature: '',
})

const rules = {
  delivery_method: [{ required: true, message: '请选择交付方式', trigger: 'change' }],
  carrier: [{ required: true, message: '请选择快递公司', trigger: 'change' }],
  tracking_no: [{ required: true, message: '请填写运单号', trigger: 'blur' }],
}

const onFileChange = (file: { raw?: File }) => {
  if (!file.raw) return
  const reader = new FileReader()
  reader.onload = (e) => { form.value.customer_signature = (e.target?.result as string) || '' }
  reader.readAsDataURL(file.raw)
}
const clearFile = () => { form.value.customer_signature = ''; uploadRef.value?.clearFiles() }

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid || !props.ro) return
  submitting.value = true
  try {
    const payload: Record<string, unknown> = {
      delivery_method: form.value.delivery_method,
      note: form.value.note || `已交付客户（${form.value.delivery_method === 'return_to_work_order' ? '返回工单' : form.value.delivery_method === 'customer_pickup' ? '客户自取' : '快递邮寄'}）`,
    }
    if (form.value.delivery_method === 'courier') {
      payload.carrier = form.value.carrier
      payload.tracking_no = form.value.tracking_no
      if (form.value.customer_signature) {
        payload.customer_signature = form.value.customer_signature
      }
    }
    await post(`/repair-orders/${props.ro.id}/close`, payload)
    ElMessage.success('已交付客户')
    visible.value = false
    emit('done')
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message || '操作失败'
    ElMessage.error(msg)
  } finally { submitting.value = false }
}
</script>

<style scoped lang="scss">
</style>
