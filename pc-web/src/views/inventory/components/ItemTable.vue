<template>
  <div class="item-table" v-loading="loading">
    <!-- 顶部工具栏 -->
    <div class="table-toolbar">
      <el-input
        v-model="searchKey"
        placeholder="搜索名称/编号/规格"
        clearable
        :prefix-icon="Search"
        style="width: 240px"
        @keyup.enter="onSearch"
        @clear="onSearch"
      />
      <el-select
        v-model="filterWarehouse"
        placeholder="仓库"
        clearable
        style="width: 160px; margin-left: 8px"
        @change="onSearch"
      >
        <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
      </el-select>
      <el-select
        v-model="filterStatus"
        placeholder="库存状态"
        clearable
        style="width: 130px; margin-left: 8px"
        @change="onSearch"
      >
        <el-option label="低库存" value="low" />
        <el-option label="预警" value="warn" />
        <el-option label="正常" value="normal" />
      </el-select>
      <span class="table-toolbar__right">
        <slot name="actions" />
      </span>
    </div>

    <el-table
      :data="list"
      border
      stripe
      style="width: 100%"
      :max-height="maxHeight"
      :default-sort="defaultSort"
      :row-key="rowKey"
      @sort-change="onSortChange"
      @row-click="onRowClick"
      @selection-change="onSelectionChange"
      empty-text="暂无物品"
    >
      <el-table-column type="selection" width="44" :reserve-selection="false" />
      <el-table-column prop="code" label="编码" width="140" sortable="custom" />
      <el-table-column label="名称" min-width="160" sortable="custom" prop="name">
        <template #default="{ row }">
          <div class="item-name">
            <el-icon :size="16" color="#0C447C"><Box /></el-icon>
            <span class="item-name__text">{{ row.name }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="分类" width="120">
        <template #default="{ row }">
          <el-tag v-if="row.category" size="small" type="info">{{ row.category }}</el-tag>
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="规格" min-width="140" show-overflow-tooltip prop="specification" />
      <el-table-column label="库存" width="110" align="center" sortable="custom" prop="current_stock">
        <template #default="{ row }">
          <span :class="stockClass(row)">
            {{ row.current_stock }} {{ row.unit }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="最小库存" width="100" align="center" prop="safety_stock">
        <template #default="{ row }">{{ row.safety_stock }} {{ row.unit }}</template>
      </el-table-column>
      <el-table-column label="仓库" width="120" show-overflow-tooltip>
        <template #default="{ row }">{{ row.warehouse?.name || '-' }}</template>
      </el-table-column>
      <el-table-column label="最近入库" width="110" align="center" prop="last_inbound_at">
        <template #default="{ row }">{{ formatDate(row.last_inbound_at) }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="statusType(row)" size="small" effect="dark">
            {{ statusLabel(row) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="220" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" @click.stop="emit('detail', row)">详情</el-button>
          <el-button link type="success" size="small" @click.stop="emit('stock-in', row)">入库</el-button>
          <el-button link type="warning" size="small" @click.stop="emit('stock-out', row)">出库</el-button>
          <el-button link type="primary" size="small" @click.stop="emit('edit', row)">编辑</el-button>
          <el-button link type="danger"  size="small" @click.stop="emit('delete', row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        background
        layout="total, prev, pager, next, sizes"
        :total="total"
        :current-page="page"
        :page-size="perPage"
        :page-sizes="[10, 15, 30, 50]"
        @current-change="(p: number) => emit('page-change', p)"
        @size-change="(s: number) => emit('size-change', s)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Search, Box } from '@element-plus/icons-vue'
import type { InventoryItem, WarehouseOption } from '../types'

const props = defineProps<{
  list: InventoryItem[]
  total: number
  page: number
  perPage: number
  loading: boolean
  warehouseOptions: WarehouseOption[]
  currentCategory: number | null
  selectedIds: number[]
}>()

const emit = defineEmits<{
  (e: 'search', payload: { keyword: string; warehouse_id: number | null; status: string; sort: string; order: string }): void
  (e: 'page-change', p: number): void
  (e: 'size-change', s: number): void
  (e: 'detail', row: InventoryItem): void
  (e: 'edit', row: InventoryItem): void
  (e: 'delete', row: InventoryItem): void
  (e: 'stock-in', row: InventoryItem): void
  (e: 'stock-out', row: InventoryItem): void
  (e: 'row-click', row: InventoryItem): void
  (e: 'selection-change', ids: number[]): void
}>()

function rowKey(row: InventoryItem): number {
  return row.id
}

function onSelectionChange(sel: InventoryItem[]) {
  emit('selection-change', sel.map(r => r.id))
}

const searchKey = ref('')
const filterWarehouse = ref<number | ''>('')
const filterStatus = ref<string>('')
const sortField = ref('')
const sortOrder = ref('')
const maxHeight = ref(window.innerHeight - 320)

const defaultSort = { prop: '', order: '' }

watch(() => props.currentCategory, () => {
  // 切换分类时自动重发搜索
  doSearch()
})

function onSearch() { doSearch() }

function doSearch() {
  emit('search', {
    keyword: searchKey.value,
    warehouse_id: filterWarehouse.value === '' ? null : (filterWarehouse.value as number),
    status: filterStatus.value,
    sort: sortField.value,
    order: sortOrder.value,
  })
}

function onSortChange({ prop, order }: { prop?: string; order?: string }) {
  sortField.value = prop || ''
  sortOrder.value = order === 'ascending' ? 'asc' : (order === 'descending' ? 'desc' : '')
  doSearch()
}

function onRowClick(row: InventoryItem) {
  emit('row-click', row)
  emit('detail', row)
}

function stockClass(row: InventoryItem) {
  if (row.is_low_stock) return 'stock-text stock-text--danger'
  if ((row.current_stock ?? 0) <= (row.safety_stock ?? 0) * 1.5) return 'stock-text stock-text--warn'
  return 'stock-text'
}

function statusType(row: InventoryItem) {
  if (row.is_low_stock) return 'danger'
  if ((row.current_stock ?? 0) <= (row.safety_stock ?? 0) * 1.5) return 'warning'
  return 'success'
}
function statusLabel(row: InventoryItem) {
  if (row.is_low_stock) return '不足'
  if ((row.current_stock ?? 0) <= (row.safety_stock ?? 0) * 1.5) return '预警'
  return '正常'
}

function formatDate(s: string | null | undefined) {
  if (!s) return '-'
  const d = new Date(s)
  if (Number.isNaN(d.getTime())) return '-'
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

defineExpose({ doSearch, searchKey, filterWarehouse, filterStatus })
</script>

<style lang="scss" scoped>
.item-table {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-direction: column;
  min-height: 0;
}
.table-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
  &__right {
    margin-left: auto;
    display: flex;
    gap: 8px;
  }
}
.item-name {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
  &__text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
.stock-text { font-weight: 600; }
.stock-text--danger { color: #A32D2D; }
.stock-text--warn { color: #BA7517; }
.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
}
</style>
