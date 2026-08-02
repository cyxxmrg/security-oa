<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="批量选择物料"
    width="980px"
    :close-on-click-modal="false"
    top="5vh"
    @open="onOpen"
  >
    <div class="picker-toolbar">
      <el-select
        v-model="categoryFilter"
        clearable placeholder="选择分类" filterable style="width:200px"
        @change="fetchItems"
      >
        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
      </el-select>
      <el-input
        v-model="searchKey"
        placeholder="搜索物料名称 / 编码 / 规格" clearable :prefix-icon="Search" style="flex:1"
        @input="onSearchInput"
        @clear="fetchItems"
      />
    </div>

    <div v-if="selectedList.length" class="picker-selected-bar">
      <span class="bar-text">已选 <strong>{{ selectedList.length }}</strong> 种</span>
      <el-tag
        v-for="it in selectedList.slice(0, 6)"
        :key="it.id"
        size="small" closable
        @close="removeOne(it)"
      >{{ it.name }}<span v-if="it.code" class="bar-code">({{ it.code }})</span></el-tag>
      <el-tag v-if="selectedList.length > 6" size="small" type="info">+{{ selectedList.length - 6 }}</el-tag>
      <el-button link size="small" type="danger" @click="selectedList = []">清空</el-button>
    </div>

    <el-table
      v-loading="loading"
      :data="pagedItems"
      :row-key="(row: InventoryItem) => row.id"
      stripe border style="width:100%" max-height="460"
      @row-click="onRowClick"
      @selection-change="onSelectionChange"
    >
      <el-table-column type="selection" width="44" :reserve-selection="true" />
      <el-table-column prop="code" label="编码" width="140">
        <template #default="{row}"><span class="item-code">{{ row.code }}</span></template>
      </el-table-column>
      <el-table-column prop="name" label="名称" min-width="160" show-overflow-tooltip />
      <el-table-column label="规格" width="120" show-overflow-tooltip>
        <template #default="{row}"><span v-if="row.spec">{{ row.spec }}</span><span v-else class="text-muted">-</span></template>
      </el-table-column>
      <el-table-column label="分类" width="120" show-overflow-tooltip>
        <template #default="{row}"><span v-if="row.category">{{ row.category.name }}</span><span v-else class="text-muted">-</span></template>
      </el-table-column>
      <el-table-column label="当前库存" width="120" align="center">
        <template #default="{row}">
          <el-tag :type="(row.current_stock??0)<=0?'danger':(row.current_stock??0)<10?'warning':'success'" size="small" effect="plain">{{ row.current_stock??0 }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="unit" label="单位" width="70" align="center" />
    </el-table>
    <div class="picker-pagination">
      <el-pagination
        background layout="total, prev, pager, next"
        :total="total" :current-page="page" :page-size="pageSize" small
        @current-change="(p: number) => { page = p; fetchItems() }"
      />
    </div>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button
        type="primary"
        :disabled="selectedList.length === 0"
        @click="handleConfirm"
      >已选 {{ selectedList.length }} 项，确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Search } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'
import type { InventoryItem, ItemCategory } from '../../types'

const props = withDefaults(defineProps<{
  visible: boolean
  /** 已选中的 id 列表（外部传入, 用于打开时回显勾选状态） */
  selectedIds?: number[]
}>(), { selectedIds: () => [] })
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'select', items: InventoryItem[]): void
}>()

interface CategoryOption { id: number; name: string }

const searchKey = ref('')
const categoryFilter = ref<number | null>(null)
const loading = ref(false)
const selectedList = ref<InventoryItem[]>([])
const categories = ref<CategoryOption[]>([])

const pagedItems = ref<InventoryItem[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = 30

let searchTimer: number | null = null

/** 已选 id 集合 (用于按需把 el-table 内部 toggleRowSelection 状态同步) */
const selectedIdSet = computed(() => new Set(props.selectedIds))
watch(() => props.selectedIds, () => {
  // 外部 selectedIds 改变, 同步 selectedList
  selectedList.value = selectedList.value.filter(s => selectedIdSet.value.has(s.id))
})

async function loadCategories() {
  if (categories.value.length) return
  try {
    const res = await get('/inventory-categories/tree')
    const flat: CategoryOption[] = []
    const walk = (ns: ItemCategory[]): void => {
      for (const n of ns) {
        flat.push({ id: n.id, name: n.name ?? '' })
        if (n.children?.length) walk(n.children)
      }
    }
    walk(unwrapList(res))
    categories.value = flat
  } catch { /* ignore */ }
}

async function fetchItems() {
  loading.value = true
  try {
    const res = await get('/inventory', {
      keyword: searchKey.value || undefined,
      category_id: categoryFilter.value || undefined,
      page: page.value, per_page: pageSize,
    })
    const { list, total: t } = unwrapPaginate(res)
    pagedItems.value = list
    total.value = t || list.length
  } catch { pagedItems.value = []; total.value = 0 }
  finally { loading.value = false }
}

function onOpen() {
  searchKey.value = ''; categoryFilter.value = null; page.value = 1
  selectedList.value = []
  loadCategories(); fetchItems()
}

function onSearchInput() {
  if (searchTimer) window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => { page.value = 1; fetchItems() }, 250)
}

function onRowClick(row: InventoryItem) {
  const idx = selectedList.value.findIndex(s => s.id === row.id)
  if (idx >= 0) selectedList.value.splice(idx, 1)
  else selectedList.value.push(row)
}

function onSelectionChange(rows: InventoryItem[]) {
  // el-table 内部 selection 变化 (含勾选/取消/跨页 reserve)
  // 合并: 保留所有非当前页的,加上当前页选中的
  const currentPageIds = new Set(pagedItems.value.map(r => r.id))
  const otherPages = selectedList.value.filter(s => !currentPageIds.has(s.id))
  selectedList.value = [...otherPages, ...rows]
}

function removeOne(it: InventoryItem) {
  selectedList.value = selectedList.value.filter(s => s.id !== it.id)
}

function handleConfirm() {
  if (selectedList.value.length === 0) return
  emit('select', [...selectedList.value])
  emit('update:visible', false)
  selectedList.value = []
}
</script>

<style lang="scss" scoped>
.item-code { font-family: monospace; color: #0C447C; }
.text-muted { color: #c0c4cc; }
.picker-toolbar { display: flex; gap: 8px; margin-bottom: 12px }
.picker-pagination { display: flex; justify-content: flex-end; margin-top: 12px }
.picker-selected-bar {
  display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
  padding: 8px 12px; margin-bottom: 8px;
  background: linear-gradient(90deg, #ecf5ff 0%, #f0f9ff 100%);
  border: 1px solid #d9ecff; border-radius: 6px;
}
.bar-text { font-size: 13px; color: #303133; margin-right: 8px }
.bar-text strong { color: #409eff; font-size: 15px; margin: 0 2px }
.bar-code { color: #909399; font-size: 11px; margin-left: 2px }
:deep(.el-table__row) { cursor: pointer }
</style>
