<template>
  <div class="purchase-flow-history">
    <div class="history-title">
      <el-icon><Clock /></el-icon>
      <span>采购详情</span>
      <el-tag size="small" type="info">{{ totalSteps }} 步</el-tag>
    </div>
    <div v-if="loading" class="loading">
      <el-skeleton :rows="3" animated />
    </div>
    <el-empty v-else-if="!logs.length && !hasEntities" description="暂无流转记录" :image-size="60" />

    <!-- V0.6.3 采购详情：聚合显示 plan/order/contract/payment/shipment 实体 -->
    <div v-if="hasEntities" class="flow-entities">
      <div v-if="entities.plans?.length" class="entity-group">
        <span class="group-label">采购计划</span>
        <div v-for="p in entities.plans" :key="'p-'+p.id" class="entity-card">
          <el-tag size="small" type="primary">计划</el-tag>
          <span class="entity-code">{{ p.code || '#'+p.id }}</span>
          <span class="entity-meta">{{ p.title || '' }}</span>
          <span class="entity-amount">¥ {{ formatMoney(p.total_amount) }}</span>
          <el-tag :type="statusTagType(p.status)" size="small" effect="plain">{{ flowStatusLabel(p.status, 'draft') }}</el-tag>
        </div>
      </div>
      <div v-if="entities.orders?.length" class="entity-group">
        <span class="group-label">采购单</span>
        <div v-for="o in entities.orders" :key="'o-'+o.id" class="entity-card">
          <el-tag size="small" type="warning">订单</el-tag>
          <span class="entity-code">{{ o.code || '#'+o.id }}</span>
          <span class="entity-meta">{{ o.title || '' }}</span>
          <span class="entity-amount">¥ {{ formatMoney(o.total_amount) }}</span>
          <el-tag :type="statusTagType(o.status)" size="small" effect="plain">{{ flowStatusLabel(o.status, 'draft') }}</el-tag>
        </div>
      </div>
      <div v-if="entities.contracts?.length" class="entity-group">
        <span class="group-label">合同</span>
        <div v-for="c in entities.contracts" :key="'c-'+c.id" class="entity-card">
          <el-tag size="small" type="success">合同</el-tag>
          <span class="entity-code">{{ c.code || '#'+c.id }}</span>
          <span class="entity-meta">{{ c.title || '' }}</span>
          <span class="entity-amount">¥ {{ formatMoney(c.total_amount) }}</span>
          <el-tag :type="statusTagType(c.status)" size="small" effect="plain">{{ flowStatusLabel(c.status, 'draft') }}</el-tag>
        </div>
      </div>
      <div v-if="entities.payment_reqs?.length" class="entity-group">
        <span class="group-label">付款申请</span>
        <div v-for="pr in entities.payment_reqs" :key="'pr-'+pr.id" class="entity-card">
          <el-tag size="small" type="info">付款</el-tag>
          <span class="entity-code">{{ pr.code || '#'+pr.id }}</span>
          <span class="entity-meta">{{ pr.stage_label || pr.payment_type || '' }}</span>
          <span class="entity-amount">¥ {{ formatMoney(pr.amount) }}</span>
          <el-tag :type="statusTagType(pr.status)" size="small" effect="plain">{{ flowStatusLabel(pr.status, 'pending') }}</el-tag>
        </div>
      </div>
      <div v-if="entities.payments?.length" class="entity-group">
        <span class="group-label">付款</span>
        <div v-for="pay in entities.payments" :key="'pay-'+pay.id" class="entity-card">
          <el-tag size="small" type="success">已付</el-tag>
          <span class="entity-code">{{ pay.voucher_no || '#'+pay.id }}</span>
          <span class="entity-meta">{{ pay.payment_method || '' }}</span>
          <span class="entity-amount">¥ {{ formatMoney(pay.amount) }}</span>
          <span class="entity-date">{{ (pay.paid_at || '').slice(0, 10) }}</span>
        </div>
      </div>
      <div v-if="entities.shipments?.length" class="entity-group">
        <span class="group-label">收货</span>
        <div v-for="s in entities.shipments" :key="'s-'+s.id" class="entity-card">
          <el-tag size="small" type="warning">收货</el-tag>
          <span class="entity-code">#{{ s.id }}</span>
          <span class="entity-meta">{{ s.carrier || '' }} {{ s.tracking_no || '' }}</span>
          <el-tag :type="statusTagType(s.status)" size="small" effect="plain">{{ flowStatusLabel(s.status, 'pending') }}</el-tag>
        </div>
      </div>
    </div>

    <el-timeline v-if="logs.length">
      <el-timeline-item
        v-for="(log, i) in logs"
        :key="i"
        :type="timelineType(log.action)"
        :timestamp="formatTime(log.created_at)"
        :hollow="i !== 0"
        placement="top"
      >
        <div class="log-row">
          <div class="log-action">
            <span class="from">{{ flowStatusLabel(log.from_status, '初始') }}</span>
            <el-icon><Right /></el-icon>
            <span class="to">{{ flowStatusLabel(log.to_status) }}</span>
          </div>
          <div class="log-meta">
            <span class="operator">{{ log.operator_name || '系统' }}</span>
            <span class="action-tag" :class="`action-${log.action}`">
              {{ actionLabel(log.action) }}
            </span>
            <span class="entity-type">{{ entityLabel(log.entity_type) }}#{{ log.entity_id }}</span>
          </div>
          <div v-if="log.remark" class="log-remark">{{ log.remark }}</div>
        </div>
      </el-timeline-item>
    </el-timeline>
  </div>
