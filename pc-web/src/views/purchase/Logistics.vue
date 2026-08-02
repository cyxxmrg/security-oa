<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">物流跟踪</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="loadList">刷新</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="物流单号 / 发货单号" clearable style="width: 220px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 140px">
            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="content-card">
      <div class="stats-row">
        <div class="stat-card" v-for="s in statCards" :key="s.label" :style="{ borderColor: s.color }">
          <div class="stat-icon" :style="{ background: s.color + '15', color: s.color }">
            <el-icon :size="20"><component :is="s.icon" /></el-icon>
          </div>
          <div>
            <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
            <div class="stat-label">{{ s.label }}</div>
          </div>
        </div>
      </div>

      <LogisticsTable
        :list="pagedList"
        :loading="loading"
        :page="page"
        :page-size="pageSize"
        :total="filteredList.length"
        @view="handleView"
        @edit="handleEdit"
        @delete="handleDelete"
        @page-change="(p: number) => page = p"
        @size-change="(s: number) => { pageSize = s; page = 1 }"
      />
    </div>

    <!-- 详情 Drawer -->
    <el-drawer v-model="showDetailDrawer" :title="`物流详情 - ${currentRow?.tracking_no || ''}`" direction="rtl" size="720px" :close-on-click-modal="false">
      <div v-if="currentRow" v-loading="detailLoading" class="detail-view">
        <!-- 头部信息 -->
        <div class="detail-header">
          <div class="header-top">
            <div>
              <div class="tracking-no">{{ currentRow.tracking_no || '-' }}</div>
              <div class="carrier-info">
                <el-icon><Van /></el-icon>
                <span>{{ currentRow.carrier || '-' }}</span>
                <span class="shipment-link" @click="handleViewShipment(currentRow)">· 发货单 {{ currentRow.code }}</span>
              </div>
            </div>
            <el-tag :type="statusTagType(currentRow.status)" effect="dark" size="large" class="status-tag">
              <el-icon style="vertical-align: -2px; margin-right: 4px;"><component :is="statusIcon(currentRow.status)" /></el-icon>
              {{ statusLabel(currentRow.status) }}
            </el-tag>
          </div>
          <div class="header-meta">
            <div class="meta-item">
              <div class="meta-label">当前位置</div>
              <div class="meta-value">
                <el-icon color="#0C447C" style="vertical-align: -2px;"><Location /></el-icon>
                {{ currentLocation }}
              </div>
            </div>
            <div class="meta-item">
              <div class="meta-label">预计到达</div>
              <div class="meta-value">{{ currentRow.expected_arrival_at ? String(currentRow.expected_arrival_at).slice(0, 10) : '-' }}</div>
            </div>
            <div class="meta-item">
              <div class="meta-label">实际到达</div>
              <div class="meta-value">{{ currentRow.arrived_at ? String(currentRow.arrived_at).slice(0, 10) : '-' }}</div>
            </div>
          </div>
        </div>

        <!-- 物流轨迹 -->
        <div class="section-title">
          <el-icon><Clock /></el-icon>
          <span>物流轨迹 ({{ currentEvents.length }})</span>
        </div>
        <div v-if="currentEvents.length === 0" class="empty-tip">暂无轨迹数据</div>
        <div class="timeline-wrapper" v-else>
          <el-timeline>
            <el-timeline-item
              v-for="(node, idx) in currentEvents"
              :key="node.id || idx"
              :type="idx === 0 ? 'primary' : (idx === currentEvents.length - 1 ? 'info' : 'success')"
              :size="idx === 0 ? 'large' : 'normal'"
              :timestamp="node.event_at ? String(node.event_at).slice(0, 16) : '-'"
              placement="top"
              :hollow="idx !== 0"
            >
              <div class="timeline-content" :class="{ 'latest': idx === 0 }">
                <div class="timeline-location">
                  <el-icon color="#0C447C" size="14" style="vertical-align: -2px;"><Location /></el-icon>
                  {{ node.location || '-' }}
                </div>
                <div class="timeline-desc">{{ node.description || node.status || '-' }}</div>
                <div class="timeline-meta" v-if="node.operator">操作人：{{ node.operator }}</div>
              </div>
            </el-timeline-item>
          </el-timeline>
        </div>
      </div>
      <template #footer>
        <el-button @click="showDetailDrawer = false">关闭</el-button>
        <el-button type="primary" @click="openAddEvent">添加物流事件</el-button>
        <el-button type="success" :icon="Phone" @click="handleContact(currentRow)">联系物流</el-button>
      </template>
    </el-drawer>

    <!-- 添加物流事件 Dialog -->
    <el-dialog v-model="showAddEventDialog" title="添加物流事件" width="1500px" :close-on-click-modal="false">
      <el-form :model="addEventForm" label-width="100px">
        <el-form-item label="位置">
          <el-input v-model="addEventForm.location" placeholder="如：上海徐汇区中转中心" />
        </el-form-item>
        <el-form-item label="事件状态">
          <el-select v-model="addEventForm.status" style="width: 100%">
            <el-option label="在途" value="in_transit" />
            <el-option label="到达/签收" value="arrived" />
            <el-option label="已揽收" value="shipped" />
          </el-select>
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="addEventForm.description" type="textarea" :rows="3" placeholder="如：快件已离开 [上海分拣中心]" />
        </el-form-item>
        <el-form-item label="操作人">
          <el-input v-model="addEventForm.operator" placeholder="如：王五" />
        </el-form-item>
        <el-alert type="info" :closable="false" show-icon>
          <template #title>添加「到达/签收」事件时，系统会自动将 shipment 状态推进为「已到达」</template>
        </el-alert>
      </el-form>
      <template #footer>
        <el-button @click="showAddEventDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="confirmAddEvent">提交</el-button>
      </template>
    </el-dialog>

    <!-- 联系物流 Dialog -->
    <el-dialog v-model="showContactDialog" title="联系物流" width="1500px" :close-on-click-modal="false">
      <div v-if="contactTarget" class="contact-view">
        <div class="contact-row">
          <span class="label">物流公司</span>
          <span class="value">{{ contactTarget.carrier }}</span>
        </div>
        <div class="contact-row">
          <span class="label">物流单号</span>
          <span class="value" style="font-family: monospace;">{{ contactTarget.tracking_no }}</span>
        </div>
        <div class="contact-row">
          <span class="label">客服电话</span>
          <span class="value phone-text">
            <el-icon color="#0C447C"><Phone /></el-icon>
            <span>{{ carrierPhone(contactTarget.carrier) }}</span>
            <el-button link type="primary" :icon="DocumentCopy" @click="copyPhone(contactTarget.carrier)">复制</el-button>
          </span>
        </div>
        <el-alert type="info" :closable="false" style="margin-top: 12px;">
          <template #title>
            <span style="font-size: 12px;">演示环境，号码与实际业务无关联</span>
          </template>
        </el-alert>
      </div>
      <template #footer>
        <el-button type="primary" @click="showContactDialog = false">知道了</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, type Component } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Refresh, Van, Promotion, Box, Location, Clock, Phone, Warning, WarningFilled, DocumentCopy } from '@element-plus/icons-vue'
