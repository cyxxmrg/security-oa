<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">供应商发货</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建发货登记</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="发货单号 / 物流单号" clearable style="width: 220px" />
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

      <el-table :data="pagedList" stripe border v-loading="loading" :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }">
        <el-table-column prop="code" label="发货单号" width="150" fixed>
          <template #default="{ row }">
            <span class="link-text" @click="handleView(row as ShipmentItem)">{{ row.code }}</span>
          </template>
        </el-table-column>
        <el-table-column label="关联合同" min-width="180">
          <template #default="{ row }">
            <span class="link-text" @click="handleViewContract(row as ShipmentItem)">{{ row.contract?.code || row.contract_id || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="supplier_id" label="供应商 ID" width="100" align="center">
          <template #default="{ row }">#{{ row.supplier_id || '-' }}</template>
        </el-table-column>
        <el-table-column prop="carrier" label="物流公司" width="110" align="center" />
        <el-table-column prop="tracking_no" label="物流单号" width="160" />
        <el-table-column label="发货日期" width="120" align="center">
          <template #default="{ row }">{{ row.shipped_at ? String(row.shipped_at).slice(0, 10) : '-' }}</template>
        </el-table-column>
        <el-table-column label="预计到达" width="140" align="center">
          <template #default="{ row }">
            <div>{{ row.expected_arrival_at ? String(row.expected_arrival_at).slice(0, 10) : '-' }}</div>
            <div v-if="arrivalHint(row as ShipmentItem)" class="sub-text" :style="{ color: arrivalColor(row as ShipmentItem) }">
              {{ arrivalHint(row as ShipmentItem) }}
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="plain" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleView(row as ShipmentItem)">查看</el-button>
            <el-button link type="success" :disabled="!['shipped', 'in_transit'].includes(row.status)" @click="handleReceive(row as ShipmentItem)">确认到达</el-button>
            <el-button link type="warning" :disabled="!['arrived', 'received'].includes(row.status)" @click="handleAutoInbound(row as ShipmentItem)">自动入库</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[5, 10, 20]" :total="filteredList.length" layout="total, sizes, prev, pager, next, jumper" />
      </div>
    </div>

    <!-- 详情 dialog -->
    <el-dialog v-model="showDetailDialog" title="发货单详情" width="1500px" v-loading="detailLoading">
      <div v-if="currentRow" class="detail-view">
        <PurchaseFlowStepper entity-type="shipment" :entity-status="currentRow.status" />
        <PurchaseFlowHistory entity-type="shipment" :entity-id="currentRow.id" />
        <div class="detail-row"><span class="label">发货单号</span><span class="value">{{ currentRow.code }}</span></div>
        <div class="detail-row"><span class="label">关联合同</span><span class="value link-text" @click="handleViewContract(currentRow)">{{ currentRow.contract?.code || currentRow.contract_id || '-' }}</span></div>
        <div class="detail-row"><span class="label">供应商 ID</span><span class="value">#{{ currentRow.supplier_id || '-' }}</span></div>
        <div class="detail-row"><span class="label">物流公司</span><span class="value">{{ currentRow.carrier }}</span></div>
        <div class="detail-row"><span class="label">物流单号</span><span class="value" style="font-family: monospace;">{{ currentRow.tracking_no || '-' }}</span></div>
        <div class="detail-row"><span class="label">发货日期</span><span class="value">{{ currentRow.shipped_at ? String(currentRow.shipped_at).slice(0, 10) : '-' }}</span></div>
        <div class="detail-row"><span class="label">预计到达</span><span class="value">{{ currentRow.expected_arrival_at ? String(currentRow.expected_arrival_at).slice(0, 10) : '-' }}</span></div>
        <div class="detail-row"><span class="label">实际到达</span><span class="value">{{ currentRow.arrived_at ? String(currentRow.arrived_at).slice(0, 10) : '-' }}</span></div>
        <div class="detail-row"><span class="label">收货人</span><span class="value">{{ currentRow.consignee || '-' }}</span></div>
        <div class="detail-row"><span class="label">状态</span><span class="value"><el-tag :type="statusTagType(currentRow.status)" effect="plain" size="small">{{ statusLabel(currentRow.status) }}</el-tag></span></div>
        <div class="detail-row"><span class="label">备注</span><span class="value">{{ currentRow.remark || '-' }}</span></div>

        <div class="detail-section-title" v-if="currentRow.items && currentRow.items.length">物品清单</div>
        <el-table v-if="currentRow.items && currentRow.items.length" :data="currentRow.items" size="small" border>
          <el-table-column type="index" label="#" width="50" align="center" />
          <el-table-column prop="name" label="物品名" min-width="160" />
          <el-table-column prop="spec" label="规格型号" min-width="140" show-overflow-tooltip />
          <el-table-column prop="qty" label="数量" width="100" align="right" />
        </el-table>

        <div class="detail-section-title" v-if="currentRow.logistics && currentRow.logistics.length">物流事件 ({{ currentRow.logistics.length }})</div>
        <el-table v-if="currentRow.logistics && currentRow.logistics.length" :data="currentRow.logistics" size="small" border>
          <el-table-column label="时间" width="160">
            <template #default="{ row }">{{ row.event_at ? String(row.event_at).slice(0, 16) : '-' }}</template>
          </el-table-column>
          <el-table-column prop="location" label="位置" min-width="160" show-overflow-tooltip />
          <el-table-column prop="status" label="状态" width="120" />
          <el-table-column prop="description" label="说明" min-width="200" show-overflow-tooltip />
          <el-table-column prop="operator" label="操作人" width="100" />
        </el-table>
      </div>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Refresh, Plus, Van, Promotion, Box, List, Warning, Document } from '@element-plus/icons-vue'
import { purchase } from '@/api/modules'
import { unwrapList } from '@/utils/response'
import { purchaseFlow } from '@/api/purchase-flow'
import PurchaseFlowStepper from '@/components/PurchaseFlowStepper.vue'
import PurchaseFlowHistory from '@/components/PurchaseFlowHistory.vue'

type ElTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

interface ShipmentItem {
  id: number
  code?: string
  contract_id?: number
  supplier_id?: number
  carrier?: string
  tracking_no?: string
  shipped_at?: string
  expected_arrival_at?: string
  arrived_at?: string
  consignee?: string
  remark?: string
  status: string
  contract?: { code?: string } | null
  items?: Array<{ name?: string; spec?: string; qty?: number | string }>
  logistics?: Array<{ event_at?: string; location?: string; status?: string; description?: string; operator?: string }>
  [key: string]: unknown
}

// === 状态选项（与后端 shipment status 一致）===
const statusOptions = [
  { value: 'shipped', label: '已发货' },
  { value: 'in_transit', label: '在途' },
  { value: 'arrived', label: '已到达' },
  { value: 'closed', label: '已关闭' }
]

// === 列表 / 加载 ===
const loading = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<ShipmentItem[]>([])
const stats = reactive({ shipped: 0, in_transit: 0, arrived: 0, closed: 0, total: 0 })

const searchForm = reactive({ keyword: '', status: '', carrier: '' })

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter(r => (r.code || '').toLowerCase().includes(kw) || (r.tracking_no || '').toLowerCase().includes(kw))
  }
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  if (searchForm.carrier) arr = arr.filter(r => r.carrier === searchForm.carrier)
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const statCards = computed(() => [
  { label: '在途数量', value: (stats.shipped || 0) + (stats.in_transit || 0), icon: Van, color: '#0C447C' },
  { label: '今日发货', value: list.value.filter(l => l.shipped_at && String(l.shipped_at).slice(0, 10) === new Date().toISOString().slice(0, 10)).length, icon: Promotion, color: '#1D9E75' },
  { label: '本月到货', value: list.value.filter(l => l.status === 'arrived' && l.arrived_at && String(l.arrived_at).startsWith(new Date().toISOString().slice(0, 7))).length, icon: Box, color: '#534AB7' },
  { label: '已关闭', value: stats.closed || 0, icon: Document, color: '#909399' }
])

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 200, page: 1 }
    if (searchForm.status) params.status = searchForm.status
    if (searchForm.carrier) params.carrier = searchForm.carrier
    const res = await purchase.getShipments(params)
    // V0.6.3 不再解包, 兼容两种形态
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  try {
    const res = await purchase.getShipmentStats()
    Object.assign(stats, (res as Partial<typeof stats>) || {})
  } catch { /* 静默 */ }
}

