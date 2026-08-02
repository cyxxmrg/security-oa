<template>
  <div class="flow-history">
    <div v-if="loading" class="loading-hint">
      <el-icon class="is-loading"><Loading /></el-icon>
      加载流转记录...
    </div>
    <el-timeline v-else-if="history.length > 0">
      <el-timeline-item
        v-for="(item, idx) in history"
        :key="idx"
        :type="getTimelineType(item.action)"
        :timestamp="formatTime(item.created_at)"
        placement="top"
      >
        <div class="history-card">
          <div class="history-header">
            <el-tag :type="getActionTagType(item.action)" size="small" effect="plain">
              {{ actionLabel(item.action) }}
            </el-tag>
            <span class="history-entity">{{ entityLabel(item.entity_type) }} #{{ item.entity_id }}</span>
          </div>
          <div class="history-flow">
            <span class="from-status">{{ statusLabel(item.from_status) }}</span>
            <el-icon><ArrowRight /></el-icon>
            <span class="to-status">{{ statusLabel(item.to_status) }}</span>
          </div>
          <div v-if="item.remark" class="history-remark">{{ item.remark }}</div>
          <div v-if="item.operator" class="history-operator">操作人: {{ item.operator }}</div>
        </div>
      </el-timeline-item>
    </el-timeline>
    <el-empty v-else description="暂无流转记录" :image-size="60" />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Loading, ArrowRight } from '@element-plus/icons-vue'
import { purchaseFlow } from '@/api/purchase-flow'
import { unwrapItem } from '@/utils/response'

const props = defineProps<{
  entityType: string
  entityId: number
}>()

const loading = ref(false)
const history = ref<Record<string, unknown>[]>([])

const loadHistory = async () => {
  if (!props.entityId) return
  loading.value = true
  try {
    // V0.6.3: res = {code, data: <history>} — 可能是 array 或 {logs:[]}
    const res = await purchaseFlow.trace(props.entityType, props.entityId)
    const d = unwrapItem(res)
    const arr = Array.isArray(d) ? d : (Array.isArray(d?.logs) ? d.logs : [])
    history.value = arr
  } catch {
    history.value = []
  } finally {
    loading.value = false
  }
}

watch(() => [props.entityType, props.entityId], loadHistory, { immediate: true })

const actionLabel = (action: string) => {
  const m: Record<string, string> = {
    submit: '提交', approve: '审批通过', reject: '驳回', create: '创建',
    sign: '签署', execute: '执行', ship: '发货', arrive: '到货',
    inbound: '入库', confirm: '确认', update: '更新',
  }
  return m[action] || action
}

const entityLabel = (type: string) => {
  const m: Record<string, string> = {
    requirement: '需求', plan: '计划', order: '采购单',
    contract: '合同', payment_request: '付款申请', payment: '付款',
    shipment: '收货', inbound: '入库',
  }
  return m[type] || type
}

const statusLabel = (status: string | null) => {
  if (!status) return '—'
  const m: Record<string, string> = {
    pending: '待处理', approved: '已通过', rejected: '已驳回',
    merged: '已合并', fulfilled: '已完成', cancelled: '已取消',
    submitted: '已提交', draft: '草稿', active: '生效',
    signed: '已签署', paid: '已付款', shipped: '已发货',
    in_transit: '运输中', arrived: '已到货', received: '已收货',
    inspected: '已检验', inbounded: '已入库',
  }
  return m[status] || status
}

const getTimelineType = (action: string) => {
  if (action === 'approve' || action === 'sign' || action === 'execute') return 'success'
  if (action === 'reject') return 'danger'
  if (action === 'create' || action === 'submit') return 'primary'
  return 'info'
}

const getActionTagType = (action: string) => {
  if (action === 'approve' || action === 'sign') return 'success'
  if (action === 'reject') return 'danger'
  if (action === 'create' || action === 'submit') return ''
  return 'info'
}

const formatTime = (t: string) => {
  if (!t) return ''
  return t.replace('T', ' ').slice(0, 19)
}
</script>

<style lang="scss" scoped>
.flow-history {
  padding: 12px 0;

  .loading-hint {
    display: flex; align-items: center; gap: 8px;
    color: #909399; font-size: 13px; padding: 20px 0;
  }

  .history-card {
    .history-header {
      display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
    }
    .history-entity { font-size: 12px; color: #909399; }
    .history-flow {
      display: flex; align-items: center; gap: 8px; margin-bottom: 4px;
      .from-status { color: #909399; font-size: 13px; }
      .to-status { color: #0c447c; font-size: 13px; font-weight: 600; }
    }
    .history-remark { font-size: 12px; color: #606266; margin-bottom: 2px; }
    .history-operator { font-size: 11px; color: #c0c4cc; }
  }
}
</style>
