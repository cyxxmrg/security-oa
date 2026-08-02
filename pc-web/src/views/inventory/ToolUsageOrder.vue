<template>
  <div class="page-container">
    <div class="page-header"><h2>工具使用单</h2></div>

    <div class="filter-bar">
      <el-input v-model="searchKey" placeholder="搜索单号 / 领用人 / 仓库" clearable style="width: 240px" :prefix-icon="Search" @keyup.enter="loadList(1)" @clear="loadList(1)" />
      <el-select v-model="filterStatus" placeholder="状态" clearable style="width: 120px" @change="loadList(1)">
        <el-option label="使用中" value="active" />
        <el-option label="已关闭" value="closed" />
      </el-select>
      <el-button type="primary" plain :icon="Plus" @click="openCreate">新增使用单</el-button>
    </div>

    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column label="单号" width="180">
          <template #default="{ row }"><span class="record-no">{{ row.code }}</span></template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'closed' ? 'info' : 'primary'" size="small">{{ row.status === 'closed' ? '已关闭' : '使用中' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="领用人" width="100">
          <template #default="{ row }">{{ row.applicant?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="仓库" min-width="120">
          <template #default="{ row }">{{ row.warehouse?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="关联项目" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ row.project?.name || '未关联' }}</template>
        </el-table-column>
        <el-table-column label="领用数" width="90" align="right">
          <template #default="{ row }"><span style="font-weight: 600; color: #A32D2D">-{{ row.checkout_qty || 0 }}</span></template>
        </el-table-column>
        <el-table-column label="退还数" width="90" align="right">
          <template #default="{ row }"><span style="font-weight: 600; color: #1D9E75">+{{ row.return_qty || 0 }}</span></template>
        </el-table-column>
        <el-table-column label="创建时间" width="150">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
        <el-table-column label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openDetail(row as OrderRow)">详情</el-button>
            <el-button v-if="row.status === 'active'" link type="danger" size="small" @click="handleClose(row as OrderRow)">关闭</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          background
          layout="total, prev, pager, next"
          :total="pagination.total"
          :current-page="pagination.page"
          :page-size="pagination.per_page"
          @current-change="(p: number) => loadList(p)"
        />
      </div>
    </div>

    <!-- 新增使用单 -->
    <el-dialog v-model="showCreate" title="新增工具使用单" width="620px" :close-on-click-modal="false">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="90px">
        <el-form-item label="仓库" prop="warehouse_id">
          <el-select v-model="createForm.warehouse_id" placeholder="选择仓库" style="width: 100%">
            <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="领用人">
          <el-input :model-value="curUser?.name || ''" disabled>
            <template #prefix><el-icon><User /></el-icon></template>
          </el-input>
        </el-form-item>
        <el-form-item label="关联项目">
          <el-select v-model="createForm.project_id" filterable clearable placeholder="选择项目(可选)" style="width: 100%">
            <el-option v-for="p in projectOptions" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="createForm.remark" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="工具用途说明(可选)" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="handleCreate">创建并进入明细</el-button>
      </template>
    </el-dialog>

    <!-- 详情 -->
    <el-dialog v-model="showDetail" title="工具使用单明细" width="1440px" top="3vh" :close-on-click-modal="false">
      <template v-if="detail">
        <div class="detail-head">
          <el-descriptions :column="4" border>
            <el-descriptions-item label="单号" :span="2"><span class="record-no">{{ detail.order.code }}</span></el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="detail.order.status === 'closed' ? 'info' : 'primary'" size="small">{{ detail.order.status === 'closed' ? '已关闭' : '使用中' }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="领用人">{{ detail.order.applicant?.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="仓库">{{ detail.order.warehouse?.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="关联项目">{{ detail.order.project?.name || '未关联' }}</el-descriptions-item>
            <el-descriptions-item label="累计领用"><span style="font-weight: 600; color: #A32D2D">-{{ detail.total_checkout || 0 }}</span></el-descriptions-item>
            <el-descriptions-item label="累计退还"><span style="font-weight: 600; color: #1D9E75">+{{ detail.total_return || 0 }}</span></el-descriptions-item>
            <el-descriptions-item label="备注" :span="4">{{ detail.order.remark || '-' }}</el-descriptions-item>
          </el-descriptions>
          <div class="detail-actions">
            <el-button v-if="detail.order.status === 'active'" type="warning" :icon="TakeawayBox" :loading="acting" @click="openMovement('checkout')">工具领用</el-button>
            <el-button v-if="detail.order.status === 'active'" type="success" :icon="RefreshLeft" :loading="acting" @click="openMovement('return')">工具退还</el-button>
            <el-button v-if="detail.order.status === 'active'" plain @click="handleCloseFromDetail">关闭单据</el-button>
            <span class="muted action-tip">领用自动出库扣减库存，退还自动入库增加库存</span>
          </div>
        </div>

        <!-- 左右两列：左=工具使用明细 右=工具在库信息 -->
        <div class="detail-columns">
          <div class="column-left">
            <div class="column-title"><el-icon><Goods /></el-icon> 工具使用明细</div>
            <el-table :data="detail.items" stripe border size="small" max-height="440" style="width: 100%">
              <el-table-column label="时间" width="140">
                <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
              </el-table-column>
              <el-table-column label="类型" width="76" align="center">
                <template #default="{ row }">
                  <el-tag :type="row.type === 'tool_checkout' ? 'danger' : 'success'" size="small">{{ row.type === 'tool_checkout' ? '领用' : '退还' }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="编码" width="120">
                <template #default="{ row }"><span class="item-code">{{ row.inventoryItem?.code || '-' }}</span></template>
              </el-table-column>
              <el-table-column label="工具名称" min-width="140" show-overflow-tooltip>
                <template #default="{ row }">{{ row.inventoryItem?.name || '-' }}</template>
              </el-table-column>
              <el-table-column label="规格" width="110" show-overflow-tooltip>
                <template #default="{ row }">{{ row.inventoryItem?.specification || row.inventoryItem?.spec || '-' }}</template>
              </el-table-column>
              <el-table-column label="单位" width="56" align="center">
                <template #default="{ row }">{{ row.inventoryItem?.unit || '-' }}</template>
              </el-table-column>
              <el-table-column label="数量" width="86" align="right">
                <template #default="{ row }">
                  <span :style="{ fontWeight: 600, color: row.type === 'tool_checkout' ? '#A32D2D' : '#1D9E75' }">{{ row.type === 'tool_checkout' ? '-' : '+' }}{{ row.quantity }}</span>
                </template>
              </el-table-column>
              <el-table-column label="操作人" width="90">
                <template #default="{ row }">{{ row.operator?.name || '-' }}</template>
              </el-table-column>
              <el-table-column prop="remark" label="备注" min-width="100" show-overflow-tooltip />
            </el-table>
            <el-empty v-if="!detail.items.length" :image-size="50" description="暂无领用/退还记录，点击上方「工具领用」开始" />
          </div>

          <div class="column-right">
            <div class="column-title"><el-icon><Box /></el-icon> 工具在库信息</div>
            <el-input v-model="stockSearch" placeholder="搜索名称 / 编码 / 规格" clearable :prefix-icon="Search" size="small" style="margin-bottom: 8px" @input="onStockSearch" @clear="loadStockList(1)" />
            <el-table v-loading="stockLoading" :data="filteredStockList" stripe border size="small" max-height="440" style="width: 100%">
              <el-table-column label="编码" width="110">
                <template #default="{ row }"><span class="item-code">{{ row.code }}</span></template>
              </el-table-column>
              <el-table-column label="名称" min-width="140" show-overflow-tooltip prop="name" />
              <el-table-column label="规格" width="100" show-overflow-tooltip>
                <template #default="{ row }">{{ row.specification || row.spec || '-' }}</template>
              </el-table-column>
              <el-table-column label="单位" width="54" align="center" prop="unit" />
              <el-table-column label="在库数量" width="90" align="center">
                <template #default="{ row }">
                  <el-tag :type="(row.current_stock ?? 0) <= 0 ? 'danger' : (row.current_stock ?? 0) < 10 ? 'warning' : 'success'" size="small" effect="plain">{{ row.current_stock ?? 0 }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="本单借出" width="88" align="right">
                <template #default="{ row }">
                  <span v-if="borrowedMap[row.id]" style="font-weight: 600; color: #0C447C">{{ borrowedMap[row.id] }}</span>
                  <span v-else class="muted">-</span>
                </template>
              </el-table-column>
            </el-table>
            <el-empty v-if="!filteredStockList.length && !stockLoading" :image-size="50" description="暂无在库信息" />
            <div class="pagination-wrap" style="margin-top: 8px">
              <el-pagination
                background
                small
                layout="total, prev, pager, next"
                :total="stockTotal"
                :current-page="stockPage"
                :page-size="stockPageSize"
                @current-change="(p: number) => loadStockList(p)"
              />
            </div>
          </div>
        </div>
      </template>
      <template #footer>
        <el-button @click="showDetail = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 领用 / 退还操作 -->
    <el-dialog v-model="showMovement" :title="movementType === 'checkout' ? '工具领用' : '工具退还'" width="1200px" :close-on-click-modal="false" top="8vh">
      <el-alert
        :title="movementType === 'checkout' ? '选择要领用的工具并填写数量，确认后自动出库并扣减库存' : '选择要退还的工具并填写数量，确认后自动入库并增加库存'"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 12px"
      />
      <div class="movement-toolbar">
        <el-button type="primary" plain size="small" :icon="Plus" @click="openPickerBatch">批量选择工具</el-button>
        <span class="muted">已选 {{ movementItems.filter(r => r.item).length }} 项</span>
      </div>
      <el-table :data="movementItems" stripe border style="width: 100%" max-height="340">
        <el-table-column type="index" label="#" width="42" />
        <el-table-column label="编码" width="120">
          <template #default="{ row }"><span v-if="row.item" class="item-code">{{ row.item.code }}</span><span v-else class="muted">-</span></template>
        </el-table-column>
        <el-table-column label="工具名称" min-width="150">
          <template #default="{ row, $index }">
            <div style="display: flex; gap: 4px; align-items: center">
              <span v-if="row.item" style="flex: 1">{{ row.item.name }}</span>
              <el-button size="small" type="primary" link @click="openPicker($index)">{{ row.item ? '更换' : '选择工具' }}</el-button>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="规格" width="110" show-overflow-tooltip>
          <template #default="{ row }">{{ row.item?.specification || row.item?.spec || '-' }}</template>
        </el-table-column>
        <el-table-column label="单位" width="56" align="center">
          <template #default="{ row }">{{ row.item?.unit || '-' }}</template>
        </el-table-column>
        <el-table-column label="在库数量" width="90" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.item" :type="(row.item.current_stock ?? 0) <= 0 ? 'danger' : 'success'" size="small" effect="plain">{{ row.item.current_stock ?? 0 }}</el-tag>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column :label="movementType === 'checkout' ? '领用数量' : '退还数量'" width="130">
          <template #default="{ row }">
            <el-input-number v-model="row.quantity" :min="1" :max="movementType === 'checkout' ? (row.item?.current_stock || 99999) : 99999" :step="1" size="small" style="width: 110px" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="55" align="center">
          <template #default="{ $index }">
            <el-button type="danger" link size="small" :icon="Delete" @click="movementItems.splice($index, 1)" />
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="movementItems.length === 0" :image-size="50" description="暂无工具，点击「批量选择工具」添加" />
      <div style="margin-top: 12px">
        <el-input v-model="movementRemark" placeholder="操作备注(可选)" maxlength="200" clearable />
      </div>
      <template #footer>
        <el-button @click="showMovement = false">取消</el-button>
        <el-button :type="movementType === 'checkout' ? 'warning' : 'success'" :loading="submitting" @click="submitMovement">
          确认{{ movementType === 'checkout' ? '领用' : '退还' }}
        </el-button>
      </template>
    </el-dialog>

    <InventoryItemPicker :show="pickerVisible" :multiple="true" :selected-ids="pickedItemIds" @select="(items: InventoryItem[]) => onPickerSelect(items)" @close="pickerVisible = false" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Search, Plus, Goods, Box, Delete, User, TakeawayBox, RefreshLeft } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapPaginate } from '@/utils/response'
import InventoryItemPicker from './components/InventoryItemPicker.vue'
import type { InventoryItem, WarehouseOption } from './types'

interface BusinessOption { id: number; name: string }

interface OrderRow extends Record<string, unknown> {
  id: number
  code: string
  status: string
  applicant?: { id: number; name: string } | null
  warehouse?: { id: number; name: string } | null
  project?: { id: number; name: string } | null
  checkout_qty?: number | null
  return_qty?: number | null
  movement_count?: number
  remark?: string | null
  created_at?: string
}

interface DetailItem extends Record<string, unknown> {
  id: number
  record_no: string
  type: string
  quantity: number
  inventory_item_id: number
  inventoryItem?: { id: number; code: string; name: string; specification?: string; spec?: string; unit?: string } | null
  operator?: { id: number; name: string } | null
  remark?: string | null
  created_at?: string
}

interface SummaryRow extends Record<string, unknown> {
  inventory_item_id: number
  checkout_qty: number
  return_qty: number
  borrowed: number
}

interface DetailData {
  order: OrderRow
  items: DetailItem[]
  summary: SummaryRow[]
  total_checkout: number
  total_return: number
}

interface StockRow extends Record<string, unknown> {
  id: number
  code: string
  name: string
  specification?: string
  spec?: string
  unit?: string
  current_stock?: number
}

interface MovementRow { uid: string; item: InventoryItem | null; quantity: number }

const searchKey = ref('')
const filterStatus = ref('')
const list = ref<OrderRow[]>([])
const loading = ref(false)
const pagination = reactive({ page: 1, per_page: 15, total: 0 })

const warehouseOptions = ref<WarehouseOption[]>([])
const projectOptions = ref<BusinessOption[]>([])
const curUser = ref<BusinessOption | null>(null)

const formatDate = (s?: string | null) => {
  if (!s) return '-'
  const t = s.replace('T', ' ').slice(0, 16)
  return t || s
}

async function loadList(page = 1) {
  pagination.page = page
  loading.value = true
  try {
    const res = await get('/inventory/tool-usage-orders', {
      page,
      per_page: pagination.per_page,
      keyword: searchKey.value || undefined,
      status: filterStatus.value || undefined,
    })
    const pag = unwrapPaginate(res)
    list.value = pag.list as OrderRow[]
    pagination.total = pag.total
  } catch (e) {
    console.error('[loadList]', e)
    list.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

async function loadWarehouses() {
  try {
    const res = await get('/inventory/warehouses')
    warehouseOptions.value = (res.data || res || []) as WarehouseOption[]
  } catch (e) { console.warn('[loadWarehouses]', e) }
}

async function loadProjects() {
  try {
    const res = await get<Record<string, unknown>>('/projects', { per_page: 500 })
    const d = (res.data ?? res) as { data?: unknown; items?: unknown }
    projectOptions.value = ((d.data || d.items || d) as unknown) as BusinessOption[]
  } catch (e) { console.warn('[loadProjects]', e) }
}

async function loadCurrentUser() {
  try {
    const stored = localStorage.getItem('oa_user_info')
    if (stored) {
      const u = JSON.parse(stored) as { id?: number; name?: string; username?: string }
      curUser.value = { id: u.id ?? 0, name: u.name || u.username || '' }
    }
    const res = await get('/auth/me')
    const u = ((res?.data?.user || res?.data) ?? null) as { id?: number; name?: string; username?: string } | null
    if (u && u.id) curUser.value = { id: u.id, name: u.name || u.username || '' }
  } catch (e) { /* ignore */ }
}

// ===== 新增使用单 =====
const showCreate = ref(false)
const creating = ref(false)
const createFormRef = ref<FormInstance | null>(null)
const createForm = reactive({
  warehouse_id: null as number | null,
  project_id: null as number | null,
  remark: '',
})
const createRules = {
  warehouse_id: [{ required: true, message: '请选择仓库', trigger: 'change' }],
}

function openCreate() {
  createForm.warehouse_id = warehouseOptions.value[0]?.id || null
  createForm.project_id = null
  createForm.remark = ''
  showCreate.value = true
}

async function handleCreate() {
  if (!createFormRef.value) return
  await createFormRef.value.validate().catch(() => null)
  if (!createForm.warehouse_id) { ElMessage.warning('请选择仓库'); return }
  creating.value = true
  try {
    const res = await post('/inventory/tool-usage-orders', {
      warehouse_id: createForm.warehouse_id,
      project_id: createForm.project_id,
      applicant_id: curUser.value?.id || undefined,
      remark: createForm.remark,
    })
    const order = ((res?.data ?? res) || {}) as OrderRow
    ElMessage.success(`使用单创建成功, 单号: ${order.code || ''}`)
    showCreate.value = false
    await loadList(pagination.page)
    await openDetail(order)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '创建失败')
  } finally {
    creating.value = false
  }
}

// ===== 详情 =====
const showDetail = ref(false)
const detail = ref<DetailData | null>(null)
const acting = ref(false)

async function openDetail(row: OrderRow) {
  detail.value = null
  showDetail.value = true
  try {
    const res = await get(`/inventory/tool-usage-orders/${row.id}`)
    detail.value = (res?.data ?? res) as DetailData
    await loadStockList(1)
  } catch (e) {
    console.error(e)
    showDetail.value = false
    ElMessage.error('加载详情失败')
  }
}

async function reloadDetail() {
  const cur = detail.value
  if (!cur) return
  try {
    const res = await get(`/inventory/tool-usage-orders/${cur.order.id}`)
    detail.value = (res?.data ?? res) as DetailData
    await loadStockList(stockPage.value)
  } catch (e) {
    console.error('[reloadDetail]', e)
    ElMessage.error('刷新详情失败')
  }
}

// 右侧在库信息
const stockSearch = ref('')
const filteredStockList = ref<StockRow[]>([])
const stockLoading = ref(false)
const stockTotal = ref(0)
const stockPage = ref(1)
const stockPageSize = 50
let stockSearchTimer: number | null = null

function onStockSearch() {
  if (stockSearchTimer) window.clearTimeout(stockSearchTimer)
  stockSearchTimer = window.setTimeout(() => loadStockList(1), 300)
}

async function loadStockList(page = 1) {
  stockPage.value = page
  stockLoading.value = true
  try {
    const res = await get('/inventory', {
      keyword: stockSearch.value || undefined,
      page,
      per_page: stockPageSize,
    })
    const pag = unwrapPaginate(res)
    filteredStockList.value = pag.list as StockRow[]
    stockTotal.value = pag.total
  } catch (e) {
    console.warn('[loadStockList]', e)
    filteredStockList.value = []
    stockTotal.value = 0
  } finally {
    stockLoading.value = false
  }
}

const borrowedMap = computed<Record<number, number>>(() => {
  const map: Record<number, number> = {}
  if (!detail.value) return map
  for (const s of detail.value.summary) {
    if (s.borrowed > 0) map[s.inventory_item_id] = s.borrowed
  }
  return map
})

async function handleClose(row: OrderRow) {
  try {
    await ElMessageBox.confirm(`确认关闭使用单「${row.code}」? 关闭后不可再领用/退还。`, '关闭确认', { type: 'warning' })
  } catch { return }
  acting.value = true
  try {
    await post(`/inventory/tool-usage-orders/${row.id}/close`)
    ElMessage.success('使用单已关闭')
    if (detail.value?.order.id === row.id) await reloadDetail()
    await loadList(pagination.page)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '关闭失败')
  } finally {
    acting.value = false
  }
}

async function handleCloseFromDetail() {
  const cur = detail.value
  if (!cur) return
  await handleClose(cur.order)
}

// ===== 领用 / 退还 =====
const showMovement = ref(false)
const movementType = ref<'checkout' | 'return'>('checkout')
const movementItems = ref<MovementRow[]>([])
const movementRemark = ref('')
const submitting = ref(false)
const pickerVisible = ref(false)
const pickerIndex = ref(-1)

const pickedItemIds = computed(() => movementItems.value.filter(r => r.item).map(r => r.item!.id))

function openMovement(type: 'checkout' | 'return') {
  movementType.value = type
  movementRemark.value = ''
  movementItems.value = [{ uid: String(Date.now()) + String(Math.random()), item: null, quantity: 1 }]
  showMovement.value = true
}

function openPicker(idx: number) {
  pickerIndex.value = idx
  pickerVisible.value = true
}

function openPickerBatch() {
  pickerIndex.value = movementItems.value.length
  pickerVisible.value = true
}

function onPickerSelect(items: InventoryItem[]) {
  if (!items || !items.length) return
  let idx = pickerIndex.value
  while (idx < movementItems.value.length && !movementItems.value[idx]?.item) {
    movementItems.value.splice(idx, 1)
  }
  for (const it of items) {
    const existingIdx = movementItems.value.findIndex(r => r.item?.id === it.id)
    if (existingIdx >= 0) movementItems.value.splice(existingIdx, 1)
  }
  for (const it of items) {
    movementItems.value.push({
      uid: String(Date.now()) + String(Math.random()) + String(it.id),
      item: { ...it },
      quantity: Math.min(1, it.current_stock || 1),
    })
  }
  pickerIndex.value = -1
}

async function submitMovement() {
  const cur = detail.value
  if (!cur) return
  const validItems = movementItems.value.filter(r => r.item)
  if (validItems.length === 0) { ElMessage.warning('请至少选择一种工具'); return }
  submitting.value = true
  try {
    const action = movementType.value === 'checkout' ? 'checkout' : 'return'
    await post(`/inventory/tool-usage-orders/${cur.order.id}/${action}`, {
      items: validItems.map(r => ({ item_id: r.item!.id, quantity: r.quantity })),
      remark: movementRemark.value || undefined,
    })
    ElMessage.success(movementType.value === 'checkout' ? '领用成功，已自动出库' : '退还成功，已自动入库')
    showMovement.value = false
    await reloadDetail()
    await loadList(pagination.page)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadList(1)
  loadWarehouses()
  loadProjects()
  loadCurrentUser()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header { margin-bottom: 16px; h2 { font-size: 20px; color: #0C447C; margin: 0; } }
.filter-bar {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  margin-bottom: 16px; padding: 16px;
  background: #fff; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}
.content-card {
  background: #fff; border-radius: 8px; padding: 16px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.muted { color: #c0c4cc; }
.record-no { font-family: "DIN Pro", monospace; font-weight: 600; color: #0C447C; }
.item-code { font-family: "DIN Pro", monospace; font-weight: 500; color: #0C447C; font-size: 12px; }
.detail-head {
  display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;
}
.detail-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.action-tip { font-size: 12px; }
.detail-columns {
  display: flex; gap: 16px; align-items: stretch;
}
.column-left, .column-right {
  flex: 1; min-width: 0; background: #fff; border: 1px solid #e8ecf1;
  border-radius: 8px; padding: 12px;
}
.column-title {
  font-size: 14px; font-weight: 600; color: #0C447C;
  margin-bottom: 10px; padding-bottom: 8px;
  border-bottom: 2px solid #e6f1fb;
  display: flex; align-items: center; gap: 6px;
}
.movement-toolbar {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  margin-bottom: 12px; padding: 8px 12px;
  background: #f6f8fa; border-radius: 6px;
}
:deep(.el-dialog__body) { padding-top: 12px; }
</style>