import { purchase } from '@/api/modules'
import { unwrapList } from '@/utils/response'
import LogisticsTable from './components/logistics/LogisticsTable.vue'

type ElTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

interface ShipmentLite {
  id: number
  code?: string
  tracking_no?: string
  carrier?: string
  status: string
  expected_arrival_at?: string
  arrived_at?: string
  [key: string]: unknown
}

interface LogisticsEvent {
  id?: number
  event_at?: string
  location?: string
  status?: string
  description?: string
  operator?: string
  [key: string]: unknown
}

// === 状态选项 ===
const statusOptions = [
  { value: 'shipped', label: '已发货' },
  { value: 'in_transit', label: '在途' },
  { value: 'arrived', label: '已到达' },
  { value: 'closed', label: '已关闭' }
]

// === 物流公司客服电话 (mock) ===
const carrierPhoneMap: Record<string, string> = {
  '顺丰速运': '95338',
  '中通快递': '95311',
  '京东物流': '950616',
  '邮政EMS': '11183',
  '德邦物流': '95353'
}

// === 列表 / 加载 ===
const loading = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<ShipmentLite[]>([])

const searchForm = reactive({ keyword: '', status: '' })

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter(r => (r.tracking_no || '').toLowerCase().includes(kw) || (r.code || '').toLowerCase().includes(kw))
  }
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const statCards = computed(() => {
  const all = list.value
  return [
    { label: '在途数量', value: all.filter(l => l.status === 'in_transit' || l.status === 'shipped').length, icon: Van, color: '#0C447C' },
    { label: '已到达', value: all.filter(l => l.status === 'arrived').length, icon: Box, color: '#1D9E75' },
    { label: '今日到达', value: all.filter(l => l.status === 'arrived' && l.arrived_at && String(l.arrived_at).slice(0, 10) === new Date().toISOString().slice(0, 10)).length, icon: Promotion, color: '#534AB7' },
    { label: '已关闭', value: all.filter(l => l.status === 'closed').length, icon: Warning, color: '#909399' }
  ]
})

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 200, page: 1 }
    if (searchForm.status) params.status = searchForm.status
    const res = await purchase.getShipments(params)
    // V0.6.3 不再解包, 兼容两种形态
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const handleSearch = () => { page.value = 1 }
const handleReset = () => {
  searchForm.keyword = ''
  searchForm.status = ''
  page.value = 1
  loadList()
}

