<template>
  <el-dialog v-model="visible" title="返修详情" width="1440px" destroy-on-close top="5vh">
    <div v-if="!ro" class="loading-placeholder">加载中...</div>
    <template v-else>
      <!-- 状态栏 -->
      <div class="status-bar" :class="`status-${ro.status}`">
        <div class="status-left">
          <code class="ro-code">{{ ro.code }}</code>
          <el-tag :type="ro.status_color" effect="dark">{{ ro.status_label }}</el-tag>
          <el-tag v-if="ro.method_label" :type="ro.is_paid ? 'danger' : 'success'" effect="plain">{{ ro.method_label }}</el-tag>
          <el-tag v-if="ro.is_paid" type="warning" size="small">收费</el-tag>
        </div>
        <div class="status-right">
          <!-- 完整的返修状态流按钮 -->
          <el-button v-if="ro.status === 'received'" type="warning" size="small" @click="showShipOut=true">发货</el-button>
          <el-button v-if="ro.status === 'sent_for_repair'" type="primary" size="small" @click="onStartRepair" :loading="starting">开始维修</el-button>
          <el-button v-if="ro.status === 'in_repair'" size="small" @click="showAddMethod=true">添加维修方式</el-button>
          <el-button v-if="ro.status === 'in_repair'" type="success" size="small" @click="onMarkRepaired" :loading="marking">标记修好</el-button>
          <el-button v-if="ro.status === 'repaired'" type="success" size="small" @click="showDeliver=true">交付客户</el-button>
          <span class="muted" style="margin-left:8px">{{ ro.customer_name || ro.contact_name }} · {{ formatTime(ro.created_at) }}</span>
        </div>
      </div>

      <!-- Tabs -->
      <el-tabs v-model="activeTab" class="detail-tabs">
        <!-- Tab 1: 基本信息 -->
        <el-tab-pane label="基本信息" name="basic">
          <el-descriptions :column="4" border style="margin-top:8px">
            <el-descriptions-item label="来源" :span="1">{{ ro.source_type === 'work_order' ? '维修工单' : ro.source_type || '—' }}</el-descriptions-item>
            <el-descriptions-item label="来源单号" :span="1"><code>{{ ro.source_code || '—' }}</code></el-descriptions-item>
            <el-descriptions-item label="客户" :span="1">{{ ro.customer_name || '—' }}</el-descriptions-item>
            <el-descriptions-item label="项目" :span="1">{{ ro.project_name || '—' }}</el-descriptions-item>
            <el-descriptions-item label="联系人" :span="1">{{ ro.contact_name || '—' }}</el-descriptions-item>
            <el-descriptions-item label="联系电话" :span="1">{{ ro.contact_phone || '—' }}</el-descriptions-item>
            <el-descriptions-item label="地址" :span="2">{{ ro.address || '—' }}</el-descriptions-item>
            <el-descriptions-item label="品牌" :span="1">{{ ro.equipment_brand || '—' }}</el-descriptions-item>
            <el-descriptions-item label="型号" :span="1">{{ ro.equipment_model || '—' }}</el-descriptions-item>
            <el-descriptions-item label="序列号" :span="2"><code>{{ ro.serial_no || '—' }}</code></el-descriptions-item>
            <el-descriptions-item label="故障描述" :span="4">{{ ro.fault_description || '—' }}</el-descriptions-item>
            <el-descriptions-item label="接件人" :span="1">{{ ro.receiver_name || '—' }}</el-descriptions-item>
            <el-descriptions-item label="接件时间" :span="1">{{ formatTime(ro.received_at) }}</el-descriptions-item>
            <el-descriptions-item label="预计完成" :span="1">{{ formatTime(ro.expected_finish_at) }}</el-descriptions-item>
            <el-descriptions-item label="备注" :span="1">{{ ro.remarks || '—' }}</el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>

        <!-- Tab 2: 物流轨迹 -->
        <el-tab-pane :label="`物流轨迹 (${(ro.shipments||[]).length})`" name="shipments">
          <div v-if="!ro.shipments?.length" class="empty-state">暂无物流记录</div>
          <div v-else class="shipment-grid">
            <div v-for="(s, idx) in ro.shipments" :key="idx" class="shipment-card" :class="`dir-${s.direction}`">
              <div class="ship-head">
                <el-tag :type="s.direction === 'outbound' ? 'warning' : 'success'" effect="dark" size="small">
                  {{ s.direction_label }}
                </el-tag>
                <el-tag size="small" effect="plain" :type="s.delivery_status === 'delivered' ? 'success' : 'info'">
                  {{ s.delivery_status === 'delivered' ? '已送达' : s.delivery_status === 'shipped' ? '已发货' : s.delivery_status || '—' }}
                </el-tag>
              </div>
              <div class="ship-body">
                <div class="ship-row"><span class="ship-label">承运商</span><span class="ship-value">{{ s.carrier }}</span></div>
                <div class="ship-row"><span class="ship-label">运单号</span><span class="ship-value"><code>{{ s.tracking_no }}</code></span></div>
                <div class="ship-row"><span class="ship-label">发件人</span><span class="ship-value">{{ s.sender_name }}{{ s.sender_phone ? ' · '+s.sender_phone : '' }}</span></div>
                <div class="ship-row"><span class="ship-label">收件人</span><span class="ship-value">{{ s.receiver_name }}{{ s.receiver_phone ? ' · '+s.receiver_phone : '' }}</span></div>
                <div class="ship-row" v-if="s.shipped_at"><span class="ship-label">发货时间</span><span class="ship-value">{{ formatTime(s.shipped_at) }}</span></div>
                <div class="ship-row" v-if="s.estimated_arrival"><span class="ship-label">预计到达</span><span class="ship-value">{{ formatTime(s.estimated_arrival) }}</span></div>
                <div class="ship-row" v-if="s.actual_arrival"><span class="ship-label">实际到达</span><span class="ship-value">{{ formatTime(s.actual_arrival) }}</span></div>
                <div class="ship-row" v-if="s.cost"><span class="ship-label">运费</span><span class="ship-value">¥{{ s.cost }}</span></div>
              </div>
            </div>
          </div>
        </el-tab-pane>

        <!-- Tab 3: 维修方式 -->
        <el-tab-pane :label="`维修方式 (${(ro.methods||[]).length})`" name="methods">
          <div v-if="!ro.methods?.length" class="empty-state">暂无维修方式</div>
          <div v-else>
            <div v-for="(m, idx) in ro.methods" :key="idx" class="method-card" :class="m.is_paid ? 'paid' : 'free'">
              <div class="method-head">
                <div class="method-title">
                  <span class="method-type">{{ m.method_label }}</span>
                  <el-tag v-if="m.is_paid" type="danger" size="small" effect="dark">收费</el-tag>
                  <el-tag v-else type="success" size="small" effect="dark">免费</el-tag>
                </div>
                <div class="method-cost">
                  <span v-if="m.actual_cost">¥{{ m.actual_cost }}</span>
                  <span v-else-if="m.estimated_cost" class="muted">预估 ¥{{ m.estimated_cost }}</span>
                </div>
              </div>
              <div class="method-body" v-if="m.parts_replaced?.length">
                <div class="info-label">换件清单</div>
                <el-table :data="m.parts_replaced" size="small" style="margin-top:4px">
                  <el-table-column prop="name" label="配件名" />
                  <el-table-column prop="qty" label="数量" width="80" />
                  <el-table-column label="单价" width="100"><template #default="{row}">¥{{ row.price }}</template></el-table-column>
                  <el-table-column label="小计" width="100"><template #default="{row}">¥{{ (row.qty * row.price).toFixed(2) }}</template></el-table-column>
                </el-table>
              </div>
              <div class="method-body" v-if="m.hours_spent"><div class="info-label">工时</div><div class="info-value">{{ m.hours_spent }} 小时</div></div>
              <div class="method-body" v-if="m.remarks" style="margin-top:4px">📝 {{ m.remarks }}</div>
            </div>
          </div>
        </el-tab-pane>

        <!-- Tab 4: 交付客户 -->
        <el-tab-pane :label="`交付客户 (${(ro.progress_logs||[]).length})`" name="progress">
          <el-timeline v-if="ro.progress_logs?.length">
            <el-timeline-item v-for="(l, idx) in ro.progress_logs" :key="idx" :timestamp="formatTime(l.action_at)" placement="top" :type="l.is_paid ? 'danger' : 'primary'">
              <div class="progress-title">
                <el-tag size="small" effect="dark">{{ l.progress }}</el-tag>
                <el-tag v-if="l.is_paid" type="warning" size="small">💰</el-tag>
                <span class="actor">{{ l.actor_name }}</span>
              </div>
              <div v-if="l.note" class="progress-note">{{ l.note }}</div>
            </el-timeline-item>
          </el-timeline>
          <div v-else class="empty-state">暂无进度记录</div>
        </el-tab-pane>
      </el-tabs>
    </template>

    <template #footer>
      <el-button @click="visible = false">关闭</el-button>
    </template>
  </el-dialog>
  <ShipmentDialog v-model="showShipOut" :ro="ro" direction="outbound" @done="reload" />
  <AddMethodDialog v-model="showAddMethod" :ro="ro" @done="reload" />
  <DeliverDialog v-model="showDeliver" :ro="ro" @done="reload" />
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import ShipmentDialog from './ShipmentDialog.vue'
import AddMethodDialog from './AddMethodDialog.vue'
import DeliverDialog from './DeliverDialog.vue'

