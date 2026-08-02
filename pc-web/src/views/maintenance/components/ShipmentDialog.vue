<template>
  <el-dialog v-model="visible" title="发货" width="600px" destroy-on-close>
    <el-form :model="form" ref="formRef" :rules="rules" label-width="100px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="承运商" prop="carrier">
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
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="发件人" prop="sender_name">
            <el-input v-model="form.sender_name" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="发件人电话">
            <el-input v-model="form.sender_phone" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="收件人" prop="receiver_name">
            <el-input v-model="form.receiver_name" :placeholder="ro?.contact_name || ''" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="收件人电话">
            <el-input v-model="form.receiver_phone" :placeholder="ro?.contact_phone || ''" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="收件地址" prop="receiver_address">
        <el-input v-model="form.receiver_address" :placeholder="ro?.address || ''" />
      </el-form-item>
      <el-form-item label="费用">
        <el-input-number v-model="form.cost" :min="0" :precision="2" style="width:100%" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="onSubmit" :loading="submitting">确认发货</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { post } from '@/utils/request'

const props = defineProps<{ modelValue: boolean; ro: Record<string, unknown> | null; direction: 'outbound' | 'inbound' }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: boolean): void; (e: 'done'): void }>()
const visible = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const formRef = ref()
const submitting = ref(false)
const form = ref({ carrier: '', tracking_no: '', sender_name: '', sender_phone: '', receiver_name: '', receiver_phone: '', receiver_address: '', cost: 0 })
const rules = {
  carrier: [{ required: true, message: '请选择承运商', trigger: 'change' }],
  tracking_no: [{ required: true, message: '请填写运单号', trigger: 'blur' }],
  sender_name: [{ required: true, message: '请填写发件人', trigger: 'blur' }],
  receiver_name: [{ required: true, message: '请填写收件人', trigger: 'blur' }],
  receiver_address: [{ required: true, message: '请填写收件地址', trigger: 'blur' }],
}

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid || !props.ro) return
  submitting.value = true
  try {
    const payload = {
      carrier: form.value.carrier, tracking_no: form.value.tracking_no,
      sender_name: form.value.sender_name, sender_phone: form.value.sender_phone,
      receiver_name: form.value.receiver_name, receiver_phone: form.value.receiver_phone,
      receiver_address: form.value.receiver_address,
      cost: form.value.cost || 0,
    }
    // 去程用 ship-out (自动改状态), 回程用 shipments (仅记录)
    const url = props.direction === 'outbound'
      ? `/repair-orders/${props.ro.id}/ship-out`
      : `/repair-orders/${props.ro.id}/shipments`
    await post(url, payload)
    ElMessage.success('已记录物流信息')
    visible.value = false
    emit('done')
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message || '操作失败'
    ElMessage.error(msg)
  } finally { submitting.value = false }
}
</script>
