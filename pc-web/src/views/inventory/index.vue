<template>
  <div class="inventory-container">
    <InventoryWarningBanner
      :low-stock="warnings.low_stock"
      :expiring="warnings.expiring"
      @click="handleWarningClick"
    />

    <!-- 库存统计卡片 -->
    <div class="stats-row">
      <el-card shadow="never" class="stats-card stats-value">
        <div class="stats-label">库存总金额</div>
        <div class="stats-number">¥{{ formatMoney(statsData.total_value) }}</div>
      </el-card>
      <el-card shadow="never" class="stats-card">
        <div class="stats-label">物料种类</div>
        <div class="stats-number">{{ statsData.total_items }}</div>
      </el-card>
      <el-card shadow="never" class="stats-card">
        <div class="stats-label">总库存量</div>
        <div class="stats-number">{{ statsData.total_stock }}</div>
      </el-card>
      <el-card shadow="never" class="stats-card stats-low">
        <div class="stats-label">低库存预警</div>
        <div class="stats-number" :class="{ 'is-warning': statsData.low_stock_cnt > 0 }">{{ statsData.low_stock_cnt }}</div>
      </el-card>
      <el-card shadow="never" class="stats-card">
        <div class="stats-label">仓库数量</div>
        <div class="stats-number">{{ statsData.warehouse_cnt }}</div>
      </el-card>
      <el-card shadow="never" class="stats-card stats-out">
        <div class="stats-label">缺货</div>
        <div class="stats-number" :class="{ 'is-danger': (statsData.out_of_stock || 0) > 0 }">{{ statsData.out_of_stock || 0 }}</div>
      </el-card>
    </div>

    <InventoryToolbar
      v-model="searchKey"
      @search="onSearch"
      @import="showBatchImport = true"
      @export="handleBatchExport"
      @create="handleCreateItem"
    />

    <!-- 仓库筛选器 -->
    <div class="filter-bar">
      <div class="filter-item">
        <span class="filter-label">仓库筛选：</span>
        <el-select v-model="filterWarehouseId" placeholder="全部仓库" clearable style="width:200px" @change="onWarehouseChange">
          <el-option label="全部仓库" value="" />
          <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
        </el-select>
      </div>
    </div>

    <InventoryBatchBar
      :count="selectedIds.length"
      @edit="handleBatchEdit"
      @set-active="handleBatchSetActive"
      @set-inactive="handleBatchSetInactive"
      @delete="handleBatchDelete"
      @clear="clearSelection"
    />

    <div class="inventory-body">
      <div class="inventory-body__tree">
        <CategoryTree
          ref="categoryTreeRef"
          v-model="currentCategoryId"
          :warehouse-id="filterWarehouseId"
          @refresh="onTreeRefresh"
        />
      </div>
      <div class="inventory-body__table">
        <ItemTable
          :list="itemList"
          :total="itemTotal"
          :page="itemPage"
          :per-page="itemPerPage"
          :loading="itemLoading"
          :warehouse-options="warehouseOptions"
          :current-category="currentCategoryId"
          :selected-ids="selectedIds"
          @search="onItemSearch"
          @page-change="onItemPageChange"
          @size-change="onItemSizeChange"
          @detail="showItemDetail"
          @edit="handleEditItem"
          @delete="handleDeleteItem"
          @stock-in="handleStockIn"
          @stock-out="handleStockOut"
          @selection-change="onSelectionChange"
        />
      </div>
    </div>

    <ItemDetailDialog
      v-model:visible="drawerVisible"
      :item="currentItem"
      @edit="handleEditItem"
    />

    <ItemFormDrawer
      v-model:visible="formDrawerVisible"
      :item="formEditingItem"
      @success="handleFormSuccess"
    />

    <BatchImportDialog
      v-model:visible="showBatchImport"
      @success="handleImportSuccess"
    />

    <InventoryBatchEditDialog
      v-model:visible="batchEditVisible"
      :submitting="batchEditSubmitting"
      :warehouse-options="warehouseOptions"
      :category-options="categoryOptionsForEdit"
      :selected-count="selectedIds.length"
      @submit="submitBatchEdit"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * Inventory 主页 — v0.3.14 C4 拆分版
 *
 * 新增/抽离子组件:
 *  - InventoryWarningBanner.vue    预警横幅
 *  - InventoryToolbar.vue         顶部工具条
 *  - InventoryBatchBar.vue        批量操作浮动栏
 *  - InventoryBatchEditDialog.vue 批量编辑字段 dialog
 *
 * 既有组件:
 *  - CategoryTree / ItemTable / ItemDrawer / ItemFormDrawer / BatchImportDialog
 */
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { inventory } from '@/api/modules'
import { unwrapList, unwrapStats, unwrapPaginate } from '@/utils/response'
import { exportExcelLike } from '@/utils/exporter'
import CategoryTree from './components/CategoryTree.vue'
import ItemTable from './components/ItemTable.vue'
import ItemDetailDialog from './components/ItemDetailDialog.vue'
import ItemFormDrawer from './components/ItemFormDrawer.vue'
import BatchImportDialog from './components/BatchImportDialog.vue'
import InventoryWarningBanner from './components/InventoryWarningBanner.vue'
import InventoryToolbar from './components/InventoryToolbar.vue'
import InventoryBatchBar from './components/InventoryBatchBar.vue'
import InventoryBatchEditDialog from './components/InventoryBatchEditDialog.vue'
import type { InventoryItem, WarehouseOption, ItemCategory as CategoryNode } from './types'