const props = defineProps<{ modelValue: boolean; roId: number | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: boolean): void }>()
const visible = computed({ get: () => props.modelValue, set: (v) => { if (!v) ro.value = null; emit('update:modelValue', v) } })

const ro = ref<Record<string, unknown> | null>(null)
const activeTab = ref('basic')
const showShipOut = ref(false)
const showAddMethod = ref(false)
const showDeliver = ref(false)
const starting = ref(false)
const marking = ref(false)

const onStartRepair = async () => {
  starting.value = true
  try { await post(`/repair-orders/${props.roId}/in-repair`); ElMessage.success('已开始维修'); reload() }
  catch (e: unknown) { ElMessage.error((e as { message?: string })?.message || '操作失败') }
  finally { starting.value = false }
}
const onMarkRepaired = async () => {
  marking.value = true
  try { await post(`/repair-orders/${props.roId}/repaired`); ElMessage.success('已标记修好'); reload() }
  catch (e: unknown) { ElMessage.error((e as { message?: string })?.message || '操作失败') }
  finally { marking.value = false }
}

const reload = () => {
  if (props.roId) {
    get(`/repair-orders/${props.roId}`).then(res => {
      ro.value = (res as { data?: Record<string, unknown> })?.data || (res as Record<string, unknown>)
    }).catch(() => {})
  }
}