// === 详情 Drawer（调 getShipmentTrack）===
const showDetailDrawer = ref(false)
const detailLoading = ref(false)
const currentRow = ref<ShipmentLite | null>(null)
const currentEvents = ref<LogisticsEvent[]>([])

const handleView = async (row: ShipmentLite) => {
  currentRow.value = row
  currentEvents.value = []
  showDetailDrawer.value = true
  detailLoading.value = true
  try {
    const res = await purchase.getShipmentTrack(row.id)
    if (res) {
      currentRow.value = { ...row, ...(res.shipment || {}) }
      currentEvents.value = Array.isArray(res.events) ? res.events : []
    }
  } catch { /* 拦截器已提示 */ }
  finally { detailLoading.value = false }
}

const handleViewShipment = (row: ShipmentLite) => {
  ElMessage.info(`查看发货单：${row.code}（占位）`)
}

// 物流跟踪页为只读视图，编辑/删除走合同发货流程，此处仅占位避免运行时崩溃
const handleEdit = (_row: ShipmentLite) => {
  ElMessage.warning('物流单不可在此直接编辑；状态变更请通过「添加物流事件」推进')
}
const handleDelete = (_row: ShipmentLite) => {
  ElMessage.warning('物流单不可直接删除；如需作废请走合同发货流程')
}

// === 添加物流事件（推进 shipment 状态）===
const showAddEventDialog = ref(false)
const submitting = ref(false)
const addEventForm = reactive({
  location: '',
  status: 'in_transit',
  description: '',
  operator: ''
})
const openAddEvent = () => {
  if (!currentRow.value) return
  Object.assign(addEventForm, { location: '', status: 'in_transit', description: '', operator: '' })
  showAddEventDialog.value = true
}
const confirmAddEvent = async () => {
  const cur = currentRow.value
  if (!cur) return
  if (!addEventForm.location.trim() && !addEventForm.description.trim()) {
    ElMessage.warning('请填写位置或说明')
    return
  }
  submitting.value = true
  try {
    await purchase.addLogisticsEvent(cur.id, {
      location: addEventForm.location || null,
      status: addEventForm.status,
      description: addEventForm.description || null,
      operator: addEventForm.operator || null
    })
    ElMessage.success('事件已添加')
    showAddEventDialog.value = false
    // 重新拉取轨迹
    const res = await purchase.getShipmentTrack(cur.id)
    if (res) {
      currentRow.value = { ...cur, ...(res.shipment || {}) }
      currentEvents.value = Array.isArray(res.events) ? res.events : []
    }
    await loadList()
  } catch { /* 拦截器已提示 */ }
  finally { submitting.value = false }
}

// === 联系物流 ===
const showContactDialog = ref(false)
const contactTarget = ref<ShipmentLite | null>(null)
const handleContact = (row: ShipmentLite | null) => {
  if (!row) return
  contactTarget.value = row
  showContactDialog.value = true
}
const carrierPhone = (carrier: string | undefined) => carrierPhoneMap[carrier ?? ''] || '400-000-0000'
const copyPhone = async (carrier: string | undefined) => {
  const phone = carrierPhone(carrier)
  try {
    await navigator.clipboard.writeText(phone)
    ElMessage.success(`已复制客服电话：${phone}`)
  } catch {
    ElMessage.info(`客服电话：${phone}（请手动复制）`)
  }
}