</template>

<script setup lang="ts">
import { localizeEnumText } from '@/utils/labels'
import { ref, computed, watch, onMounted } from 'vue'
import { Clock, Right } from '@element-plus/icons-vue'
import { purchaseFlow } from '@/api/purchase-flow'

const props = defineProps<{
  entityType: string
  entityId: number | string
}>()

const logs = ref<Record<string, unknown>[]>([])
const entities = ref<Record<string, unknown>>({})
const loading = ref(false)

const totalSteps = computed(() => {
  const e = entities.value
  return (e.plans?.length || 0) + (e.orders?.length || 0) + (e.contracts?.length || 0) +
         (e.payment_reqs?.length || 0) + (e.payments?.length || 0) + (e.shipments?.length || 0)
})

const hasEntities = computed(() => {
  const e = entities.value
  return (e.plans?.length || e.orders?.length || e.contracts?.length ||
          e.payment_reqs?.length || e.payments?.length || e.shipments?.length)
})

async function load() {
  if (!props.entityId) return
  loading.value = true
  try {
    const r = await purchaseFlow.trace(props.entityType, props.entityId)
    const data = r?.data || r
    logs.value = data?.logs || []
    entities.value = {
      plans:        data?.plans || [],
      orders:       data?.orders || [],
      contracts:    data?.contracts || [],
      payment_reqs: data?.payment_reqs || [],
      payments:     data?.payments || [],
      shipments:    data?.shipments || [],
    }
  } catch (e) {
    console.error('[FlowHistory] load failed', e)
    logs.value = []
    entities.value = {}
  } finally {
    loading.value = false
  }
}

function formatTime(t: string) {
  if (!t) return ''
  const d = new Date(t)
  return d.toLocaleString('zh-CN', { hour12: false })
}

function formatMoney(n: Record<string, unknown>) {
  return Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
}


function flowStatusLabel(status: string, fallback = '-') {
  const value = status || fallback
  return (
    {
      draft: '草稿',
      pending: '待处理',
      submitted: '已提交',
      approved: '已通过',
      rejected: '已驳回',
      cancelled: '已取消',
      signed: '已签署',
      paid: '已付款',
      shipped: '已发货',
      in_transit: '运输中',
      inbounded: '已入库',
      arrived: '已到货',
      fulfilled: '已完成',
      completed: '已完成',
      failed: '失败',
      '初始': '初始',
    } as Record<string, unknown>
  )[value] || localizeEnumText(value)
}