const handleSearch = () => { page.value = 1 }
const handleReset = () => {
  searchForm.keyword = ''
  searchForm.status = ''
  searchForm.carrier = ''
  page.value = 1
  loadList()
}

// === 详情 ===
const showDetailDialog = ref(false)
const detailLoading = ref(false)
const currentRow = ref<ShipmentItem | null>(null)
const handleView = async (row: ShipmentItem) => {
  currentRow.value = row
  showDetailDialog.value = true
  detailLoading.value = true
  try {
    const res = await purchase.getShipmentDetail(row.id)
    if (res && (res as { id?: number }).id) currentRow.value = res as ShipmentItem
  } catch { /* 拦截器已提示 */ }
  finally { detailLoading.value = false }
}
const handleViewContract = (row: ShipmentItem) => {
  ElMessage.info(`查看合同：${row.contract?.code || row.contract_id || '-'}（占位）`)
}

// === 新建 / 编辑 / 收货：后端无对应端点（后端仅 shipContract 触发） ===
//    保留 mock 占位但显式提示用户用「合同 → 发货」流程
const handleAdd = () => {
  ElMessage.warning('请到「采购合同」页面，对已签订合同点击「发货」按钮生成发货单')
}
const handleEdit = (_row: ShipmentItem) => {
  ElMessage.warning('发货单不可直接编辑；如需修改请走合同流程')
}
const handleReceive = async (row: ShipmentItem) => {
  // V0.6.2 走 flow API: 先 mark arrived, 再 confirm inbound
  try {
    await purchaseFlow.markArrived(row.id)
    ElMessage.success('已标记到货')
    await purchaseFlow.confirmInbound(row.id)
    ElMessage.success('已确认入库')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

// === 自动入库 (采购员手动触发) ===
const handleAutoInbound = async (row: ShipmentItem) => {
  try {
    await purchaseFlow.autoCreateInbound(row.id)
    ElMessage.success('已自动生成入库单')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

// === 日期提示 ===
const arrivalHint = (row: ShipmentItem) => {
  if (row.status === 'arrived' || row.status === 'closed') return ''
  if (!row.expected_arrival_at) return ''
  const today = new Date()
  const exp = new Date(row.expected_arrival_at)
  if (isNaN(exp.getTime())) return ''
  const diff = Math.round((exp.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
  if (diff > 0) return `还剩 ${diff} 天`
  if (diff === 0) return '今天到达'
  return `已延期 ${-diff} 天`
}
const arrivalColor = (row: ShipmentItem) => {
  if (row.status === 'arrived' || row.status === 'closed') return '#909399'
  if (!row.expected_arrival_at) return '#909399'
  const today = new Date()
  const exp = new Date(row.expected_arrival_at)
  const diff = Math.round((exp.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
  if (diff > 0) return '#1D9E75'
  if (diff === 0) return '#BA7517'
  return '#A32D2D'
}

// === 工具 ===
const statusLabel = (s: string) => statusOptions.find(o => o.value === s)?.label || s || '-'
const statusTagTypeMap: Record<string, ElTagType> = { shipped: 'primary', in_transit: 'warning', arrived: 'success', closed: 'info' }
const statusTagType = (s: string): ElTagType => statusTagTypeMap[s] || 'info'

onMounted(() => { loadList(); loadStats() })
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

.detail-view {
  .detail-row {
    display: flex; padding: 10px 0; border-bottom: 1px dashed #ebeef5;
    .label { width: 110px; color: #909399; font-size: 13px; }
    .value { flex: 1; color: #303133; font-size: 14px; }
    &:last-child { border-bottom: none; }
  }
  .detail-section-title {
    margin: 16px 0 8px; font-size: 13px; font-weight: 600; color: #0C447C;
    border-left: 3px solid #0C447C; padding-left: 8px;
  }
}

.items-block {
  width: 100%; border: 1px solid #ebeef5; border-radius: 4px; padding: 8px 12px; background: #fafbfc;
  .items-header {
    display: grid; grid-template-columns: 1.5fr 1.5fr 120px 70px; gap: 8px;
    font-size: 12px; color: #909399; padding: 0 0 8px; border-bottom: 1px dashed #ebeef5;
    span { padding: 0 4px; }
  }
  .items-row {
    display: grid; grid-template-columns: 1.5fr 1.5fr 120px 70px; gap: 8px;
    align-items: center; padding: 6px 0;
  }
  .items-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0 0; border-top: 1px dashed #ebeef5; margin-top: 4px;
    .items-total { font-size: 13px; color: #0C447C; font-weight: 600; }
  }
}
</style>
