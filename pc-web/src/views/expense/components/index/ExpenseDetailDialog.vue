<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="报销详情"
    width="900px"
    :close-on-click-modal="false"
  >
    <div v-if="row" v-loading="loading">
      <el-descriptions :column="2" border>
        <el-descriptions-item label="报销单号">{{ row.claim_no }}</el-descriptions-item>
        <el-descriptions-item label="申请人">{{ row.user?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="费用类别">
          <el-tag size="small">{{ row.category_label || expenseCategoryLabel(row.category) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="金额">
          <span style="font-weight:600;color:#0C447C">¥{{ Number(row.total_amount || 0).toFixed(2) }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="关联项目" :span="2">{{ row.project?.name || '无' }}</el-descriptions-item>
        <el-descriptions-item label="审批状态">
          <el-tag :type="statusType(row.status)" size="small">{{ row.status_label || commonStatusLabel(row.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="提交日期">{{ formatDate(row.created_at) }}</el-descriptions-item>
        <el-descriptions-item label="报销事由" :span="2">
          <div style="white-space: pre-wrap;">{{ row.description || '-' }}</div>
        </el-descriptions-item>
        <el-descriptions-item v-if="row.reject_reason" label="驳回原因" :span="2">
          <span style="color: #f56c6c">{{ row.reject_reason }}</span>
        </el-descriptions-item>
        <el-descriptions-item v-if="row.approver" label="审批人">
          {{ row.approver.name }}
        </el-descriptions-item>
        <el-descriptions-item v-if="row.approved_at" label="审批时间">
          {{ formatDate(row.approved_at) }}
        </el-descriptions-item>
        <el-descriptions-item v-if="row.paid_at" label="付款时间" :span="2">
          {{ formatDate(row.paid_at) }} · 付款金额 ¥{{ Number(row.paid_amount || 0).toFixed(2) }}
        </el-descriptions-item>
        <el-descriptions-item label="费用明细" :span="2">
          <el-table :data="row.items || []" border size="small" style="width: 100%">
            <el-table-column type="index" label="#" width="50" align="center" />
            <el-table-column prop="item_date" label="发生日期" width="120" />
            <el-table-column prop="description" label="说明" min-width="180" />
            <el-table-column label="金额" width="120" align="right">
              <template #default="{ row: it }">
                <span style="color:#0C447C;font-weight:500;">¥{{ Number(it.amount || 0).toFixed(2) }}</span>
              </template>
            </el-table-column>
          </el-table>
        </el-descriptions-item>
      </el-descriptions>
    </div>
    <template #footer>
      <el-button @click="emit('update:visible', false)">关闭</el-button>
      <el-button v-if="row && canCancel(row)" type="warning" @click="handleAction('cancel')">撤销</el-button>
      <el-button v-if="row && canDelete(row)" type="danger" @click="handleAction('delete')">删除</el-button>
      <el-button v-if="row && canPay(row)" type="success" @click="handleAction('pay')">标记付款</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { expenseCategoryLabel, statusLabel as commonStatusLabel } from '@/utils/labels'
defineProps<{
  visible: boolean
  row: Record<string, unknown>
  loading: boolean
  statusType: (s: string) => string
  formatDate: (s?: string) => string
  canCancel: (r: Record<string, unknown>) => boolean
  canDelete: (r: Record<string, unknown>) => boolean
  canPay: (r: Record<string, unknown>) => boolean
}>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'action', action: 'cancel' | 'delete' | 'pay'): void
}>()
function handleAction(a: 'cancel' | 'delete' | 'pay') {
  emit('update:visible', false)
  emit('action', a)
}
</script>
