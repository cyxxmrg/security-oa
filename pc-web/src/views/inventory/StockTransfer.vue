<template>
  <div class="page-container">
    <div class="page-header"><h2>仓库调拨单</h2></div>

    <div class="filter-bar">
      <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width:240px" @change="loadList(1)" />
      <el-input v-model="searchKey" placeholder="搜索单号" clearable style="width:220px" :prefix-icon="Search" @keyup.enter="loadList(1)" @clear="loadList(1)" />
      <el-button type="primary" plain :icon="Plus" @click="handleCreate">新增调拨单</el-button>
    </div>

    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width:100%" :row-key="(r: Record<string,unknown>) => r.record_no as string">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column prop="record_no" label="单号" width="180">
          <template #default="{ row }"><span class="record-no">{{ row.record_no }}</span></template>
        </el-table-column>
        <el-table-column label="调拨类型" width="100" align="center">
          <template #default="{ row }"><el-tag type="warning" size="small">仓库调拨</el-tag></template>
        </el-table-column>
        <el-table-column label="调出仓库" width="140" show-overflow-tooltip>
          <template #default="{ row }">
            <el-icon style="color:#A32D2D;vertical-align:middle;margin-right:2px"><Top /></el-icon>
            <span v-if="row.source_warehouse">{{ row.source_warehouse.name }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="调入仓库" width="140" show-overflow-tooltip>
          <template #default="{ row }">
            <el-icon style="color:#1D9E75;vertical-align:middle;margin-right:2px"><Bottom /></el-icon>
            <span v-if="row.target_warehouse">{{ row.target_warehouse.name }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="物料种类" width="80" align="center">
          <template #default="{ row }">{{ row.item_count || 1 }} 种</template>
        </el-table-column>
        <el-table-column label="调拨总数" width="100" align="right">
          <template #default="{ row }">
            <span style="font-weight:600;color:#0C447C">{{ row.total_quantity || 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作人" width="100">
          <template #default="{ row }">{{ row.operator?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="调拨时间" width="150">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
        <el-table-column label="操作" width="70" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openDetail(String(row.record_no||''))">详情</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination background layout="total,prev,pager,next" :total="pagination.total" :current-page="pagination.page" :page-size="pagination.per_page" @current-change="(p: number) => loadList(p)" />
      </div>
    </div>

    <!-- 新增调拨单弹窗 -->
    <el-dialog v-model="showFormDialog" title="新增调拨单" width="1500px" :close-on-click-modal="false" top="3vh">
      <div class="section-card">
        <div class="section-title"><el-icon><Document /></el-icon> 基本信息</div>
        <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="单号">
                <el-input :model-value="autoRecordNo" disabled style="width:100%">
                  <template #prefix><el-icon><Document /></el-icon></template>
                </el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="操作员">
                <el-input :model-value="curUser?.name || ''" disabled style="width:100%">
                  <template #prefix><el-icon><User /></el-icon></template>
                </el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="调拨时间">
                <el-input :model-value="formatDate(new Date().toISOString())" disabled style="width:100%" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="调出仓库" prop="source_warehouse_id">
                <el-select v-model="form.source_warehouse_id" placeholder="选择调出仓库" filterable style="width:100%" @change="onSourceChange">
                  <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="调入仓库" prop="target_warehouse_id">
                <el-select v-model="form.target_warehouse_id" placeholder="选择调入仓库" filterable style="width:100%">
                  <el-option v-for="w in targetWarehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item label="调拨说明" prop="remark">
            <el-input v-model="form.remark" maxlength="500" show-word-limit placeholder="例如: 项目结束物料调回主仓" />
          </el-form-item>
        </el-form>
      </div>

      <div class="section-card" style="margin-top:12px">
        <div class="section-title" style="display:flex;align-items:center;justify-content:space-between">
          <span><el-icon><Goods /></el-icon> 调拨物料明细 ({{ form.items.filter(r => r.item).length }} 种)</span>
          <div style="display:flex;gap:8px">
            <el-button size="small" type="warning" :icon="Plus" @click="openPickerBatch" :disabled="!form.source_warehouse_id">
              批量选择物料
            </el-button>
            <el-button size="small" :icon="Plus" plain @click="addItemRow" :disabled="!form.source_warehouse_id">单条添加</el-button>
          </div>
        </div>
        <el-table :data="form.items" stripe border style="width:100%" max-height="360">
          <el-table-column type="index" label="#" width="42" />
          <el-table-column label="编码" width="120">
            <template #default="{ row }"><span v-if="row.item" class="item-code">{{ row.item.code }}</span><span v-else class="text-muted">-</span></template>
          </el-table-column>
          <el-table-column label="物料名称" min-width="160">
            <template #default="{ row, $index }">
              <div style="display:flex;gap:4px;align-items:center">
                <span v-if="row.item" style="flex:1">{{ row.item.name }}</span>
                <el-button size="small" type="primary" link @click="openPicker($index)">{{ row.item?'更换':'选择物料' }}</el-button>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="规格" width="100" show-overflow-tooltip>
            <template #default="{ row }"><span v-if="row.item?.specification">{{ row.item.specification }}</span><span v-else-if="row.item?.spec">{{ row.item.spec }}</span><span v-else class="text-muted">-</span></template>
          </el-table-column>
          <el-table-column label="单位" width="60" align="center">
            <template #default="{ row }">{{ row.item?.unit || '-' }}</template>
          </el-table-column>
          <el-table-column label="源库存" width="80" align="right">
            <template #default="{ row }"><span style="color:#909399">{{ row.item?.current_stock ?? '-' }}</span></template>
          </el-table-column>
          <el-table-column label="调拨数量" width="130">
            <template #default="{ row }">
              <el-input-number v-model="row.quantity" :min="1" :step="1" :max="row.item?.current_stock||999999" style="width:110px" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="55" align="center">
            <template #default="{ _, $index }">
              <el-button type="danger" link size="small" :icon="Delete" @click="removeItemRow($index)" />
            </template>
          </el-table-column>
        </el-table>
        <div v-if="form.items.length===0" style="text-align:center;padding:24px;color:#c0c4cc">
          <el-empty :image-size="50" :description="form.source_warehouse_id ? '暂无物料，点击上方「批量选择物料」按钮' : '请先选择调出仓库'" />
        </div>
      </div>

      <template #footer>
        <el-button @click="showFormDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" :disabled="form.items.filter(i => i.item).length === 0" @click="handleSubmit">确认调拨</el-button>
      </template>
    </el-dialog>

    <!-- 详情弹窗 -->
    <el-dialog v-model="showDetail" title="调拨单详情" width="1440px" :close-on-click-modal="false">
      <template v-if="detailItem">
        <el-descriptions :column="4" border>
          <el-descriptions-item label="单号" :span="2">{{ detailItem.record_no }}</el-descriptions-item>
          <el-descriptions-item label="调拨类型" :span="2">
            <el-tag type="warning" size="small">仓库调拨</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="操作人">{{ detailItem.operator?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="调拨时间" :span="3">{{ formatDate(detailItem.created_at) }}</el-descriptions-item>
          <el-descriptions-item label="调出仓库" :span="2">
            <el-tag type="danger" size="small">{{ detailItem.source_warehouse?.name || '-' }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="调入仓库" :span="2">
            <el-tag type="success" size="small">{{ detailItem.target_warehouse?.name || '-' }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="物料种类">{{ detailItem.item_count || (detailItem.items?.length||0) }} 种</el-descriptions-item>
          <el-descriptions-item label="调拨总数">{{ detailItem.total_quantity || 0 }}</el-descriptions-item>
          <el-descriptions-item label="调拨说明" :span="4">{{ detailItem.remark || '-' }}</el-descriptions-item>
        </el-descriptions>
        <h4 style="margin:16px 0 8px;color:#0C447C">物料明细</h4>
        <el-table :data="detailItem.items || []" stripe border style="width:100%">
          <el-table-column type="index" label="#" width="50" />
          <el-table-column label="编码" width="130">
            <template #default="{ row }">{{ row.inventoryItem?.code || '-' }}</template>
          </el-table-column>
          <el-table-column label="物料名称" min-width="160">
            <template #default="{ row }">{{ row.inventoryItem?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="规格" width="120" show-overflow-tooltip>
            <template #default="{ row }">{{ row.inventoryItem?.specification || row.inventoryItem?.spec || '-' }}</template>
          </el-table-column>
          <el-table-column label="单位" width="60" align="center">
            <template #default="{ row }">{{ row.inventoryItem?.unit || '-' }}</template>
          </el-table-column>
          <el-table-column label="调拨数量" width="100" align="right">
            <template #default="{ row }">
              <span style="font-weight:600">{{ row.quantity }}</span>
            </template>
          </el-table-column>
          <el-table-column label="剩余库存" width="100" align="right">
            <template #default="{ row }">{{ row.remaining_stock ?? '-' }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!detailItem.items?.length" :image-size="50" description="无物料明细" />
      </template>
      <template #footer>
        <el-button @click="showDetail = false">关闭</el-button>
      </template>
    </el-dialog>

    <InventoryItemPicker
      :show="pickerVisible"
      :multiple="true"
      :selected-ids="pickedItemIds"
      @select="(items: InventoryItem[]) => onPickerSelect(items)"
      @close="pickerVisible = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Delete, Goods, User, Search, Document, Top, Bottom } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'
import InventoryItemPicker from './components/InventoryItemPicker.vue'
import type { InventoryItem, WarehouseOption } from './types'

interface BusinessOption { id: number; name: string }
interface TransferRow {
  record_no?: string; type?: string; created_at?: string
  source_warehouse?: { id?: number; name?: string } | null
  target_warehouse?: { id?: number; name?: string } | null
  item_count?: number; total_quantity?: number; is_transfer?: boolean
  operator?: { name?: string } | null
  remark?: string
  [k: string]: unknown
}
interface TransferDetail {
  record_no: string; type?: string; is_transfer?: boolean
  operator?: { name?: string } | null
  source_warehouse?: { id?: number; name?: string } | null
  target_warehouse?: { id?: number; name?: string } | null
  item_count?: number; total_quantity?: number
  created_at?: string; remark?: string
  items?: Array<Record<string, unknown> & { inventoryItem?: Record<string, unknown> | null; quantity?: number; remaining_stock?: number }>
}

const loading = ref(false)
const list = ref<TransferRow[]>([])
const pagination = reactive({ page: 1, per_page: 15, total: 0 })
const dateRange = ref<[string, string] | null>(null)
const searchKey = ref('')

// 详情
const showDetail = ref(false)
const detailItem = ref<TransferDetail | null>(null)

// 表单
const showFormDialog = ref(false)
const formRef = ref<any>(null)
const submitting = ref(false)
const warehouseOptions = ref<WarehouseOption[]>([])
const curUser = ref<BusinessOption | null>(null)
const pickerVisible = ref(false)
const pickerIndex = ref(-1)

const form = reactive({
  source_warehouse_id: null as number | null,
  target_warehouse_id: null as number | null,
  remark: '',
  items: [] as Array<{ uid?: string; item: InventoryItem | null; quantity: number }>,
})

const formRules = {
  source_warehouse_id: [{ required: true, message: '请选择调出仓库', trigger: 'change' }],
  target_warehouse_id: [{ required: true, message: '请选择调入仓库', trigger: 'change' }],
}

const autoRecordNo = computed(() => `TR-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-预生成`)
const targetWarehouseOptions = computed(() => warehouseOptions.value.filter(w => w.id !== form.source_warehouse_id))
const pickedItemIds = computed(() => form.items.filter(r => r.item).map(r => r.item!.id))

function formatDate(s?: string | null) {
  if (!s) return '-'
  const d = new Date(s)
  if (isNaN(d.getTime())) return s
  return s!.replace('T', ' ').slice(0, 16)
}

async function loadList(page = 1) {
  pagination.page = page
  loading.value = true
  try {
    const params: Record<string, unknown> = { type: 'transfer', page, per_page: pagination.per_page }
    if (searchKey.value) params.keyword = searchKey.value
    if (dateRange.value) { params.date_from = dateRange.value[0]; params.date_to = dateRange.value[1] }
    const res = await get('/inventory/stock-records', params)
    const pag = unwrapPaginate(res)
    list.value = pag.list
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
    warehouseOptions.value = unwrapList(res) as WarehouseOption[]
  } catch (e) { console.warn(e) }
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
  } catch { /* ignore */ }
}

function handleCreate() {
  resetForm()
  showFormDialog.value = true
}

function resetForm() {
  form.source_warehouse_id = null
  form.target_warehouse_id = null
  form.remark = ''
  form.items = []
}

function onSourceChange() {
  // 切换源仓后, 清空已选物料 (避免跨仓库)
  form.items = []
}

function addItemRow() {
  form.items.push({ uid: String(Date.now()) + Math.random(), item: null, quantity: 1 })
  pickerIndex.value = form.items.length - 1
  pickerVisible.value = true
}

function openPicker(idx: number) {
  pickerIndex.value = idx
  pickerVisible.value = true
}

function openPickerBatch() {
  pickerIndex.value = form.items.length
  pickerVisible.value = true
}

function removeItemRow(idx: number) {
  form.items.splice(idx, 1)
}

function onPickerSelect(items: InventoryItem[]) {
  if (!items || !items.length) return
  let idx = pickerIndex.value
  while (idx < form.items.length && !form.items[idx]?.item) {
    form.items.splice(idx, 1)
  }
  for (const it of items) {
    const existingIdx = form.items.findIndex(r => r.item?.id === it.id)
    if (existingIdx >= 0) form.items.splice(existingIdx, 1)
  }
  for (const it of items) {
    form.items.push({
      uid: String(Date.now()) + Math.random() + String(it.id),
      item: { ...it },
      quantity: Math.min(1, it.current_stock || 1),
    })
  }
  pickerIndex.value = -1
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate()
  const validItems = form.items.filter(i => i.item)
  if (validItems.length === 0) { ElMessage.warning('请至少选择一种物料'); return }

  if (form.source_warehouse_id === form.target_warehouse_id) {
    ElMessage.warning('调出仓库和调入仓库不能相同'); return
  }

  submitting.value = true
  try {
    const res = await post('/inventory/stock-transfer', {
      source_warehouse_id: form.source_warehouse_id,
      target_warehouse_id: form.target_warehouse_id,
      items: validItems.map(i => ({ item_id: i.item!.id, quantity: i.quantity })),
      remark: form.remark,
    })
    ElMessage.success(res?.message || '调拨成功')
    showFormDialog.value = false
    resetForm()
    loadList(1)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '调拨失败')
  } finally {
    submitting.value = false
  }
}

async function openDetail(recordNo: string) {
  detailItem.value = null
  showDetail.value = true
  try {
    const res = await get(`/inventory/stock-records/${recordNo}`)
    detailItem.value = (res?.data ?? res) as TransferDetail
  } catch (e) {
    console.error(e)
    showDetail.value = false
    ElMessage.error('加载详情失败')
  }
}

onMounted(() => {
  loadWarehouses()
  loadCurrentUser()
  loadList(1)
})
</script>

<style scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header { margin-bottom: 16px; h2 { font-size: 20px; color: #0C447C; margin: 0; } }
.filter-bar { display: flex; gap: 8px; margin-bottom: 12px; align-items: center; }
.content-card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.section-card { background: #fff; border-radius: 6px; padding: 16px; }
.section-title { font-size: 14px; font-weight: 600; color: #0C447C; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e6f1fb; display: flex; align-items: center; gap: 6px; }
.section-title .el-icon { font-size: 16px }
.item-code { font-family: "DIN Pro", monospace; font-weight: 500; color: #0C447C; font-size: 12px }
.text-muted { color: #c0c4cc; }
.record-no { font-family: "DIN Pro", monospace; font-weight: 500; color: #0C447C; }
.muted { color: #c0c4cc; }
.pagination-wrap { margin-top: 12px; display: flex; justify-content: flex-end; }
</style>