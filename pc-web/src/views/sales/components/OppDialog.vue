<template>
  <el-dialog
    :model-value="visible"
    :title="mode === 'create' ? '新建商机' : '编辑商机'"
    width="1500px"
    :close-on-click-modal="false"
    @update:model-value="$emit('update:visible', $event)"
  >
    <el-form
      ref="formRef"
      :model="formData"
      :rules="formRules"
      label-width="100px"
    >
      <el-row :gutter="16">
        <el-col :span="16">
          <el-form-item label="商机名称" prop="name">
            <el-input v-model="formData.name" placeholder="如：XX 银行监控改造" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="预计金额" prop="estimated_amount">
            <el-input-number
              v-model="formData.estimated_amount"
              :min="0"
              :step="1000"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="客户" prop="customer_id">
            <el-select
              v-model="formData.customer_id"
              placeholder="请选择客户"
              filterable
              style="width: 100%"
            >
              <el-option
                v-for="c in customerOptions"
                :key="c.id"
                :label="c.name"
                :value="c.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="推荐人">  <!-- V1.2.5 -->
            <el-select v-model="formData.referrer_id" placeholder="无(自拓)" clearable filterable style="width: 100%">
              <el-option v-for="r in referrerOptions" :key="r.id" :label="`${r.name} (${r.commission_rate}%)`" :value="r.id" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="销售" prop="sales_id">
            <el-select v-model="formData.sales_id" placeholder="请选择" filterable style="width: 100%">
              <el-option v-for="u in userOptions" :key="u.id" :label="u.name" :value="u.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="售前" prop="presale_id">
            <el-select v-model="formData.presale_id" placeholder="请选择" filterable style="width: 100%">
              <el-option v-for="u in userOptions" :key="u.id" :label="u.name" :value="u.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="预计签约日">
            <el-date-picker
              v-model="formData.expected_sign_date"
              type="date"
              placeholder="选择日期"
              style="width: 100%"
              value-format="YYYY-MM-DD"
            />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="备注">
        <el-input v-model="formData.notes" type="textarea" :rows="3" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="$emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSave">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { FormInstance, FormRules } from 'element-plus'
import type { Opportunity } from '../types'

export interface OppFormData {
  id: number
  name: string
  customer_id: number | null
  estimated_amount: number
  sales_id: number | null
  presale_id: number | null
  expected_sign_date: string
  referrer_id: number | null  // V1.2.5: 推荐人 (E2E 阶段4 BUG 修复)
  notes: string
}

const props = defineProps<{
  visible: boolean
  mode: 'create' | 'edit'
  submitting: boolean
  customerOptions: { id: number; name: string }[]
  userOptions: { id: number; name: string }[]
  referrerOptions: { id: number; name: string; commission_rate: number }[]  // V1.2.5
  target: Opportunity | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'save', data: OppFormData, mode: 'create' | 'edit'): void
}>()

const formRef = ref<FormInstance>()
const formData = reactive<OppFormData>({
  id: 0,
  name: '',
  customer_id: null,
  estimated_amount: 0,
  sales_id: null,
  presale_id: null,
  expected_sign_date: '',
  referrer_id: null,  // V1.2.5
  notes: '',
})

const formRules: FormRules = {
  name: [{ required: true, message: '请输入商机名称', trigger: 'blur' }],
  customer_id: [{ required: true, message: '请选择客户', trigger: 'change' }],
  estimated_amount: [{ required: true, message: '请输入预计金额', trigger: 'blur' }],
  sales_id: [{ required: true, message: '请选择销售', trigger: 'change' }],
  presale_id: [{ required: true, message: '请选择售前', trigger: 'change' }],
}

const resetForm = () => {
  Object.assign(formData, {
    id: 0, name: '', customer_id: null, estimated_amount: 0,
    sales_id: null, presale_id: null, expected_sign_date: '', referrer_id: null, notes: '',
  })
}

watch(
  () => props.target,
  (row) => {
    if (!row) return
    if (props.mode === 'edit') {
      Object.assign(formData, {
        id: row.id,
        name: row.name || '',
        customer_id: row.customer_id || null,
        estimated_amount: Number(row.estimated_amount || 0),
        sales_id: row.sales_id || null,
        presale_id: row.presale_id || null,
        expected_sign_date: row.expected_sign_date || '',
        referrer_id: row.referrer_id || null,  // V1.2.5
        notes: row.notes || '',
      })
    } else {
      resetForm()
    }
  },
  { immediate: true },
)

const handleSave = async () => {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  emit('save', { ...formData }, props.mode)
}
</script>
