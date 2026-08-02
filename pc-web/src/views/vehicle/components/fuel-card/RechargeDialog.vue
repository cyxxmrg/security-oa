<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="记录充值" width="1440px" destroy-on-close
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="油卡" prop="card_id">
            <el-select v-model="form.card_id" placeholder="选择油卡" filterable style="width: 100%">
              <el-option v-for="c in cardList" :key="c.id" :label="`${c.card_no} (${c.card_name || '-'}) - 余 ¥${formatMoney(c.balance ?? 0)}`" :value="c.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="充值金额" prop="amount">
            <el-input-number v-model="form.amount" :min="0.01" :precision="2" :step="100" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="充值日期" prop="recharge_date">
            <el-date-picker v-model="form.recharge_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="支付方式">
            <el-select v-model="form.payment_method" placeholder="选择" style="width: 100%">
              <el-option v-for="m in payMethods" :key="m" :label="m" :value="m" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col v-if="form.payment_method === '银行转账'" :span="8">
          <el-form-item label="资金账户">
            <el-select v-model="form.account_id" placeholder="选择付款账户" filterable clearable style="width: 100%">
              <el-option v-for="a in accounts" :key="a.id" :label="`${a.name}（余额 ¥${formatMoney(a.balance)}）`" :value="a.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="经办人">
            <el-input v-model="form.operator" placeholder="选填" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="凭证号">
            <el-input v-model="form.voucher_no" placeholder="选填" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="备注">
        <el-input v-model="form.notes" type="textarea" :rows="2" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('submit')">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { FormRules } from 'element-plus'
import { FuelCard } from '../../types'

export interface AccountOpt { id: number; name: string; balance: number }

export interface RechargeForm {
  card_id: number | null
  amount: number
  recharge_date: string
  payment_method: string
  account_id: number | null
  operator: string
  voucher_no: string
  notes: string
}

defineProps<{
  visible: boolean
  form: RechargeForm
  rules: FormRules
  cardList: FuelCard[]
  payMethods: string[]
  accounts: AccountOpt[]
  submitting: boolean
  formatMoney: (n: number | string) => string
}>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit'): void
}>()
const formRef = ref()
defineExpose({ formRef })
</script>