function statusTagType(s: string): 'primary' | 'success' | 'warning' | 'danger' | 'info' {
  if (!s) return 'info'
  if (['approved', 'signed', 'paid', 'inbounded', 'arrived'].includes(s)) return 'success'
  if (['rejected', 'cancelled', 'failed'].includes(s)) return 'danger'
  if (['submitted', 'shipped', 'in_transit', 'pending'].includes(s)) return 'warning'
  if (['draft'].includes(s)) return 'info'
  return 'primary'
}

function timelineType(action: string): 'primary' | 'success' | 'warning' | 'danger' | 'info' {
  if (action === 'approve' || action === 'sign' || action === 'confirm') return 'success'
  if (action === 'reject' || action === 'cancel') return 'danger'
  if (action === 'submit' || action === 'create') return 'primary'
  if (action === 'auto') return 'info'
  return 'info'
}

function actionLabel(a: string) {
  return (
    {
      create: '创建',
      submit: '提交',
      approve: '通过',
      reject: '驳回',
      sign: '签署',
      cancel: '取消',
      auto: '自动',
      auto_inbound: '自动入库',
      confirm: '确认',
      execute: '执行',
    } as Record<string, unknown>
  )[a] || localizeEnumText(a)
}

function entityLabel(t: string) {
  return (
    {
      requirement: '需求',
      plan: '计划',
      order: '采购计划',
      contract: '合同',
      payment_request: '付款申请',
      payment: '付款',
      shipment: '收货',
      inbound: '入库',
    } as Record<string, unknown>
  )[t] || localizeEnumText(t)
}

onMounted(load)
watch(() => [props.entityType, props.entityId], load)
</script>

<style scoped lang="scss">
.purchase-flow-history {
  background: #fff;
  border: 1px solid #e4e7ed;
  border-radius: 10px;
  padding: 14px 18px;
  margin-top: 12px;
}
.history-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 8px;
  .el-icon { color: #909399; }
  .el-tag { margin-left: 4px; }
}
.loading { padding: 12px 0; }

/* V0.6.3 采购详情 - 实体卡片 */
.flow-entities {
  margin-bottom: 14px;
  padding: 10px 12px;
  background: #fafbfc;
  border: 1px solid #ebeef5;
  border-radius: 6px;
}
.entity-group {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 4px 0;
  & + .entity-group { border-top: 1px dashed #e4e7ed; margin-top: 4px; padding-top: 8px; }
  .group-label {
    font-size: 12px;
    color: #909399;
    font-weight: 600;
    min-width: 56px;
  }
  .entity-card {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    font-size: 12px;
  }
  .entity-code { font-weight: 600; color: #0c447c; }
  .entity-meta { color: #606266; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .entity-amount { color: #1d9e75; font-weight: 600; }
  .entity-date { color: #909399; font-size: 11px; }
}

.log-row {
  font-size: 13px;
  line-height: 1.7;
}
.log-action {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: #303133;
  .from { color: #909399; }
  .to { color: #67c23a; }
}
.log-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #606266;
  margin-top: 2px;
  .operator { color: #303133; }
  .entity-type { color: #c0c4cc; }
}
.action-tag {
  display: inline-block;
  padding: 1px 6px;
  border-radius: 3px;
  font-size: 11px;
  &.action-approve { background: #f0f9eb; color: #67c23a; }
  &.action-reject  { background: #fef0f0; color: #f56c6c; }
  &.action-submit  { background: #ecf5ff; color: #409eff; }
  &.action-auto,
  &.action-auto_inbound { background: #fdf6ec; color: #e6a23c; }
  &.action-sign    { background: #f0f9eb; color: #67c23a; }
  &.action-confirm { background: #f0f9eb; color: #67c23a; }
  &.action-cancel  { background: #fef0f0; color: #f56c6c; }
}
.log-remark {
  color: #606266;
  font-size: 12px;
  background: #f5f7fa;
  padding: 4px 8px;
  border-radius: 4px;
  margin-top: 4px;
}
</style>
