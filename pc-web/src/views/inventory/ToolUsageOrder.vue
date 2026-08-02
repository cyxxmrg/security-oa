<template>
  <div class="page-container">
    <div class="page-header"><h2>工具使用单</h2></div>

    <!-- 上方三个动作按钮 -->
    <div class="filter-bar">
      <el-button type="warning" :icon="TakeawayBox" @click="openMovement('checkout')">新增工具领用单</el-button>
      <el-button type="success" :icon="RefreshLeft" @click="openMovement('return')">新增工具归还单</el-button>
      <el-button type="primary" plain :icon="Switch" @click="openConvert">库存转工具</el-button>
      <el-divider direction="vertical" />
      <el-select v-model="filterType" placeholder="类型" clearable style="width: 110px" @change="loadList(1)">
        <el-option label="领用" value="tool_checkout" />
        <el-option label="归还" value="tool_return" />
      </el-select>
      <el-input v-model="searchKey" placeholder="搜索单号 / 工具名称 / 操作人" clearable style="width: 260px" :prefix-icon="Search" @keyup.enter="loadList(1)" @clear="loadList(1)" />
    </div>

    <!-- 使用/归还明细直接平铺列表 -->
    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column label="单号" width="170">
          <template #default="{ row }"><span class="record-no">{{ row.record_no }}</span></template>
        </el-table-column>
        <el-table-column label="类型" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.type === 'tool_checkout' ? 'danger' : 'success'" size="small">{{ row.type === 'tool_checkout' ? '领用' : '归还' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="工具名称" min-width="150" show-overflow-tooltip>
          <template #default="{ row }">{{ row.inventoryItem?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="固定资产编号" width="170">
          <template #default="{ row }">
            <el-tag v-if="row.tool?.fixed_asset_no" size="small" effect="plain" type="primary" style="font-family: 'DIN Pro', monospace">{{ row.tool.fixed_asset_no }}</el-tag>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="规格" width="110" show-overflow-tooltip>
          <template #default="{ row }">{{ row.inventoryItem?.specification || row.inventoryItem?.spec || '-' }}</template>
        </el-table-column>
        <el-table-column label="单位" width="60" align="center">
          <template #default="{ row }">{{ row.inventoryItem?.unit || '-' }}</template>
        </el-table-column>
        <el-table-column label="数量" width="90" align="right">
          <template #default="{ row }">
            <span :style="{ fontWeight: 600, color: row.type === 'tool_checkout' ? '#A32D2D' : '#1D9E75' }">{{ row.type === 'tool_checkout' ? '-' : '+' }}{{ row.quantity }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作人" width="90">
          <template #default="{ row }">{{ row.operator?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="时间" width="150">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="110" show-overflow-tooltip />
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

    <!-- 新增领用/归还单 -->
    <el-dialog v-model="showMovement" :title="movementType === 'checkout' ? '新增工具领用单' : '新增工具归还单'" width="1100px" :close-on-click-modal="false" top="8vh">
      <el-alert
        :title="movementType === 'checkout' ? '选择在库工具并填写领用数量，确认后自动出库扣减库存' : '选择已领用工具并填写归还数量，确认后自动入库增加库存'"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 12px"
      />
      <div class="movement-toolbar">
        <el-button type="primary" plain size="small" :icon="Plus" @click="toolPickerVisible = true">选择工具</el-button>
        <span class="muted">已选 {{ movementItems.filter(r => r.tool).length }} 项</span>
        <el-input v-model="movementRemark" placeholder="操作备注(可选)" maxlength="200" clearable style="width: 300px; margin-left: auto" />
      </div>
      <el-table :data="movementItems" stripe border style="width: 100%" max-height="340">
        <el-table-column type="index" label="#" width="42" />
        <el-table-column label="固定资产编号" width="170">
          <template #default="{ row }"><span v-if="row.tool" class="asset-no">{{ row.tool.fixed_asset_no }}</span><span v-else class="muted">-</span></template>
        </el-table-column>
        <el-table-column label="工具名称" min-width="160">
          <template #default="{ row }">{{ row.tool?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="规格" width="110" show-overflow-tooltip>
          <template #default="{ row }">{{ row.tool?.specification || '-' }}</template>
        </el-table-column>
        <el-table-column label="单位" width="60" align="center">
          <template #default="{ row }">{{ row.tool?.unit || '-' }}</template>
        </el-table-column>
        <el-table-column label="在库数量" width="90" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.tool" :type="(row.tool.current_stock ?? 0) <= 0 ? 'danger' : 'success'" size="small" effect="plain">{{ row.tool.current_stock ?? 0 }}</el-tag>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column :label="movementType === 'checkout' ? '领用数量' : '归还数量'" width="130">
          <template #default="{ row }">
            <el-input-number
              v-model="row.quantity"
              :min="1"
              :max="Math.max(1, movementType === 'checkout' ? (row.tool?.available ?? 1) : (row.tool?.borrowed ?? 1))"
              :step="1"
              size="small"
              style="width: 110px"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="55" align="center">
          <template #default="{ $index }">
            <el-button type="danger" link size="small" :icon="Delete" @click="movementItems.splice($index, 1)" />
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="movementItems.length === 0" :image-size="50" description="暂无工具，点击「选择工具」添加" />
      <template #footer>
        <el-button @click="showMovement = false">取消</el-button>
        <el-button :type="movementType === 'checkout' ? 'warning' : 'success'" :loading="submitting" @click="submitMovement">
          确认{{ movementType === 'checkout' ? '领用' : '归还' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 工具选择器 (领用/归还共用) -->
    <el-dialog v-model="toolPickerVisible" :title="movementType === 'checkout' ? '选择在库工具' : '选择已领用工具'" width="900px" :close-on-click-modal="false" top="10vh" append-to-body>
      <div class="movement-toolbar">
        <el-input v-model="toolSearch" placeholder="搜索名称 / 资产编号 / 编码" clearable :prefix-icon="Search" size="small" style="width: 300px" @input="onToolSearch" @clear="loadTools" />
        <span class="muted">仅显示{{ movementType === 'checkout' ? '在库' : '已领用' }}工具</span>
      </div>
      <el-table v-loading="toolLoading" :data="toolOptions" stripe border size="small" style="width: 100%" max-height="380" @selection-change="onToolSelection">
        <el-table-column type="selection" width="44" />
        <el-table-column label="固定资产编号" width="170">
          <template #default="{ row }"><span class="asset-no">{{ row.fixed_asset_no }}</span></template>
        </el-table-column>
        <el-table-column prop="name" label="工具名称" min-width="150" show-overflow-tooltip />
        <el-table-column label="规格" width="110" show-overflow-tooltip>
          <template #default="{ row }">{{ row.specification || '-' }}</template>
        </el-table-column>
        <el-table-column prop="unit" label="单位" width="60" align="center" />
        <el-table-column label="台账件数" width="90" align="center">
          <template #default="{ row }">{{ row.quantity ?? 0 }}</template>
        </el-table-column>
        <el-table-column label="在库数量" width="90" align="center">
          <template #default="{ row }">{{ row.current_stock ?? 0 }}</template>
        </el-table-column>
        <el-table-column v-if="movementType === 'checkout'" label="可用件数" width="90" align="center">
          <template #default="{ row }">
            <span v-if="(row.available ?? 0) > 0" style="font-weight: 600; color: #1D9E75">{{ row.available }}</span>
            <span v-else class="muted">0</span>
          </template>
        </el-table-column>
        <el-table-column v-else label="已借出" width="90" align="center">
          <template #default="{ row }">
            <span v-if="(row.borrowed ?? 0) > 0" style="font-weight: 600; color: #A32D2D">{{ row.borrowed }}</span>
            <span v-else class="muted">0</span>
          </template>
        </el-table-column>
      </el-table>
      <template #footer>
        <el-button @click="toolPickerVisible = false">取消</el-button>
        <el-button type="primary" :disabled="selectedTools.length === 0" @click="confirmToolPick">加入明细 ({{ selectedTools.length }})</el-button>
      </template>
    </el-dialog>

    <!-- 库存转工具 -->
    <el-dialog v-model="showConvert" title="库存转工具" width="980px" :close-on-click-modal="false" top="10vh">
      <el-alert title="把库存商品的部分数量转换为工具台账，自动生成固定资产编号（GD-YYYYMMDD-NNNN）。转换数量不能超过当前库存，已转换过的商品会跳过。" type="info" :closable="false" show-icon style="margin-bottom: 12px" />
      <div class="movement-toolbar">
        <el-button type="primary" plain size="small" :icon="Plus" @click="convertPickerVisible = true">选择库存商品</el-button>
        <span class="muted">已选 {{ convertItems.length }} 项</span>
      </div>
      <el-table :data="convertItems" stripe border style="width: 100%" max-height="340">
        <el-table-column type="index" label="#" width="42" />
        <el-table-column label="编码" width="130">
          <template #default="{ row }"><span class="item-code">{{ row.item.code }}</span></template>
        </el-table-column>
        <el-table-column prop="item.name" label="商品名称" min-width="160" show-overflow-tooltip />
        <el-table-column label="规格" width="120" show-overflow-tooltip>
          <template #default="{ row }">{{ row.item.specification || row.item.spec || '-' }}</template>
        </el-table-column>
        <el-table-column prop="item.unit" label="单位" width="60" align="center" />
        <el-table-column label="当前库存" width="90" align="center">
          <template #default="{ row }">{{ row.item.current_stock ?? 0 }}</template>
        </el-table-column>
        <el-table-column label="转换数量" width="130">
          <template #default="{ row }">
            <el-input-number v-model="row.quantity" :min="1" :max="Math.max(1, row.item.current_stock ?? 1)" :step="1" size="small" style="width: 110px" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="55" align="center">
          <template #default="{ $index }">
            <el-button type="danger" link size="small" :icon="Delete" @click="convertItems.splice($index, 1)" />
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="convertItems.length === 0" :image-size="50" description="暂无商品，点击「选择库存商品」添加" />
      <template #footer>
        <el-button @click="showConvert = false">取消</el-button>
        <el-button type="primary" :loading="converting" :disabled="convertItems.length === 0" @click="submitConvert">确认转换</el-button>
      </template>
    </el-dialog>

    <InventoryItemPicker :show="convertPickerVisible" :multiple="true" :selected-ids="convertPickedIds" @select="(items: InventoryItem[]) => onConvertPick(items)" @close="convertPickerVisible = false" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Search, Plus, Delete, TakeawayBox, RefreshLeft, Switch } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'
import InventoryItemPicker from './components/InventoryItemPicker.vue'
import type { InventoryItem } from './types'

interface ToolOption extends Record<string, unknown> {
  id: number
  inventory_item_id: number
  fixed_asset_no: string
  name: string
  specification?: string | null
  unit?: string | null
  status: string
  quantity?: number
  current_stock?: number
  borrowed?: number
  available?: number
}

interface RecordRow extends Record<string, unknown> {
  record_no: string
  type: string
  quantity: number
  inventory_item_id: number
  inventoryItem?: { name?: string; code?: string; specification?: string; spec?: string; unit?: string } | null
  operator?: { name?: string } | null
  tool?: { fixed_asset_no?: string } | null
  remark?: string | null
  created_at?: string
}

interface MovementRow { uid: string; tool: ToolOption | null; quantity: number }

interface ConvertRow { uid: string; item: InventoryItem; quantity: number }

const searchKey = ref('')
const filterType = ref('')
const list = ref<RecordRow[]>([])
const loading = ref(false)
const pagination = reactive({ page: 1, per_page: 15, total: 0 })

const formatDate = (s?: string | null) => {
  if (!s) return '-'
  const t = s.replace('T', ' ').slice(0, 16)
  return t || s
}

async function loadList(page = 1) {
  pagination.page = page
  loading.value = true
  try {
    const res = await get('/inventory/tool-records', {
      page,
      per_page: pagination.per_page,
      keyword: searchKey.value || undefined,
      type: filterType.value || undefined,
    })
    const pag = unwrapPaginate(res)
    list.value = pag.list as RecordRow[]
    pagination.total = pag.total
  } catch (e) {
    console.error('[loadList]', e)
    list.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

// ===== 新增领用/归还单 =====
const showMovement = ref(false)
const movementType = ref<'checkout' | 'return'>('checkout')
const movementItems = ref<MovementRow[]>([])
const movementRemark = ref('')
const submitting = ref(false)

function openMovement(type: 'checkout' | 'return') {
  movementType.value = type
  movementRemark.value = ''
  movementItems.value = []
  showMovement.value = true
  toolSearch.value = ''
  selectedTools.value = []
}

async function submitMovement() {
  const validItems = movementItems.value.filter(r => r.tool)
  if (validItems.length === 0) { ElMessage.warning('请至少选择一种工具'); return }
  submitting.value = true
  try {
    const action = movementType.value === 'checkout' ? 'tool-checkout' : 'tool-return'
    const res = await post(`/inventory/${action}`, {
      items: validItems.map(r => ({ tool_id: r.tool!.id, quantity: r.quantity })),
      remark: movementRemark.value || undefined,
    })
    const recordNo = (res?.data as { record_no?: string } | undefined)?.record_no || ''
    ElMessage.success(movementType.value === 'checkout' ? `领用成功, 单号: ${recordNo}` : `归还成功, 单号: ${recordNo}`)
    showMovement.value = false
    await loadList(pagination.page)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

// ===== 工具选择器 =====
const toolPickerVisible = ref(false)
const toolSearch = ref('')
const toolOptions = ref<ToolOption[]>([])
const toolLoading = ref(false)
const selectedTools = ref<ToolOption[]>([])
let toolSearchTimer: number | null = null

function onToolSearch() {
  if (toolSearchTimer) window.clearTimeout(toolSearchTimer)
  toolSearchTimer = window.setTimeout(() => { selectedTools.value = []; loadTools() }, 300)
}

async function loadTools() {
  toolLoading.value = true
  try {
    const res = await get('/inventory/tools', {
      keyword: toolSearch.value || undefined,
      status: movementType.value === 'checkout' ? 'in_stock' : 'out',
    })
    toolOptions.value = unwrapList(res) as ToolOption[]
  } catch (e) {
    console.warn('[loadTools]', e)
    toolOptions.value = []
  } finally {
    toolLoading.value = false
  }
}

function onToolSelection(rows: ToolOption[]) {
  selectedTools.value = rows
}

function confirmToolPick() {
  if (!selectedTools.value.length) return
  // 去重后追加
  for (const t of selectedTools.value) {
    const existIdx = movementItems.value.findIndex(r => r.tool?.id === t.id)
    if (existIdx >= 0) movementItems.value.splice(existIdx, 1)
    movementItems.value.push({
      uid: String(Date.now()) + String(Math.random()) + String(t.id),
      tool: { ...t },
      quantity: Math.min(1, movementType.value === 'checkout' ? (t.available || t.current_stock || 1) : (t.borrowed || 1)),
    })
  }
  toolPickerVisible.value = false
}

// ===== 库存转工具 =====
const showConvert = ref(false)
const converting = ref(false)
const convertItems = ref<ConvertRow[]>([])
const convertPickerVisible = ref(false)
const convertPickedIds = computed(() => convertItems.value.map(i => i.item.id))

function openConvert() {
  convertItems.value = []
  showConvert.value = true
}

function onConvertPick(items: InventoryItem[]) {
  if (!items || !items.length) return
  for (const it of items) {
    if (!convertItems.value.some(c => c.item.id === it.id)) {
      convertItems.value.push({
        uid: String(Date.now()) + String(Math.random()) + String(it.id),
        item: { ...it },
        quantity: Math.min(1, it.current_stock || 1),
      })
    }
  }
  convertPickerVisible.value = false
}

async function submitConvert() {
  if (!convertItems.value.length) { ElMessage.warning('请至少选择一个库存商品'); return }
  converting.value = true
  try {
    const res = await post('/inventory/tools/convert', {
      items: convertItems.value.map(r => ({ inventory_item_id: r.item.id, quantity: r.quantity })),
    })
    const d = (res?.data ?? {}) as { created?: unknown[]; skipped?: { reason?: string }[] }
    const created = d.created?.length || 0
    const skipped = d.skipped?.length || 0
    ElMessage.success(`转换成功 ${created} 件工具${skipped ? `, 跳过 ${skipped} 件(已存在或数量超库存)` : ''}, 已自动生成固定资产编号`)
    showConvert.value = false
    await loadList(pagination.page)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '转换失败')
  } finally {
    converting.value = false
  }
}

onMounted(() => {
  loadList(1)
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
.asset-no { font-family: "DIN Pro", monospace; font-weight: 600; color: #0C447C; font-size: 12px; }
.item-code { font-family: "DIN Pro", monospace; font-weight: 500; color: #0C447C; font-size: 12px; }
.movement-toolbar {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  margin-bottom: 12px; padding: 8px 12px;
  background: #f6f8fa; border-radius: 6px;
}
:deep(.el-dialog__body) { padding-top: 12px; }
</style>
