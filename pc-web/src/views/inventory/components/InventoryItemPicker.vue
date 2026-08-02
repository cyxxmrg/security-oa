<template>
  <el-dialog
    :model-value="showDialog"
    @update:model-value="onDialogVisibleChange"
    :title="multiple ? '批量选择物料' : '选择物料'"
    width="980px"
    :close-on-click-modal="false"
    top="5vh"
    append-to-body
    :destroy-on-close="false"
    @open="onOpen"
  >
    <div class="picker-toolbar">
      <el-select
        v-model="categoryFilter"
        clearable
        placeholder="选择分类"
        style="width:200px"
        filterable
        @change="fetchItems"
      >
        <el-option
          v-for="c in categories"
          :key="c.id"
          :label="c.name"
          :value="c.id"
        />
      </el-select>
      <el-input
        v-model="searchKey"
        placeholder="搜索物料名称 / 编码 / 规格"
        clearable
        :prefix-icon="SearchI"
        style="flex:1"
        @input="onSearchInput"
        @clear="fetchItems"
      />
      <el-button v-if="multiple" :icon="Refresh" size="default" @click="fetchItems">刷新</el-button>
    </div>

    <div v-if="multiple && selectedList.length" class="picker-selected-bar">
      <el-icon class="bar-icon"><ShoppingCart /></el-icon>
      <span class="bar-text">已选 <strong>{{ selectedList.length }}</strong> 种物料</span>
      <el-tag
        v-for="it in selectedList.slice(0, 8)"
        :key="it.id"
        size="small"
        closable
        @close="removeOne(it)"
        class="bar-tag"
      >
        {{ it.name }}<span v-if="it.code" class="bar-tag-code"> ({{ it.code }})</span>
      </el-tag>
      <el-tag v-if="selectedList.length > 8" size="small" type="info">+{{ selectedList.length - 8 }}</el-tag>
      <el-button link size="small" type="danger" @click="clearAll">清空</el-button>
    </div>

    <el-table
      ref="tableRef"
      v-loading="loading"
      :data="pagedItems"
      :row-key="(row: InventoryItem) => String(row.id)"
      stripe
      border
      style="width:100%"
      max-height="460"
      :highlight-current-row="!multiple"
      :default-expand-all="false"
      @row-click="onRowClick"
      @selection-change="onSelectionChange"
    >
      <el-table-column
        v-if="multiple"
        type="selection"
        width="44"
        :reserve-selection="true"
        :selectable="(row: InventoryItem) => !row.disabled"
      />
      <el-table-column v-else type="index" label="#" width="48" />
      <el-table-column prop="code" label="编码" width="140">
        <template #default="{ row }"><span class="item-code">{{ row.code }}</span></template>
      </el-table-column>
      <el-table-column prop="name" label="名称" min-width="160" show-overflow-tooltip />
      <el-table-column prop="spec" label="规格" width="120" show-overflow-tooltip>
        <template #default="{ row }"><span v-if="row.spec">{{ row.spec }}</span><span v-else class="text-muted">-</span></template>
      </el-table-column>
      <el-table-column label="分类" width="120" show-overflow-tooltip>
        <template #default="{ row }"><span v-if="row.category">{{ row.category.name }}</span><span v-else class="text-muted">-</span></template>
      </el-table-column>
      <el-table-column label="当前库存" width="120" align="center">
        <template #default="{ row }">
          <el-tag
            :type="(row.current_stock??0)<=0?'danger':(row.current_stock??0)<10?'warning':'success'"
            size="small"
            effect="plain"
          >{{ row.current_stock??0 }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="unit" label="单位" width="70" align="center" />
    </el-table>

    <div class="picker-pagination">
      <el-pagination
        background
        layout="total, prev, pager, next"
        :total="total"
        :current-page="page"
        :page-size="pageSize"
        small
        @current-change="(p: number) => { page = p; fetchItems() }"
      />
    </div>

    <template #footer>
      <el-button @click="closeDialog">取消</el-button>
      <el-button
        type="primary"
        :disabled="multiple ? selectedList.length === 0 : !selectedItem"
        @click="handleConfirm"
      >
        <span v-if="multiple">已选 {{ selectedList.length }} 项，确定加入</span>
        <span v-else>确定选择</span>
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch, nextTick } from "vue"
import { Search as SearchI, ShoppingCart, Refresh } from "@element-plus/icons-vue"
import { get } from "@/utils/request"
import { unwrapList, unwrapPaginate } from '@/utils/response'

const props = withDefaults(defineProps<{
  show: boolean
  items?: InventoryItem[]
  multiple?: boolean
  /** 已选中的 id 列表（外部传入，回显时回填用） */
  selectedIds?: number[]
}>(), {
  multiple: false,
  selectedIds: () => [],
})

import type { InventoryItem, ItemCategory as CategoryNode } from '../types'

const emit = defineEmits<{
  (e: "close"): void
  (e: "select", items: InventoryItem[]): void
}>()

