<template>
  <div class="page-container">
    <div v-if="loading && !ro" class="loading-state"><el-icon class="is-loading"><Loading /></el-icon><span>加载中…</span></div>

    <template v-else-if="ro">
      <!-- 顶部 -->
      <div class="status-bar" :class="`status-${ro.status}`">
        <div class="status-left">
          <el-button :icon="ArrowLeft" @click="$router.back()" circle size="small" />
          <div class="status-info">
            <div class="status-row1">
              <code class="ro-code">{{ ro.code }}</code>
              <el-tag :type="ro.status_color" effect="dark">{{ ro.status_label }}</el-tag>
              <el-tag v-if="ro.method_label" :type="ro.is_paid ? 'danger' : 'success'" effect="plain">{{ ro.method_label }}</el-tag>
              <el-tag v-if="ro.is_paid" type="warning" size="small">💰 收费</el-tag>
            </div>
            <div class="status-row2">
              <span><el-icon><User /></el-icon> {{ ro.customer_name || ro.contact_name }}</span>
              <span v-if="ro.source_type === 'work_order'">
                <el-icon><Promotion /></el-icon>
                来自工单
                <el-link type="primary" @click="$router.push(`/maintenance/work-orders/${ro.source_id}`)">{{ ro.source_code }}</el-link>
              </span>
            </div>
          </div>
        </div>
        <div class="status-right">
          <el-button v-if="ro.status === 'received' && !readonly" type="warning" :icon="Box" @click="onShipOut" size="small">寄出</el-button>
          <el-button v-if="ro.status === 'sent_for_repair' && !readonly" @click="onInRepair" size="small">开始维修</el-button>
          <el-button v-if="ro.status === 'in_repair' && !readonly" type="success" @click="onRepaired" size="small">标记修好</el-button>
          <el-button v-if="ro.status === 'repaired' && !readonly" type="warning" @click="onShipBack" size="small">寄回</el-button>
          <el-button v-if="['sent_back','repaired'].includes(ro.status) && !readonly" type="success" :icon="CircleCheck" @click="onClose" size="small">关闭</el-button>
          <el-button v-if="!['closed','cancelled'].includes(ro.status) && !readonly" @click="onCancel" plain size="small">取消</el-button>
        </div>
      </div>

      <!-- Tab 切换 (移动友好) -->
      <div class="content-card">
        <el-tabs v-model="activeTab" class="detail-tabs">
          <!-- Tab 1: 基本信息 -->
          <el-tab-pane label="基本信息" name="basic">
            <div class="info-grid">
              <div class="info-item">
                <div class="info-label">客户</div>
                <div class="info-value">{{ ro.customer_name || '—' }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">项目</div>
                <div class="info-value">{{ ro.project_name || '—' }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">联系人</div>
                <div class="info-value">{{ ro.contact_name || '—' }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">联系电话</div>
                <div class="info-value">{{ ro.contact_phone || '—' }}</div>
              </div>
              <div class="info-item full" v-if="ro.address">
                <div class="info-label">地址</div>
                <div class="info-value">{{ ro.address }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">品牌</div>
                <div class="info-value">{{ ro.equipment_brand || '—' }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">型号</div>
                <div class="info-value">{{ ro.equipment_model || '—' }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">序列号</div>
                <div class="info-value"><code v-if="ro.serial_no">{{ ro.serial_no }}</code><span v-else>—</span></div>
              </div>
              <div class="info-item full">
                <div class="info-label">故障描述</div>
                <div class="info-value description">{{ ro.fault_description }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">接件人</div>
                <div class="info-value">{{ ro.receiver_name || '—' }}</div>
              </div>
              <div class="info-item">
                <div class="info-label">接件时间</div>
                <div class="info-value">{{ formatDate(ro.received_at) }}</div>
              </div>
            </div>
          </el-tab-pane>

          <!-- Tab 2: 物流轨迹 (往返双向) -->
          <el-tab-pane :label="`物流轨迹 (${(ro.shipments||[]).length})`" name="shipments">
            <div class="attachment-section">
              <div class="attach-header">
                <h4>凭证照片 ({{ attachments.length }})</h4>
                <el-upload
                  :http-request="uploadAttachment"
                  :show-file-list="false"
                  accept="image/*"
                  multiple
                >
                  <el-button size="small" type="primary" :icon="Upload">上传凭证图</el-button>
                </el-upload>
              </div>
              <div v-if="!attachments.length" class="empty-state-sm">暂无凭证照片</div>
              <div v-else class="attachment-grid">
                <div v-for="att in attachments" :key="att.id" class="attachment-card">
                  <el-image :src="att.file_url" :preview-src-list="[att.file_url]" fit="cover" />
                  <div class="att-name">{{ att.file_name }}</div>
                  <el-button size="small" link type="danger" @click="deleteAttachment(att.id)">删除</el-button>
                </div>
              </div>
            </div>
            <div v-if="!ro.shipments?.length" class="empty-state">
              <el-empty description="暂无物流记录" />
            </div>
            <div v-else class="shipment-grid">
              <div
                v-for="(s, idx) in ro.shipments"
                :key="idx"
                class="shipment-card"
                :class="`dir-${s.direction}`"
              >
                <div class="ship-head">
                  <el-tag :type="s.direction === 'outbound' ? 'warning' : 'success'" effect="dark" size="small">
                    <el-icon><Box /></el-icon>
                    {{ s.direction_label }}
                  </el-tag>
                  <el-tag size="small" effect="plain" :type="s.delivery_status === 'delivered' ? 'success' : 'info'">
                    {{ deliveryLabel(s.delivery_status) }}
                  </el-tag>
                </div>
                <div class="ship-body">
                  <div class="ship-row">
                    <span class="ship-label">承运商</span>
                    <span class="ship-value">{{ s.carrier }}</span>
                  </div>
                  <div class="ship-row">
                    <span class="ship-label">运单号</span>
                    <span class="ship-value"><code>{{ s.tracking_no }}</code></span>
                  </div>
                  <div class="ship-row">
                    <span class="ship-label">发件人</span>
                    <span class="ship-value">{{ s.sender_name }} {{ s.sender_phone ? '· ' + s.sender_phone : '' }}</span>
                  </div>
                  <div class="ship-row">
                    <span class="ship-label">收件人</span>
                    <span class="ship-value">{{ s.receiver_name }} {{ s.receiver_phone ? '· ' + s.receiver_phone : '' }}</span>
                  </div>
                  <div class="ship-row" v-if="s.shipped_at">
                    <span class="ship-label">发货时间</span>
                    <span class="ship-value">{{ formatDate(s.shipped_at) }}</span>
                  </div>
                  <div class="ship-row" v-if="s.estimated_arrival">
                    <span class="ship-label">预计到达</span>
                    <span class="ship-value">{{ formatDate(s.estimated_arrival) }}</span>
                  </div>
                  <div class="ship-row" v-if="s.actual_arrival">
                    <span class="ship-label">实际到达</span>
                    <span class="ship-value">{{ formatDate(s.actual_arrival) }}</span>
                  </div>
                  <div class="ship-row" v-if="s.cost">
                    <span class="ship-label">运费</span>
                    <span class="ship-value">¥ {{ s.cost }}</span>
                  </div>
                </div>
              </div>
            </div>
          </el-tab-pane>

          <!-- Tab 3: 维修方式 -->
          <el-tab-pane :label="`维修方式 (${(ro.methods||[]).length})`" name="methods">
            <div v-if="!ro.methods?.length" class="empty-state">
              <el-empty description="暂无维修方式记录" />
              <el-button v-if="!readonly" type="primary" @click="onAddMethod">添加维修方式</el-button>
            </div>
            <div v-else>
              <div
                v-for="(m, idx) in ro.methods"
                :key="idx"
                class="method-card"
                :class="m.is_paid ? 'paid' : 'free'"
              >
                <div class="method-head">
                  <div class="method-title">
                    <span class="method-type">{{ m.method_label }}</span>
                    <el-tag v-if="m.is_paid" type="danger" size="small" effect="dark">💰 收费</el-tag>
                    <el-tag v-else type="success" size="small" effect="dark">🆓 免费</el-tag>
                  </div>
                  <div class="method-cost">
                    <span v-if="m.actual_cost">¥ {{ m.actual_cost }}</span>
                    <span v-else-if="m.estimated_cost" class="muted">预估 ¥ {{ m.estimated_cost }}</span>
                  </div>
                </div>
                <div class="method-body" v-if="m.parts_replaced?.length">
                  <div class="info-label">换件清单</div>
                  <el-table :data="m.parts_replaced" size="small" style="margin-top: 4px;">
                    <el-table-column prop="name" label="配件名" />
                    <el-table-column prop="qty" label="数量" width="80" />
                    <el-table-column label="单价" width="100">
                      <template #default="{ row }">¥ {{ row.price }}</template>
                    </el-table-column>
                    <el-table-column label="小计" width="100">
                      <template #default="{ row }">¥ {{ (row.qty * row.price).toFixed(2) }}</template>
                    </el-table-column>
                  </el-table>
                </div>
                <div class="method-body" v-if="m.hours_spent">
                  <div class="info-label">工时</div>
                  <div class="info-value">{{ m.hours_spent }} 小时</div>
                </div>
                <div class="method-body" v-if="m.payment_status">
                  <div class="info-label">付款</div>
                  <div class="info-value">
                    <el-tag :type="paymentColor(m.payment_status)" size="small">{{ paymentLabel(m.payment_status) }}</el-tag>
                    <span v-if="m.paid_at" class="muted" style="margin-left: 8px;">{{ formatDate(m.paid_at) }}</span>
                  </div>
                </div>
                <div v-if="m.remarks" class="method-remarks">📝 {{ m.remarks }}</div>
              </div>
              <el-button v-if="!readonly" @click="onAddMethod" plain style="margin-top: 12px;">+ 添加另一个维修方式</el-button>
            </div>
          </el-tab-pane>

          <!-- Tab 4: 进度日志 -->
          <el-tab-pane :label="`进度 (${(ro.progress_logs||[]).length})`" name="progress">
            <el-button v-if="!readonly" @click="onAddLog" plain :icon="Plus" size="small" style="margin-bottom: 12px;">添加进度</el-button>
            <el-timeline v-if="ro.progress_logs?.length">
              <el-timeline-item
                v-for="(l, idx) in ro.progress_logs"
                :key="idx"
                :timestamp="formatDate(l.action_at)"
                placement="top"
                :type="l.is_paid ? 'danger' : 'primary'"
              >
                <div class="progress-title">
                  <el-tag size="small" effect="dark">{{ l.progress }}</el-tag>
                  <el-tag v-if="l.is_paid" type="warning" size="small">💰</el-tag>
                  <span class="actor">{{ l.actor_name }}</span>
                </div>
                <div v-if="l.description" class="progress-desc">{{ l.description }}</div>
                <div v-if="l.cost_added" class="progress-cost">+ ¥ {{ l.cost_added }}</div>
              </el-timeline-item>
            </el-timeline>
            <el-empty v-else description="暂无进度记录" />
          </el-tab-pane>

          <!-- V0.5.7 块2 — 维修过程照片 (7 步进度) -->
          <el-tab-pane label="过程照片" name="photos">
            <StepPhotoUploader
              v-if="ro?.id"
              target-type="repair_order"
              :target-id="ro.id"
            />
          </el-tab-pane>
          <!-- Tab 5: 费用 -->
          <el-tab-pane label="费用" name="cost">
            <div class="cost-summary">
              <div class="cost-row">
                <span>配件费</span><span class="amount">¥ {{ ro.parts_cost }}</span>
              </div>
              <div class="cost-row">
                <span>工时费</span><span class="amount">¥ {{ ro.labor_cost }}</span>
              </div>
              <div class="cost-row">
                <span>物流费</span><span class="amount">¥ {{ ro.shipping_cost }}</span>
              </div>
              <div class="cost-row total">
                <span>合计</span><span class="amount">¥ {{ ro.total_cost }}</span>
              </div>
            </div>
            <div v-if="ro.remarks" style="margin-top: 16px;">
              <div class="info-label">备注</div>
              <div class="info-value description">{{ ro.remarks }}</div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>

      <!-- 移动底部操作栏 -->
      <div class="bottom-bar show-mobile">
        <el-button v-if="ro.status === 'received' && !readonly" type="warning" @click="onShipOut" style="flex:1;">寄出</el-button>
        <el-button v-if="ro.status === 'in_repair' && !readonly" type="success" @click="onRepaired" style="flex:1;">修好</el-button>
        <el-button v-if="ro.status === 'repaired' && !readonly" type="warning" @click="onShipBack" style="flex:1;">寄回</el-button>
        <el-button v-if="['sent_back','repaired'].includes(ro.status) && !readonly" type="primary" @click="onClose" style="flex:1;">关闭</el-button>
        <el-button v-if="!['closed','cancelled'].includes(ro.status) && !readonly" @click="onCancel" plain>取消</el-button>
      </div>
    </template>

    <!-- 寄出 dialog -->
    <el-dialog v-model="shipOutVisible" :title="shipDirection === 'outbound' ? '寄出去程' : '寄回回程'" width="500px">
      <el-form :model="shipForm" label-width="100px">
        <el-form-item label="承运商" required>
          <el-select v-model="shipForm.carrier" filterable allow-create placeholder="选择或输入" style="width: 100%">
            <el-option label="顺丰" value="顺丰" />
            <el-option label="京东" value="京东" />
            <el-option label="中通" value="中通" />
            <el-option label="圆通" value="圆通" />
            <el-option label="申通" value="申通" />
            <el-option label="邮政" value="邮政" />
          </el-select>
        </el-form-item>
        <el-form-item label="运单号" required>
          <el-input v-model="shipForm.tracking_no" />
        </el-form-item>
        <el-form-item label="运费">
          <el-input-number v-model="shipForm.cost" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="发件人" required>
          <el-input v-model="shipForm.sender_name" />
        </el-form-item>
        <el-form-item label="发件电话">
          <el-input v-model="shipForm.sender_phone" />
        </el-form-item>
        <el-form-item label="发件地址">
          <el-input v-model="shipForm.sender_address" />
        </el-form-item>
        <el-form-item label="收件人" required>
          <el-input v-model="shipForm.receiver_name" />
        </el-form-item>
        <el-form-item label="收件电话">
          <el-input v-model="shipForm.receiver_phone" />
        </el-form-item>
        <el-form-item label="收件地址" required>
          <el-input v-model="shipForm.receiver_address" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="shipOutVisible = false">取消</el-button>
        <el-button type="primary" @click="onShipConfirm" :loading="shipping">确认</el-button>
      </template>
    </el-dialog>

    <!-- 维修方式 dialog -->
    <el-dialog v-model="methodVisible" title="添加维修方式" width="500px">
      <el-form :model="methodForm" label-width="100px">
        <el-form-item label="维修方式" required>
          <el-select v-model="methodForm.method_type" style="width: 100%">
            <el-option label="🆓 免费（保内）" value="free_warranty" />
            <el-option label="🆓 免费（合同）" value="free_contract" />
            <el-option label="💰 付费（维修）" value="paid_repair" />
            <el-option label="💰 付费（换新）" value="paid_replace" />
            <el-option label="↩️ 退回（不修）" value="returned" />
          </el-select>
        </el-form-item>
        <el-form-item label="实际成本">
          <el-input-number v-model="methodForm.actual_cost" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="工时 (小时)">
          <el-input-number v-model="methodForm.hours_spent" :min="0" :precision="1" style="width: 100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="methodForm.remarks" type="textarea" :rows="2" maxlength="500" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="methodVisible = false">取消</el-button>
        <el-button type="primary" @click="onMethodConfirm" :loading="savingMethod">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowLeft, User, Promotion, Box, CircleCheck, Plus, Loading, Upload } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { unwrapItem } from '@/utils/response'
import StepPhotoUploader from './components/StepPhotoUploader.vue'

const route = useRoute()
const id = Number(route.params.id)
const ro = ref<Record<string, unknown> | null>(null)
const loading = ref(false)
const activeTab = ref('basic')
const readonly = computed(() => ['closed', 'cancelled'].includes(ro.value?.status))

// 寄出/寄回
const shipOutVisible = ref(false)
const shipping = ref(false)
const shipDirection = ref<'outbound' | 'inbound'>('outbound')
const shipForm = ref({
  carrier: '顺丰', tracking_no: '', cost: 0,
  sender_name: '', sender_phone: '', sender_address: '',
  receiver_name: '', receiver_phone: '', receiver_address: '',
  estimated_arrival: '',
})

// 维修方式
const methodVisible = ref(false)
const savingMethod = ref(false)
const methodForm = ref({ method_type: 'paid_repair', actual_cost: 0, hours_spent: 0, remarks: '' })

const attachments = ref<Record<string, unknown>[]>([])
const loadAttachments = async () => {
  if (!ro.value?.id) return
  try {
    // 后端返回 {code:0, data: [array]}, 解包后 res 本身就是数组
    const res = await get(`/repair-orders/${ro.value.id}/attachments`)
    attachments.value = res || []
  } catch { attachments.value = [] }
}
const uploadAttachment = async (option: Record<string, unknown>) => {
  const fd = new FormData()
  fd.append('file', option.file)
  fd.append('category', 'shipping')
  try {
    await post(`/repair-orders/${ro.value?.id}/attachments`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    ElMessage.success('上传成功')
    await loadAttachments()
  } catch (e: unknown) { ElMessage.error(e?.message || '上传失败') }
}
const deleteAttachment = async (id: number) => {
  try { await ElMessageBox.confirm('确定删除这张凭证图?', '提示', { type: 'warning' }) } catch { return }
  try {
    await del(`/repair-orders/${ro.value?.id}/attachments/${id}`)
    ElMessage.success('已删除')
    await loadAttachments()
  } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
}

const loadData = async () => {
  loading.value = true
  try {
    // V0.6.3: res = {code, data: <entity>}
    const res = await get(`/repair-orders/${id}`)
    ro.value = unwrapItem(res) || {}
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + (e?.message || ''))
  } finally { loading.value = false }
}

// 寄出
const onShipOut = () => {
  shipDirection.value = 'outbound'
  shipForm.value = {
    carrier: '顺丰', tracking_no: '', cost: 25,
    sender_name: ro.value?.contact_name || '', sender_phone: ro.value?.contact_phone || '',
    sender_address: ro.value?.address || '',
    receiver_name: '厂家售后', receiver_phone: '', receiver_address: '',
    estimated_arrival: '',
  }
  shipOutVisible.value = true
}

const onShipBack = () => {
  shipDirection.value = 'inbound'
  shipForm.value = {
    carrier: '顺丰', tracking_no: '', cost: 25,
    sender_name: '厂家售后', sender_phone: '', sender_address: '',
    receiver_name: ro.value?.contact_name || '', receiver_phone: ro.value?.contact_phone || '',
    receiver_address: ro.value?.address || '',
    estimated_arrival: '',
  }
  shipOutVisible.value = true
}

const onShipConfirm = async () => {
  if (!shipForm.value.carrier || !shipForm.value.tracking_no || !shipForm.value.receiver_name || !shipForm.value.receiver_address) {
    return ElMessage.warning('请填承运商/运单号/收件人/收件地址')
  }
  shipping.value = true
  try {
    const endpoint = shipDirection.value === 'outbound' ? 'ship-out' : 'ship-back'
    await post(`/repair-orders/${id}/${endpoint}`, shipForm.value)
    ElMessage.success(shipDirection.value === 'outbound' ? '已寄出' : '已寄回')
    shipOutVisible.value = false
    await loadData()
  } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
  finally { shipping.value = false }
}

const onInRepair = async () => {
  try {
    await post(`/repair-orders/${id}/in-repair`)
    ElMessage.success('已进入维修')
    await loadData()
  } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
}

const onRepaired = async () => {
  // 必须先有 method
  if (!ro.value?.methods?.length) {
    try { await ElMessageBox.confirm('标记修好前需要至少 1 条维修方式记录, 现在添加?', '提示', { type: 'warning' }) } catch { return }
    onAddMethod()
    return
  }
  try {
    await post(`/repair-orders/${id}/repaired`)
    ElMessage.success('已修好')
    await loadData()
  } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
}

const onClose = async () => {
  try { await ElMessageBox.confirm('确认关闭此返修单?', '提示', { type: 'warning' }) } catch { return }
  try {
    await post(`/repair-orders/${id}/close`)
    ElMessage.success('已关闭')
    await loadData()
  } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
}

const onCancel = async () => {
  const { value } = await ElMessageBox.prompt('请输入取消原因', '取消返修', { inputType: 'textarea' }).catch(() => null)
  if (!value) return
  try {
    await post(`/repair-orders/${id}/cancel`, { reason: value })
    ElMessage.success('已取消')
    await loadData()
  } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
}

const onAddMethod = () => {
  methodForm.value = { method_type: 'paid_repair', actual_cost: 0, hours_spent: 0, remarks: '' }
  methodVisible.value = true
}

const onMethodConfirm = async () => {
  savingMethod.value = true
  try {
    await post(`/repair-orders/${id}/methods`, methodForm.value)
    ElMessage.success('已添加')
    methodVisible.value = false
    await loadData()
  } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
  finally { savingMethod.value = false }
}

const onAddLog = async () => {
  const { value } = await ElMessageBox.prompt('进度说明 (例: 已诊断, 主板烧毁, 报价 ¥750)', '添加进度', { inputType: 'textarea' }).catch(() => null)
  if (!value) return
  try {
    await post(`/repair-orders/${id}/progress-logs`, { progress: '进度更新', description: value })
    ElMessage.success('已添加')
    await loadData()
  } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
}

const formatDate = (s: string) => {
  if (!s) return ''
  const d = new Date(s)
  return `${d.getMonth()+1}/${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

const deliveryLabel = (s: string) => ({ pending: '待发出', in_transit: '在途中', delivered: '已签收', exception: '异常' }[s] || s)
const paymentLabel = (s: string) => ({ unpaid: '未付', partial: '部分付', paid: '已付', refunded: '已退' }[s] || s)
const paymentColor = (s: string): string => ({ unpaid: 'danger', partial: 'warning', paid: 'success', refunded: 'info' }[s] || '')

onMounted(() => { loadData(); loadAttachments() })
</script>

<style scoped lang="scss">
.page-container { padding: 20px; }
.loading-state { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 60px; color: #909399; }

.attachment-section { margin-bottom: 20px; padding: 16px; background: #FAFAFA; border-radius: 6px; }
.attach-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.attach-header h4 { margin: 0; font-size: 14px; color: #303133; }
.empty-state-sm { text-align: center; padding: 20px; color: #C0C4CC; font-size: 12px; }
.attachment-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
.attachment-card { background: #fff; padding: 8px; border-radius: 4px; border: 1px solid #EBEEF5; text-align: center; }
.attachment-card .el-image { width: 100%; height: 100px; border-radius: 3px; }
.att-name { font-size: 11px; color: #606266; margin: 4px 0; word-break: break-all; }

.status-bar {
  display: flex; align-items: center; justify-content: space-between;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04); border-left: 4px solid #409EFF;
  &.status-received { border-left-color: #409EFF; }
  &.status-sent_for_repair, &.status-in_repair, &.status-sent_back { border-left-color: #E6A23C; }
  &.status-repaired { border-left-color: #67C23A; }
  &.status-closed, &.status-cancelled { border-left-color: #909399; }
}
.status-left { display: flex; align-items: center; gap: 12px; flex: 1; }
.status-row1 { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap; }
.ro-code { font-size: 18px; font-weight: 600; }
.status-row2 { display: flex; gap: 16px; font-size: 13px; color: #606266; span { display: flex; align-items: center; gap: 4px; } }
.status-right { display: flex; gap: 8px; flex-wrap: wrap; }

.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }

.info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.info-item { display: flex; flex-direction: column; gap: 4px; &.full { grid-column: 1 / -1; } }
.info-label { font-size: 12px; color: #909399; }
.info-value { font-size: 14px; color: #303133; }
.info-value.description { background: #f5f7fa; padding: 12px; border-radius: 4px; white-space: pre-wrap; }

.shipment-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.shipment-card {
  background: #fafbfc; border-radius: 8px; padding: 12px;
  border-left: 3px solid #E6A23C;
  &.dir-inbound { border-left-color: #67C23A; }
}
.ship-head { display: flex; justify-content: space-between; margin-bottom: 8px; }
.ship-body { display: flex; flex-direction: column; gap: 6px; }
.ship-row { display: flex; gap: 8px; font-size: 13px; }
.ship-label { color: #909399; min-width: 70px; }
.ship-value { color: #303133; flex: 1; }

.method-card {
  background: #fafbfc; border-radius: 8px; padding: 16px; margin-bottom: 12px;
  border-left: 3px solid #909399;
  &.paid { border-left-color: #F56C6C; }
  &.free { border-left-color: #67C23A; }
}
.method-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.method-title { display: flex; align-items: center; gap: 8px; }
.method-type { font-size: 16px; font-weight: 600; }
.method-cost { font-size: 20px; font-weight: 700; color: #F56C6C; }
.method-body { margin: 8px 0; }
.method-remarks { font-size: 13px; color: #606266; margin-top: 8px; font-style: italic; }

.progress-title { display: flex; align-items: center; gap: 8px; }
.progress-desc { font-size: 13px; color: #606266; margin-top: 4px; }
.progress-cost { font-size: 13px; color: #F56C6C; margin-top: 2px; font-weight: 600; }
.actor { font-size: 12px; color: #909399; margin-left: 4px; }

.cost-summary { padding: 16px; background: #f5f7fa; border-radius: 6px; }
.cost-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; border-bottom: 1px solid #ebeef5; &.total { font-weight: 600; font-size: 18px; color: #F56C6C; border-bottom: none; } .amount { font-family: 'Courier New', monospace; } }

.empty-state { text-align: center; padding: 40px 0; }

.bottom-bar { display: none; position: fixed; bottom: 0; left: 0; right: 0; background: #fff; padding: 12px 16px; box-shadow: 0 -2px 8px rgba(0,0,0,0.08); z-index: 100; gap: 8px; }
.show-mobile { display: none; }

@media (max-width: 768px) {
  .page-container { padding: 12px; padding-bottom: 80px; }
  .status-bar { flex-direction: column; align-items: flex-start; gap: 12px; }
  .status-right { display: none; }
  .info-grid { grid-template-columns: 1fr; }
  .shipment-grid { grid-template-columns: 1fr; }
  .show-mobile { display: flex; }
}
</style>
