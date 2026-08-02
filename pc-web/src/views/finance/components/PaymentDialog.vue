<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="$emit('update:visible', $event)"
    title="新增付款"
    width="720px"
    :close-on-click-modal="false"
    destroy-on-close
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
      <el-form-item label="供应商">
        <el-input :model-value="form.supplier_name" disabled />
      </el-form-item>
      <el-form-item label="付款金额" prop="amount">
        <el-input-number v-model="form.amount" :min="0.01" :precision="2" style="width: 100%" />
      </el-form-item>
      <el-form-item label="付款日期" prop="payment_date">
        <el-date-picker v-model="form.payment_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
      </el-form-item>
      <el-form-item label="付款方式" prop="method">
        <el-select v-model="form.method" style="width: 100%">
          <el-option label="银行转账" value="bank" />
          <el-option label="现金" value="cash" />
          <el-option label="支付宝" value="alipay" />
          <el-option label="微信" value="wechat" />
          <el-option label="其他" value="other" />
        </el-select>
      </el-form-item>
      <el-form-item label="凭证号">
        <el-input v-model="form.voucher_no" />
      </el-form-item>
      <el-form-item label="操作人">
        <el-input v-model="form.operator" />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="form.remark" type="textarea" :rows="2" />
      </el-form-item>

      <el-divider content-position="left">分摊到应付明细</el-divider>
      <el-table :data="form.allocations" border>
        <el-table-column label="选择" width="60" align="center">
          <template #default="{ row }">
            <el-checkbox v-model="row.selected" @change="onSelectChange(row)" />
          </template>
        </el-table-column>
        <el-table-column prop="ref_no" label="单号" width="140" />
        <el-table-column prop="amount" label="应付金额" width="100" />
        <el-table-column prop="paid_amount" label="已付" width="100" />
        <el-table-column prop="balance" label="未付余额" width="100" />
        <el-table-column label="本次分摊" width="140">
          <template #default="{ row }">
            <el-input-number
              v-model="row.amount_alloc"
              :min="0"
              :max="row.balance"
              :precision="2"
              :disabled="!row.selected"
              size="small"
              style="width: 100%"
            />
          </template>
        </el-table-column>
      </el-table>
    </el-form>
    <template #footer>
      <el-button @click="$emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">提交</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { ledger } from '@/api/ledger'
import { unwrapList } from '@/utils/response'
import type { LedgerRow } from '../types'

const props = defineProps<{
  visible: boolean
  supplierId: number
  supplierName?: string
}>()

const emit = defineEmits<{
  'update:visible': [v: boolean]
  saved: []
}>()

interface PaymentAllocation {
  payable_id: number
  ref_no: string
  amount: number
  paid_amount: number
  balance: number
  selected: boolean
  amount_alloc: number
}

interface PaymentFormState {
  supplier_id: number
  supplier_name: string
  amount: number
  payment_date: string
  method: 'bank' | 'cash' | 'alipay' | 'wechat' | 'other'
  voucher_no: string
  operator: string
  remark: string
  allocations: PaymentAllocation[]
}

const formRef = ref<FormInstance>()
const submitting = ref(false)
const payables = ref<LedgerRow[]>([])

const form = ref<PaymentFormState>({
  supplier_id: 0,
  supplier_name: '',
  amount: 0,
  payment_date: new Date().toISOString().slice(0, 10),
  method: 'bank',
  voucher_no: '',
  operator: '',
  remark: '',
  allocations: [],
})

const rules: FormRules = {
  amount: [{ required: true, message: '请输入金额', trigger: 'blur' }],
  payment_date: [{ required: true, message: '请选择日期', trigger: 'change' }],
  method: [{ required: true, message: '请选择方式', trigger: 'change' }],
}

const totalAlloc = computed(() =>
  form.value.allocations
    .filter(a => a.selected)
    .reduce((s, a) => s + (a.amount_alloc || 0), 0)
)

const onSelectChange = (row: PaymentAllocation) => {
  if (row.selected && !row.amount_alloc) {
    row.amount_alloc = row.balance
  }
  // 同步 form.amount
  form.value.amount = totalAlloc.value
}

const loadPayables = async () => {
  const res = await ledger.getSupplierPayables(props.supplierId, {})
  // V0.6.3: res = {code, data: <items>} 或 {data: paginator}
  const items = unwrapList(res)
  payables.value = items
  form.value.allocations = items
    .filter((p: LedgerRow) => p.status === 'pending' || p.status === 'partial' || p.status === 'overdue')
    .map((p: LedgerRow) => ({
      payable_id: p.id ?? 0,
      ref_no: p.ref_no || `#${p.id}`,
      amount: Number(p.amount),
      paid_amount: Number(p.paid_amount),
      balance: Number(p.balance),
      selected: false,
      amount_alloc: 0,
    }))
}

watch(
  () => props.visible,
  (v) => {
    if (v) {
      form.value.supplier_id = props.supplierId
      form.value.supplier_name = props.supplierName || ''
      form.value.amount = 0
      loadPayables()
    }
  },
)

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()
  const allocs = form.value.allocations
    .filter(a => a.selected && a.amount_alloc > 0)
    .map(a => ({ payable_id: a.payable_id, amount: a.amount_alloc }))
  if (allocs.length === 0) {
    ElMessage.warning('请至少选择一条分摊明细')
    return
  }
  const sum = allocs.reduce((s, a) => s + a.amount, 0)
  if (Math.abs(sum - form.value.amount) > 0.01) {
    ElMessage.warning(`分摊合计 ¥${sum} 与付款金额 ¥${form.value.amount} 不一致`)
    return
  }
  submitting.value = true
  try {
    await ledger.createSupplierPayment({
      supplier_id: form.value.supplier_id,
      amount: form.value.amount,
      payment_date: form.value.payment_date,
      method: form.value.method,
      voucher_no: form.value.voucher_no,
      operator: form.value.operator,
      remark: form.value.remark,
      allocations: allocs,
    })
    ElMessage.success('付款记录已创建')
    emit('update:visible', false)
    emit('saved')
  } finally {
    submitting.value = false
  }
}
</script>