const searchKey = ref("")
const categoryFilter = ref<number | null>(null)
const loading = ref(false)
const selectedItem = ref<InventoryItem | null>(null)
const selectedList = ref<InventoryItem[]>([])

// 分类下拉 — 第一次打开时一次性拉
const categories = ref<CategoryNode[]>([])

// 物料分页数据（服务端）
const pagedItems = ref<InventoryItem[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = 30

let searchTimer: number | null = null

// el-table ref 用于 clearSelection()
const tableRef = ref<{ clearSelection: () => void } | null>(null)

// 本地显示状态 + 双向同步 props.show
// 不使用 v-model 是为了避免 el-dialog 内部状态与父组件 v-model 冲突
const showDialog = ref(props.show)
watch(() => props.show, (v) => { showDialog.value = v })

function onDialogVisibleChange(v: boolean) {
  showDialog.value = v
  if (!v) emit('close')
}

function closeDialog() {
  showDialog.value = false
}

async function loadCategories() {
  if (categories.value.length) return
  try {
    const res: unknown = await get("/inventory-categories/tree")
    const flat: CategoryNode[] = []
    const walk = (ns: CategoryNode[]) => { for (const n of ns) { flat.push({ id: n.id, name: n.name }); if (n.children?.length) walk(n.children) } }
    walk(unwrapList(res))
    categories.value = flat
  } catch { /* ignore */ }
}

async function fetchItems() {
  loading.value = true
  try {
    const res: unknown = await get("/inventory", {
      keyword: searchKey.value || undefined,
      category_id: categoryFilter.value || undefined,
      page: page.value,
      per_page: pageSize,
    })
    const { list, total: t } = unwrapPaginate(res)
    pagedItems.value = list
    total.value = t || list.length
  } catch (e) {
    pagedItems.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

async function onOpen() {
  // 每次打开重置 + 拉首屏
  searchKey.value = ""
  categoryFilter.value = null
  page.value = 1
  selectedItem.value = null
  selectedList.value = []
  // V1.2.16 fix: el-table 的 :reserve-selection 会保留跨页+跨次勾选, 必须 DOM 层清掉
  await nextTick()
  tableRef.value?.clearSelection()
  loadCategories()
  fetchItems()
}

function onSearchInput() {
  if (searchTimer) window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    page.value = 1
    fetchItems()
  }, 250)
}

function onRowClick(row: InventoryItem, _column?: unknown, event?: MouseEvent) {
  if (props.multiple) {
    // V1.2.16 fix: 多选模式不再手动 toggle selectedList (会和 el-table 的 selection-change 重复)
    // 改用 el-table 原生勾选: 用户点 checkbox 即触发 selection-change
    // 点击行只用来 highlight, 真正勾选由 checkbox 决定
    return
  } else {
    selectedItem.value = row
  }
}

function onSelectionChange(rows: InventoryItem[]) {
  if (!props.multiple) return
  // el-table 内部 selection 变化 (含勾选/取消/跨页 reserve)
  // 合并: 保留所有非当前页的,加上当前页选中的
  const currentPageIds = new Set(pagedItems.value.map((r: InventoryItem) => r.id))
  const otherPages = selectedList.value.filter((s: InventoryItem) => !currentPageIds.has(s.id))
  selectedList.value = [...otherPages, ...rows]
}

function removeOne(it: InventoryItem) {
  selectedList.value = selectedList.value.filter((s: InventoryItem) => s.id !== it.id)
}

function clearAll() {
  selectedList.value = []
}

function handleConfirm() {
  if (props.multiple) {
    if (selectedList.value.length === 0) return
    emit("select", [...selectedList.value])
    closeDialog()
  } else {
    if (!selectedItem.value) return
    emit("select", [selectedItem.value])
    closeDialog()
    selectedItem.value = null
  }
}
</script>

<style scoped>
.picker-toolbar { display: flex; gap: 8px; margin-bottom: 12px }
.picker-pagination { display: flex; justify-content: flex-end; margin-top: 12px }
.picker-selected-bar {
  display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
  padding: 8px 12px; margin-bottom: 8px;
  background: linear-gradient(90deg, #ecf5ff 0%, #f0f9ff 100%);
  border: 1px solid #d9ecff; border-radius: 6px;
}
.bar-icon { color: #409eff; font-size: 16px }
.bar-text { font-size: 13px; color: #303133; margin-right: 8px }
.bar-text strong { color: #409eff; font-size: 15px; margin: 0 2px }
.bar-tag-code { color: #909399; font-size: 11px; margin-left: 2px }
.item-code { font-family: "DIN Pro", "Consolas", monospace; font-weight: 500; color: #0C447C; font-size: 12px }
.text-muted { color: #c0c4cc }
:deep(.el-table__row) { cursor: pointer }
:deep(.el-table__row.current-row) { background-color: #e6f1fb !important }
</style>