// 库存统计
const statsData = reactive({
  total_value: 0, total_items: 0, total_stock: 0,
  low_stock_cnt: 0, out_of_stock: 0, warehouse_cnt: 0,
})

// 仓库筛选
const filterWarehouseId = ref<number | null>(null)

const formatMoney = (n: number) => {
  if (!n || isNaN(n)) return '0.00'
  return n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadStats() {
  try {
    const res = await inventory.stats()
    const d = unwrapStats(res) as Record<string, unknown>
    statsData.total_value = Number(d.total_value || 0)
    statsData.total_items = Number(d.total_items || 0)
    statsData.total_stock = Number(d.total_stock || 0)
    statsData.low_stock_cnt = Number(d.low_stock_cnt || 0)
    statsData.out_of_stock = Number(d.out_of_stock || 0)
    statsData.warehouse_cnt = Number(d.warehouse_cnt || 0)
  } catch { /* silent */ }
}

function onWarehouseChange() {
  itemPage.value = 1
  lastSearch.value.warehouse_id = filterWarehouseId.value
  loadItems()
}

// 分类树节点
export type _CategoryNode = CategoryNode  // 兼容旧引用

// 批量更新响应
interface BatchUpdateResponse {
  data?: { updated_count?: number }
  [key: string]: unknown
}

// 批量删除响应
interface BatchDeleteResponse {
  data?: {
    deleted_count?: number
    skipped_count?: number
    skipped?: unknown[]
  }
  [key: string]: unknown
}

// 物品查询参数
interface ItemListParams {
  page?: number
  per_page?: number
  keyword?: string
  search?: string
  warehouse_id?: number | null
  status?: string
  sort?: string
  order?: string
  category_id?: number
  [key: string]: unknown
}

// 导出参数
interface ExportParams {
  ids?: number[]
  keyword?: string
  warehouse_id?: number
  category_id?: number
  status?: string
}

// API 错误
interface ApiError {
  message?: string
  response?: { data?: { message?: string } }
}

const router = useRouter()

const currentCategoryId = ref<number | null>(null)
const searchKey = ref('')

const warnings = reactive({ total: 0, low_stock: 0, expiring: 0 })

const itemList = ref<InventoryItem[]>([])
const itemTotal = ref(0)
const itemPage = ref(1)
const itemPerPage = ref(15)
const itemLoading = ref(false)
const lastSearch = ref<{ keyword: string; warehouse_id: number | null; status: string; sort: string; order: string }>({
  keyword: '', warehouse_id: null, status: '', sort: '', order: '',
})

const warehouseOptions = ref<WarehouseOption[]>([])
const categoryTree = ref<CategoryNode[]>([])
const categoryTreeRef = ref<InstanceType<typeof CategoryTree> | null>(null)

const drawerVisible = ref(false)
const currentItem = ref<InventoryItem | null>(null)

const formDrawerVisible = ref(false)
const formEditingItem = ref<InventoryItem | null>(null)

const showBatchImport = ref(false)

const selectedIds = ref<number[]>([])
const batchEditVisible = ref(false)
const batchEditSubmitting = ref(false)
const categoryOptionsForEdit = computed(() => categoryTree.value)

async function loadWarnings() {
  try {
    const res = await inventory.warnings()
    // V1.2.10 用 unwrapStats 解包统计对象
    const d = unwrapStats(res)
    warnings.low_stock = Number(d.low_stock || 0)
    warnings.expiring = Number(d.expiring || 0)
    warnings.total = warnings.low_stock + warnings.expiring
  } catch (e) { /* silent */ }
}

async function loadWarehouses() {
  try {
    // V0.6.3: res = {code, data: <warehouses>} 可能是 array
    const res = await inventory.warehouses()
    warehouseOptions.value = unwrapList(res) as WarehouseOption[]
  } catch (e) { /* silent */ }
}

async function loadCategoryTree() {
  try {
    // V0.6.3: res = {code, data: <tree>} 可能是 array
    const res = await inventory.treeWithCounts()
    categoryTree.value = unwrapList(res) as CategoryNode[]
  } catch (e) { /* silent */ }
}

async function loadItems() {
  itemLoading.value = true
  try {
    const params: ItemListParams = {
      page: itemPage.value,
      per_page: itemPerPage.value,
      ...lastSearch.value,
    }
    if (currentCategoryId.value) params.category_id = currentCategoryId.value
    if (lastSearch.value.keyword) {
      params.keyword = lastSearch.value.keyword
      params.search = lastSearch.value.keyword
    }
    const res = await inventory.itemsByCategory(params)
    // V1.2.10 用 unwrapPaginate 统一解包, 兼容老/新两种结构
    const pag = unwrapPaginate(res)
    itemList.value = pag.list as InventoryItem[]
    itemTotal.value = pag.total
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '加载物品失败')
    itemList.value = []
    itemTotal.value = 0
  } finally {
    itemLoading.value = false
  }
}