watch(() => props.modelValue, async (v) => {
  if (v && props.roId) {
    try {
      const res = await get(`/repair-orders/${props.roId}`)
      ro.value = (res as { data?: Record<string, unknown> })?.data || (res as Record<string, unknown>)
    } catch { ro.value = {} }
  }
})

const formatTime = (s: string) => {
  if (!s) return ''
  return (s + '').replace('T', ' ').slice(0, 16)
}
</script>

<style scoped lang="scss">
.status-bar {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 8px;
  .status-left { display: flex; align-items: center; gap: 10px; }
  .status-right { display: flex; align-items: center; gap: 8px; }
  .ro-code { font-size: 18px; font-weight: 700; }
}
.muted { color: #999; font-size: 13px; }
.loading-placeholder { padding: 60px; text-align: center; color: #999; }
.empty-state { padding: 40px 0; text-align: center; color: #999; }

.detail-tabs { margin-top: 4px; }

.shipment-grid { display: flex; flex-direction: column; gap: 12px; }
.shipment-card {
  border: 1px solid #e8e8e8; border-radius: 8px; padding: 14px;
  &.dir-outbound { border-left: 3px solid #E6A23C; }
  &.dir-inbound { border-left: 3px solid #67c23a; }
  .ship-head { display: flex; gap: 8px; margin-bottom: 10px; }
  .ship-body { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
  .ship-row { display: flex; font-size: 13px; }
  .ship-label { color: #999; width: 70px; flex-shrink: 0; }
  .ship-value { color: #333; }
}

.method-card {
  border: 1px solid #e8e8e8; border-radius: 8px; padding: 14px; margin-bottom: 12px;
  &.paid { border-left: 3px solid #F56C6C; }
  &.free { border-left: 3px solid #67c23a; }
  .method-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
  .method-title { display: flex; align-items: center; gap: 8px; }
  .method-type { font-weight: 600; }
  .method-cost { font-size: 16px; font-weight: 700; color: #E6A23C; }
  .method-body { margin-top: 6px; }
  .info-label { font-size: 12px; color: #999; }
  .info-value { font-size: 13px; }
}
.progress-title { display: flex; align-items: center; gap: 8px; }
.progress-note { margin-top: 4px; color: #666; font-size: 13px; }
</style>
