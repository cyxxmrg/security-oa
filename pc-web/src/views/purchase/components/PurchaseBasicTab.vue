<template>
  <div v-loading="loadingBasic" class="tab-content">
    <el-descriptions :column="2" border>
      <el-descriptions-item label="PO 编号">{{ order.code || order.po_no || '-' }}</el-descriptions-item>
      <el-descriptions-item label="状态">
        <el-tag :type="orderStatusTagType(order.status)" effect="plain" size="small">{{ orderStatusLabel(order.status) }}</el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="标题">{{ order.title || '-' }}</el-descriptions-item>
      <el-descriptions-item label="路径">{{ order.path || '-' }}</el-descriptions-item>
      <el-descriptions-item label="金额">¥ {{ formatMoney(order.total_amount) }}</el-descriptions-item>
      <el-descriptions-item label="供应商">{{ order.supplier?.name || `#${order.supplier_id}` }}</el-descriptions-item>
      <el-descriptions-item label="上游计划">#{{ order.plan_id || '-' }}</el-descriptions-item>
      <el-descriptions-item label="来源需求">#{{ order.source_requirement_id || '-' }}</el-descriptions-item>
      <!-- V0.6.4 招标联动: 来源招标 (可点击跳转) -->
      <el-descriptions-item label="来源招标">
        <span v-if="order.tender">
          <el-link type="warning" @click="$emit('go-tender', order.tender.id)">
            {{ order.tender.code }} · {{ order.tender.name }}
          </el-link>
        </span>
        <span v-else-if="order.tender_id">
          <el-link type="warning" @click="$emit('go-tender', order.tender_id)">
            #{{ order.tender_id }} (来源招标)
          </el-link>
        </span>
        <span v-else class="muted">-</span>
      </el-descriptions-item>
      <el-descriptions-item label="下游合同">#{{ order.contract_id || '-' }}</el-descriptions-item>
      <el-descriptions-item label="创建人">#{{ order.created_by || '-' }}</el-descriptions-item>
      <el-descriptions-item label="创建时间">{{ order.created_at || '-' }}</el-descriptions-item>
      <el-descriptions-item label="审批时间">{{ order.approved_at || '-' }}</el-descriptions-item>
      <el-descriptions-item label="备注" :span="2">{{ order.notes || '-' }}</el-descriptions-item>
    </el-descriptions>
  </div>
</template>

<script setup lang="ts">

defineProps<{
  order: Record<string, unknown>
  loadingBasic: boolean
}>()

defineEmits<{
  'go-tender': [tenderId: number]
}>()

const formatMoney = (n: number) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const STATUS_LABELS: Record<string, string> = { draft: '草稿', pending: '待审批', approved: '已审批', fulfilled: '已完成', rejected: '已驳回', cancelled: '已取消' }
const STATUS_TYPES: Record<string, string> = { draft: 'info', pending: 'warning', approved: 'success', fulfilled: 'success', rejected: 'danger', cancelled: 'info' }
const orderStatusLabel = (s: string): string => STATUS_LABELS[s] || s || '-'
const orderStatusTagType = (s: string): string => STATUS_TYPES[s] || ''
</script>

<style scoped>
.tab-content { padding: 8px 4px; }
.muted { color: #999; }
</style>