function onSearch() {
  itemPage.value = 1
  lastSearch.value.keyword = searchKey.value
  loadItems()
}

function onItemSearch(payload: { keyword: string; warehouse_id: number | null; status: string; sort: string; order: string }) {
  itemPage.value = 1
  lastSearch.value = payload
  if (payload.keyword !== searchKey.value) searchKey.value = payload.keyword
  loadItems()
}

function onItemPageChange(p: number) {
  itemPage.value = p
  loadItems()
}

function onItemSizeChange(s: number) {
  itemPage.value = 1
  itemPerPage.value = s
  loadItems()
}

function onTreeRefresh() {
  loadWarnings()
  loadStats()
  loadItems()
}

function handleWarningClick() {
  ElMessage.info('请在列表中筛选库存状态查看详情')
}

function showItemDetail(row: InventoryItem) {
  currentItem.value = row
  drawerVisible.value = true
}

function handleCreateItem() {
  formEditingItem.value = null
  formDrawerVisible.value = true
}

function handleEditItem(row: InventoryItem) {
  currentItem.value = row
  drawerVisible.value = false
  formEditingItem.value = row
  formDrawerVisible.value = true
}

function handleFormSuccess() {
  loadItems()
  loadWarnings()
  loadStats()
  loadCategoryTree()
  categoryTreeRef.value?.refresh()
}

async function handleDeleteItem(row: InventoryItem) {
  try {
    await ElMessageBox.confirm(`确认删除物品「${row.name}」?`, '删除确认', { type: 'error' })
    await inventory.deleteItem(row.id)
    ElMessage.success('已删除')
    loadItems()
    loadWarnings()
  } catch (e) {
    const err = e as ApiError
    if (e === 'cancel' || err?.message === 'cancel') return
    ElMessage.error(err?.response?.data?.message || err?.message || '删除失败')
  }
}

function handleStockIn(row: InventoryItem) {
  router.push({ path: '/inventory/inbound-order', query: { item_id: String(row.id) } })
}

function handleStockOut(row: InventoryItem) {
  router.push({ path: '/inventory/outbound-order', query: { item_id: String(row.id) } })
}

function handleImportSuccess() {
  showBatchImport.value = false
  ElMessage.success('导入完成, 正在刷新列表')
  loadItems()
  loadWarnings()
  loadStats()
  categoryTreeRef.value?.refresh()
}

onMounted(() => {
  loadWarnings()
  loadWarehouses()
  loadStats()
  loadItems()
  loadCategoryTree()
})

function onSelectionChange(ids: number[]) {
  selectedIds.value = ids
}
function clearSelection() {
  selectedIds.value = []
}
function handleBatchEdit() {
  batchEditVisible.value = true
}

