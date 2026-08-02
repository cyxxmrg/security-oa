<template>
  <el-dialog v-model="visible" title="添加维修方式" width="700px" destroy-on-close>
    <el-form :model="form" ref="formRef" :rules="rules" label-width="120px">
      <el-form-item label="维修方式" prop="method_type">
        <el-radio-group v-model="form.method_type">
          <el-radio-button value="free_warranty">免费维修</el-radio-button>
          <el-radio-button value="paid_repair">付费维修</el-radio-button>
        </el-radio-group>
      </el-form-item>

      <!-- 付费维修 → 付款方式 -->
      <el-form-item v-if="form.method_type === 'paid_repair'" label="付款方式" prop="payment_mode">
        <el-radio-group v-model="form.payment_mode">
          <el-radio value="cash">现金付费</el-radio>
          <el-radio value="payable">应付计费（同步供应商应付账款）</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="预估费用">
            <el-input-number v-model="form.estimated_cost" :min="0" :precision="2" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="实际费用">
            <el-input-number v-model="form.actual_cost" :min="0" :precision="2" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="工时(小时)">
        <el-input-number v-model="form.hours_spent" :min="0" :step="0.5" style="width:100%" />
      </el-form-item>

      <el-form-item label="备注">
        <el-input v-model="form.remarks" type="textarea" :rows="2" maxlength="500" />
      </el-form-item>

      <!-- 应付计费 → 选择供应商 -->
      <el-form-item v-if="form.payment_mode === 'payable'" label="供应商" prop="supplier_id">
        <el-select v-model="form.supplier_id" filterable placeholder="选择供应商" style="width:100%">
          <el-option v-for="s in suppliers" :key="s.id" :label="s.name" :value="s.id" />
        </el-select>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="onSubmit" :loading="submitting">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList } from '@/utils/response'

const props = defineProps<{ modelValue: boolean; ro: Record<string, unknown> | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: boolean): void; (e: 'done'): void }>()
const visible = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const formRef = ref()
const submitting = ref(false)
const suppliers = ref<{ id: number; name: string }[]>([])

const form = ref({
  method_type: 'free_warranty',
  payment_mode: 'cash',
  estimated_cost: 0,
  actual_cost: 0,
  hours_spent: 0,
  remarks: '',
  supplier_id: null as number | null,
})

const rules = {
  method_type: [{ required: true, message: '请选择维修方式', trigger: 'change' }],
}

const loadSuppliers = async () => {
  try {
    const res = await get('/suppliers', { per_page: 200 })
    const list = unwrapList(res)
    suppliers.value = list.map((s: Record<string, unknown>) => ({ id: Number(s.id), name: String(s.name || '') }))
  } catch { suppliers.value = [] }
}

watch(() => props.modelValue, (v) => { if (v) loadSuppliers() })

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid || !props.ro) return
  submitting.value = true
  try {
    const payload: Record<string, unknown> = {
      method_type: form.value.method_type,
      estimated_cost: form.value.estimated_cost,
      actual_cost: form.value.actual_cost,
      hours_spent: form.value.hours_spent,
      remarks: form.value.remarks,
      is_paid: form.value.method_type === 'paid_repair',
    }
    // 应付计费 → 生成供应商应付账款
    if (form.value.payment_mode === 'payable') {
      payload.generate_payable = true
      payload.supplier_id = form.value.supplier_id
    }
    await post(`/repair-orders/${props.ro.id}/methods`, payload)
    ElMessage.success('已添加维修方式')
    visible.value = false
    emit('done')
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message || '操作失败'
    ElMessage.error(msg)
  } finally { submitting.value = false }
}
</script>
