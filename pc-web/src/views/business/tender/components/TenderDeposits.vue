<template>
  <div>
    <div class="deposit-rule-section">
      <h4 class="block-title">保证金规则</h4>
      <el-card shadow="never" class="rule-card">
        <div v-if="depositRule">
          <el-descriptions :column="3" border>
            <el-descriptions-item label="是否必缴">
              <el-tag :type="depositRule.required ? 'success' : 'info'" size="small">
                {{ depositRule.required ? '是' : '否' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="金额">¥ {{ Number(depositRule.amount || 0).toLocaleString() }}</el-descriptions-item>
            <el-descriptions-item label="缴款截止">距开标 {{ depositRule.deadline_hours_before_open }} 小时</el-descriptions-item>
            <el-descriptions-item label="自动退款">未中标方 {{ depositRule.refund_policy?.auto_refund_days ?? 7 }} 天内</el-descriptions-item>
            <el-descriptions-item label="合同期限">中标方 {{ depositRule.refund_policy?.forfeit_on_no_contract_sign_days ?? 14 }} 天内签合同</el-descriptions-item>
            <el-descriptions-item label="银行账户">{{ depositRule.bank_account || '-' }}</el-descriptions-item>
            <el-descriptions-item label="备注" :span="3">{{ depositRule.note || '-' }}</el-descriptions-item>
          </el-descriptions>
        </div>
        <div v-else class="muted">尚未设置保证金规则</div>
        <div class="rule-actions">
          <el-button v-if="canEditDepositRule" type="primary" plain @click="$emit('edit-rule')">
            {{ depositRule ? '修改规则' : '设置规则' }}
          </el-button>
        </div>
      </el-card>
    </div>

    <h4 class="block-title">保证金缴纳记录</h4>
    <div class="deposit-stats" v-if="depositSummary.total > 0">
      <el-row :gutter="12">
        <el-col :span="4"><div class="stat-mini"><span class="label">总数</span><span class="value">{{ depositSummary.total }}</span></div></el-col>
        <el-col :span="4"><div class="stat-mini success"><span class="label">已缴</span><span class="value">{{ depositSummary.paid }}</span></div></el-col>
        <el-col :span="4"><div class="stat-mini warning"><span class="label">待缴</span><span class="value">{{ depositSummary.pending }}</span></div></el-col>
        <el-col :span="4"><div class="stat-mini info"><span class="label">已退</span><span class="value">{{ depositSummary.refunded }}</span></div></el-col>
        <el-col :span="4"><div class="stat-mini danger"><span class="label">已没收</span><span class="value">{{ depositSummary.forfeited }}</span></div></el-col>
        <el-col :span="4"><div class="stat-mini"><span class="label">已缴金额</span><span class="value">¥{{ Number(depositSummary.total_paid_amt || 0).toLocaleString() }}</span></div></el-col>
      </el-row>
    </div>

    <el-table :data="deposits" border stripe v-loading="loadingDeposits" empty-text="暂无保证金记录" style="margin-top:12px">
      <el-table-column label="供应商" min-width="180">
        <template #default="{ row }">
          <strong>{{ row.supplier?.name }}</strong>
          <span v-if="row.supplier?.contact_name" class="muted" style="margin-left:6px">{{ row.supplier.contact_name }}</span>
        </template>
      </el-table-column>
      <el-table-column label="金额" width="120" align="right">
        <template #default="{ row }">¥ {{ Number(row.amount || 0).toLocaleString() }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="depositStatusTag(row.status)" effect="dark">{{ row.status_label || row.status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="缴纳时间" width="160">
        <template #default="{ row }">
          {{ fmt(row.paid_at) }}
          <span v-if="row.markedPaidByUser" class="muted" style="font-size:12px"> ({{ row.markedPaidByUser.name }})</span>
        </template>
      </el-table-column>
      <el-table-column label="退款金额" width="120" align="right">
        <template #default="{ row }">{{ row.refund_amount ? '¥ ' + Number(row.refund_amount).toLocaleString() : '-' }}</template>
      </el-table-column>
      <el-table-column label="退款时间" width="160">
        <template #default="{ row }">{{ fmt(row.refunded_at) }}</template>
      </el-table-column>
      <el-table-column label="原因/备注" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">
          <span>{{ row.refund_reason || row.forfeit_reason || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="240" fixed="right">
        <template #default="{ row }">
          <el-button v-if="row.status === 'pending'" link type="success" @click="$emit('mark-paid', row)">确认收款</el-button>
          <el-button v-if="row.status === 'paid'" link type="primary" @click="$emit('refund', row)">退还</el-button>
          <el-button v-if="['paid', 'pending'].includes(row.status)" link type="danger" @click="$emit('forfeit', row)">没收</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import type { TenderDeposit, TenderDepositRule } from '@/api/tender'
import { depositStatusTag, fmt } from '../utils'

defineProps<{
  depositRule: TenderDepositRule | null
  deposits: TenderDeposit[]
  depositSummary: Record<string, unknown>
  loadingDeposits: boolean
  canEditDepositRule: boolean
}>()

defineEmits<{
  'edit-rule': []
  'mark-paid': [row: TenderDeposit]
  refund: [row: TenderDeposit]
  forfeit: [row: TenderDeposit]
}>()
</script>

<style scoped>
.muted { color: #999; }
.block-title { margin: 16px 0 8px; font-size: 14px; font-weight: 600; }
.deposit-rule-section { margin-bottom: 16px; }
.rule-card { padding: 8px 0; }
.rule-actions { margin-top: 12px; text-align: right; }
.deposit-stats { margin-bottom: 12px; }
.stat-mini { padding: 12px; border-radius: 6px; background: #f5f7fa; border-left: 3px solid #409EFF; }
.stat-mini.success { border-left-color: #67C23A; }
.stat-mini.warning { border-left-color: #E6A23C; }
.stat-mini.danger { border-left-color: #F56C6C; }
.stat-mini.info { border-left-color: #909399; }
.stat-mini .label { font-size: 12px; color: #666; }
.stat-mini .value { font-size: 18px; font-weight: 600; color: #303133; display: block; margin-top: 2px; }
</style>