async function submitBatchEdit(fields: Record<string, unknown>) {
  if (Object.keys(fields).length === 0) {
    ElMessage.warning('请至少填写一个要修改的字段')
    return
  }
  batchEditSubmitting.value = true
  try {
    // V0.6.3: res = {code, data: {updated_count, ...}}
    const res = await inventory.batchUpdate(selectedIds.value, fields) as BatchUpdateResponse
    const cnt = res?.data?.updated_count ?? 0
    ElMessage.success(`已更新 ${cnt} 项`)
    batchEditVisible.value = false
    clearSelection()
    await loadItems()
    await loadCategoryTree()
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '批量更新失败')
  } finally {
    batchEditSubmitting.value = false
  }
}

async function handleBatchSetActive() {
  await applyBatchField('status', 'active', '已启用')
}
async function handleBatchSetInactive() {
  await applyBatchField('status', 'inactive', '已禁用')
}

async function applyBatchField(field: string, value: unknown, successText: string) {
  if (!selectedIds.value.length) return
  try {
    // V0.6.3: res = {code, data: {updated_count, ...}}
    const res = await inventory.batchUpdate(selectedIds.value, { [field]: value }) as BatchUpdateResponse
    const cnt = res?.data?.updated_count ?? 0
    ElMessage.success(`${successText} ${cnt} 项`)
    clearSelection()
    await loadItems()
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '操作失败')
  }
}

async function handleBatchDelete() {
  if (!selectedIds.value.length) return
  try {
    await ElMessageBox.confirm(
      `确定删除选中的 ${selectedIds.value.length} 项物料? 仅可删除"无库存+无流水"的物料, 其他会自动跳过。`,
      '批量删除确认',
      { type: 'error' },
    )
  } catch { return }
  try {
    const res = await inventory.batchDelete(selectedIds.value) as BatchDeleteResponse
    // V0.6.3: res = {code, data: {deleted_count, skipped_count, skipped:[]}}
    const d = res?.data || {}
    ElMessage.success(`已删除 ${d.deleted_count ?? 0} 项, 跳过 ${d.skipped_count ?? 0} 项`)
    if (d.skipped?.length) console.warn('[batchDelete skipped]', d.skipped)
    clearSelection()
    await loadItems()
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '批量删除失败')
  }
}

async function handleBatchExport() {
  try {
    const params: ExportParams = selectedIds.value.length > 0 ? { ids: selectedIds.value } : {
      keyword: lastSearch.value.keyword,
      warehouse_id: lastSearch.value.warehouse_id ?? undefined,
    }
    // 后端 batchExport 直接返 Blob, 透传给浏览器 (后端会负责 .xls 格式)
    const blob = await inventory.batchExport(params) as Blob
    const today = new Date().toISOString().slice(0, 10)
    const filename = selectedIds.value.length > 0
      ? `库存导出_选中${selectedIds.value.length}项_${today}.xls`
      : `库存导出_全部_${today}.xls`
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
    ElMessage.success(selectedIds.value.length > 0
      ? `已导出 ${selectedIds.value.length} 项选中物料`
      : '已导出当前搜索结果')
  } catch (e) {
    const err = e as ApiError
    ElMessage.error(err?.response?.data?.message || err?.message || '导出失败')
  }
}
</script>

<style lang="scss" scoped>
.inventory-container {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 20px;
  background: #f5f7fa;
  gap: 16px;
  overflow: hidden;
}
.inventory-body {
  display: flex;
  flex: 1;
  min-height: 0;
  gap: 16px;
  overflow: hidden;
}
.inventory-body__tree {
  width: 240px;
  flex-shrink: 0;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  overflow: hidden;
}
.inventory-body__table {
  flex: 1;
  min-width: 0;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.stats-row {
  display: flex; gap: 12px; flex-wrap: wrap;
}
.stats-card {
  flex: 1; min-width: 140px; border-radius: 8px;
  :deep(.el-card__body) { padding: 14px 16px; }
}
.stats-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stats-number { font-size: 22px; font-weight: 700; color: #303133; font-variant-numeric: tabular-nums; }
.stats-value .stats-number { color: #1D9E75; }
.stats-low .stats-number.is-warning { color: #E6A23C; }
.stats-out .stats-number.is-danger { color: #A32D2D; }
.filter-bar {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 14px; background: #fff;
  border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.filter-label { font-size: 13px; color: #606266; white-space: nowrap; }

</style>
