<template>
  <el-dialog
    v-model="visible"
    :title="mode === 'edit' ? '编辑应付' : '新增应付'"
    width="560px"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
      <el-form-item label="供应商" prop="supplier_id">
        <el-select v-model="form.supplier_id" placeholder="请选择供应商" filterable style="width: 100%">
          <el-option v-for="s in suppliers" :key="s.id" :label="s.name" :value="s.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="关联项目">
        <el-select v-model="form.project_id" placeholder="请选择项目" filterable clearable style="width: 100%">
          <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="应付金额" prop="amount">
        <el-input-number v-model="form.amount" :min="0" :precision="2" style="width: 100%" />
      </el-form-item>
      <el-form-item label="已付金额">
        <el-input-number v-model="form.paid_amount" :min="0" :precision="2" style="width: 100%" />
      </el-form-item>
      <el-form-item label="到期日" prop="due_date">
        <el-date-picker v-model="form.due_date" type="date" placeholder="选择到期日" style="width: 100%" value-format="YYYY-MM-DD" />
      </el-form-item>
      <el-form-item label="付款条件">
        <el-input v-model="form.payment_term" placeholder="如：月结30天" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="loading" @click="emit('submit')">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PayableForm } from './types'

// v0.3.25 抽自 finance/Payable.vue:166-199
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  form: PayableForm
  loading: boolean
  suppliers: { id: number; name: string }[]
  projects: { id: number; name: string }[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'submit'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const rules = {
  supplier_id: [{ required: true, message: '请选择供应商', trigger: 'change' }],
  amount: [{ required: true, message: '请输入应付金额', trigger: 'blur' }],
  due_date: [{ required: true, message: '请选择到期日', trigger: 'change' }],
}
</script>

<style lang="scss" scoped></style>