// === 工具 ===
const statusLabel = (s: string) => statusOptions.find(o => o.value === s)?.label || s || '-'
const statusTagTypeMap: Record<string, ElTagType> = { shipped: 'primary', in_transit: 'warning', arrived: 'success', closed: 'info' }
const statusTagType = (s: string): ElTagType => statusTagTypeMap[s] || 'info'
const statusIconMap: Record<string, Component> = { shipped: Promotion, in_transit: Van, arrived: Box, closed: WarningFilled }
const statusIcon = (s: string): Component => statusIconMap[s] || Van

// 估计当前 location
const currentLocation = computed(() => {
  if (!currentEvents.value.length) return '-'
  return currentEvents.value[0].location || '-'
})

onMounted(() => { loadList() })
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.filter-bar { background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.stat-card {
  display: flex; align-items: center; gap: 12px; padding: 12px 16px;
  background: #fff; border: 1px solid #ebeef5; border-left: 4px solid;
  border-radius: 6px; transition: all 0.2s;
  &:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
  .stat-value { font-size: 20px; font-weight: 700; line-height: 1.2; }
  .stat-label { font-size: 12px; color: #909399; margin-top: 2px; }
}
.sub-text { font-size: 11px; }
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; &:hover { text-decoration: underline; } }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }

// === 详情 Drawer 样式 ===
.detail-view {
  padding: 0 4px;

  .detail-header {
    background: linear-gradient(135deg, #f0f5fa 0%, #ffffff 100%);
    border: 1px solid #e6ecf3;
    border-radius: 8px;
    padding: 16px 18px;
    margin-bottom: 20px;

    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 14px;
      padding-bottom: 14px;
      border-bottom: 1px dashed #d8e0e8;

      .tracking-no {
        font-size: 20px;
        font-weight: 700;
        color: #0C447C;
        font-family: monospace;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
      }
      .carrier-info {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #606266;
        .shipment-link { color: #0C447C; cursor: pointer; &:hover { text-decoration: underline; } }
      }
      .status-tag {
        font-size: 14px;
        padding: 6px 14px;
        height: auto;
      }
    }
    .header-meta {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      .meta-item {
        .meta-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
        .meta-value { font-size: 14px; color: #303133; font-weight: 500; }
      }
    }
  }

  .section-title {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 18px 0 12px;
    font-size: 14px;
    font-weight: 600;
    color: #0C447C;
    border-left: 3px solid #0C447C;
    padding-left: 8px;
  }
  .timeline-wrapper {
    padding: 4px 0 4px 4px;
    .timeline-content {
      background: #fafbfc;
      padding: 8px 12px;
      border-radius: 4px;
      border-left: 2px solid #e6ecf3;
      &.latest {
        background: linear-gradient(90deg, #f0f5fa 0%, #fafbfc 100%);
        border-left: 2px solid #0C447C;
      }
      .timeline-location {
        font-size: 13px;
        font-weight: 600;
        color: #303133;
        margin-bottom: 4px;
      }
      .timeline-desc {
        font-size: 12px;
        color: #606266;
        line-height: 1.6;
      }
    }
  }

  .detail-row {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px dashed #ebeef5;
    .label { width: 100px; color: #909399; font-size: 13px; }
    .value { flex: 1; color: #303133; font-size: 13px; }
  }

  .exception-info {
    background: #fef5f5;
    border: 1px solid #fbc4c4;
    border-radius: 6px;
    padding: 8px 14px;
  }
}

// === 联系物流 Dialog 样式 ===
.contact-view {
  .contact-row {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px dashed #ebeef5;
    .label { width: 90px; color: #909399; font-size: 13px; }
    .value { flex: 1; color: #303133; font-size: 14px; display: flex; align-items: center; gap: 6px; }
    .phone-text {
      font-size: 16px;
      font-weight: 600;
      color: #0C447C;
    }
  }
  .contact-row:last-child { border-bottom: none; }
}

.upload-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}

// Element Plus Drawer 内部调整
:deep(.el-drawer__header) {
  margin-bottom: 0;
  padding: 16px 20px;
  border-bottom: 1px solid #ebeef5;
  background: #fafbfc;
}
:deep(.el-drawer__body) {
  padding: 16px 20px;
}
:deep(.el-drawer__footer) {
  border-top: 1px solid #ebeef5;
  padding: 12px 20px;
  background: #fafbfc;
}
</style>
