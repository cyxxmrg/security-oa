<template>
  <div class="page-container">
    <div class="page-header"><h2>物料退库</h2></div>

    <div class="filter-bar">
      <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width:240px" @change="loadList(1)" />
      <el-input v-model="searchKey" placeholder="搜索单号/物料" clearable style="width:220px" :prefix-icon="Search" @keyup.enter="loadList(1)" @clear="loadList(1)" />
      <el-button type="primary" plain :icon="Plus" @click="handleCreate">新增退库单</el-button>
    </div>

    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width:100%" :row-key="(r: Record<string,unknown>) => r.record_no as string">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column prop="record_no" label="单号" width="170">
          <template #default="{ row }"><span class="record-no">{{ row.record_no }}</span></template>
        </el-table-column>
        <el-table-column label="退库类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag type="success" size="small">退库入库</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="物料种类" width="80" align="center">
          <template #default="{ row }">{{ row.item_count || 1 }} 种</template>
        </el-table-column>
        <el-table-column label="退库总数" width="100" align="right">
          <template #default="{ row }"><span style="font-weight:600;color:#1D9E75">+{{ row.total_quantity || 0 }}</span></template>
        </el-table-column>
        <el-table-column label="仓库" width="100">
          <template #default="{ row }">{{ row.warehouse?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="关联项目" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.project">{{ row.project.name }}</span>
            <span v-else class="muted">未关联</span>
          </template>
        </el-table-column>
        <el-table-column label="往来单位" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.party">{{ row.party.name }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="操作人" width="100">
          <template #default="{ row }">{{ row.operator?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="退库时间" width="150">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="退库原因" min-width="160" show-overflow-tooltip />
        <el-table-column label="操作" width="70" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openDetail(String(row.record_no||''))">详情</el-button>
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

    <!-- 新增退库弹窗 -->
    <el-dialog v-model="showFormDialog" title="新增物料退库" width="1500px" :close-on-click-modal="false" top="3vh">
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
              <el-form-item label="退料类型">
                <el-radio-group v-model="form.sub_type">
                  <el-radio value="return">退料入库</el-radio>
                </el-radio-group>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="退回仓库" prop="warehouse_id">
                <el-select v-model="form.warehouse_id" placeholder="选择仓库" style="width:100%">
                  <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="关联项目">
                <el-select v-model="form.project_id" filterable clearable placeholder="选择项目(可选)" style="width:100%">
                  <el-option v-for="p in projectOptions" :key="p.id" :label="p.name" :value="p.id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="退料操作员">
                <el-input :model-value="curUser?.name||''" disabled style="width:100%">
                  <template #prefix><el-icon><User /></el-icon></template>
                </el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="退库原因" prop="remark">
                <el-input v-model="form.remark" maxlength="500" show-word-limit placeholder="例如: 项目剩余物料退回" />
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>

      <div class="section-card" style="margin-top:16px">
        <div class="section-title" style="display:flex;align-items:center;justify-content:space-between">
          <span><el-icon><Goods /></el-icon> 退库明细 ({{ form.items.filter(r => r.item).length }} 种物料)</span>
          <div style="display:flex;gap:8px">
            <el-button size="small" type="success" :icon="FolderOpened" @click="openImportDialog">从项目导入</el-button>
            <el-button size="small" type="warning" :icon="Plus" @click="openPickerBatch">批量选择物料</el-button>
            <el-button size="small" :icon="Plus" plain @click="addItemRow">单条添加</el-button>
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
          <el-table-column label="退库数量" width="130">
            <template #default="{ row }">
              <el-input-number v-model="row.quantity" :min="1" :step="1" style="width:110px" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="55" align="center">
            <template #default="{ _, $index }">
              <el-button type="danger" link size="small" :icon="Delete" @click="removeItemRow($index)" />
            </template>
          </el-table-column>
        </el-table>
        <div v-if="form.items.length===0" style="text-align:center;padding:24px;color:#c0c4cc">
          <el-empty :image-size="50" description="暂无物料，点击上方「批量选择物料」按钮" />
        </div>
      </div>

      <template #footer>
        <el-button @click="showFormDialog = false">取消</el-button>
        <el-button type="success" :loading="submitting" @click="handleSubmit">确认退库</el-button>
      </template>
    </el-dialog>

    <!-- 从项目导入物料弹窗 -->
    <el-dialog v-model="importDialogVisible" title="从项目导入物料" width="800px" :close-on-click-modal="false">
      <div style="margin-bottom:12px;display:flex;gap:8px">
        <el-select v-model="importSelectedProject" filterable placeholder="选择项目" style="width:300px" @change="loadProjectMaterials">
          <el-option v-for="p in projectOptions" :key="p.id" :label="p.name" :value="p.id" />
        </el-select>
        <el-button :loading="importLoading" :disabled="!importSelectedProject" @click="loadProjectMaterials()">加载物料</el-button>
      </div>
      <div v-if="importLoading" style="text-align:center;padding:40px"><el-icon class="is-loading" :size="24"><Loading /></el-icon> 加载中...</div>
      <template v-else-if="importProjectMaterials.length > 0">
        <div style="margin-bottom:8px">
          <el-checkbox v-model="importSelectAll" @change="toggleImportAll">全选 ({{ importProjectMaterials.length }} 种物料)</el-checkbox>
        </div>
        <el-table :data="importProjectMaterials" stripe border style="width:100%" max-height="400" @selection-change="onImportSelectionChange">
          <el-table-column type="selection" width="42" />
          <el-table-column label="物料名称" min-width="160" prop="material_name" />
          <el-table-column label="规格" width="120" prop="specification" show-overflow-tooltip />
          <el-table-column label="数量" width="80" prop="quantity" />
          <el-table-column label="单位" width="60" prop="unit" />
          <el-table-column label="单价" width="100">
            <template #default="{ row }">¥{{ Number(row.unit_cost||0).toFixed(2) }}</template>
          </el-table-column>
        </el-table>
      </template>
      <el-empty v-else-if="importSelectedProject && !importLoading" :image-size="50" description="该项目暂无物料清单" />
      <el-empty v-else :image-size="50" description="请先选择一个项目" />
      <template #footer>
        <el-button @click="importDialogVisible = false">取消</el-button>
        <el-button type="success" :disabled="importSelectedIds.length === 0" @click="confirmImportMaterials">导入物料 ({{ importSelectedIds.length }})</el-button>
      </template>
    </el-dialog>

    <InventoryItemPicker
      :show="pickerVisible"
      :multiple="true"
      :selected-ids="pickedItemIds"
      @select="(items: InventoryItem[]) => onPickerSelect(items)"
      @close="pickerVisible = false"
    />

    <!-- 详情弹窗 -->
    <el-dialog v-model="showDetail" title="退库单详情" width="1440px" :close-on-click-modal="false">
      <template v-if="detailItem">
        <el-descriptions :column="4" border>
          <el-descriptions-item label="单号" :span="2">{{ (detailItem as ReturnDetail).record_no }}</el-descriptions-item>
          <el-descriptions-item label="退库类型" :span="2">
            <el-tag type="success" size="small">退库入库</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="操作人">{{ (detailItem as ReturnDetail).operator?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="仓库">{{ (detailItem as ReturnDetail).warehouse?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="关联项目">
            <span v-if="(detailItem as ReturnDetail).project">{{ (detailItem as ReturnDetail).project!.name }}</span>
            <span v-else class="muted">未关联</span>
          </el-descriptions-item>
          <el-descriptions-item label="物料种类">{{ (detailItem as ReturnDetail).item_count || 1 }} 种</el-descriptions-item>
          <el-descriptions-item label="退库总数">
            <span style="font-weight:600;color:#1D9E75">+{{ (detailItem as ReturnDetail).total_quantity || 0 }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="退库时间">{{ formatDate((detailItem as ReturnDetail).created_at as string) }}</el-descriptions-item>
          <el-descriptions-item label="退库原因" :span="4">{{ (detailItem as ReturnDetail).remark || '-' }}</el-descriptions-item>
        </el-descriptions>
        <h4 style="margin:16px 0 8px;color:#0C447C">物料明细</h4>
        <el-table :data="((detailItem as ReturnDetail).items as ReturnDetailItem[]) || []" stripe border style="width:100%">
          <el-table-column type="index" label="#" width="50" />
          <el-table-column label="物料编码" min-width="140" prop="inventory_item_id" />
          <el-table-column label="物料名称" min-width="180" show-overflow-tooltip>
            <template #default="{ row }">{{ (row as ReturnDetailItem).inventoryItem?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="规格" min-width="120" show-overflow-tooltip>
            <template #default="{ row }">{{ (row as ReturnDetailItem).inventoryItem?.specification || (row as ReturnDetailItem).inventoryItem?.spec || '-' }}</template>
          </el-table-column>
          <el-table-column label="单位" width="60" align="center">
            <template #default="{ row }">{{ (row as ReturnDetailItem).inventoryItem?.unit || '-' }}</template>
          </el-table-column>
          <el-table-column label="退库数量" width="120" align="right">
            <template #default="{ row }">
              <span style="font-weight:600;color:#1D9E75">+{{ (row as ReturnDetailItem).quantity }}</span>
            </template>
          </el-table-column>
          <el-table-column label="仓库" min-width="120">
            <template #default="{ row }">{{ (row as ReturnDetailItem).warehouse?.name || (detailItem as ReturnDetail).warehouse?.name || '-' }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!((detailItem as ReturnDetail).items as ReturnDetailItem[])?.length" :image-size="50" description="无物料明细" />
      </template>
      <template #footer>
        <el-button @click="showDetail = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Search, Plus, Document, Goods, Delete, FolderOpened, Loading, User } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'
import InventoryItemPicker from './components/InventoryItemPicker.vue'
import type { StockRecord, InventoryItem, WarehouseOption } from './types'

interface BusinessOption { id: number; name: string }
interface ReturnDetailItem extends Record<string, unknown> {
  inventory_item_id?: number | string
  quantity: number
  inventoryItem?: { name?: string; specification?: string; spec?: string; unit?: string } | null
  warehouse?: { name?: string } | null
}
interface ReturnDetail extends Record<string, unknown> {
  record_no?: string
  operator?: { name?: string } | null
  warehouse?: { name?: string } | null
  project?: { name?: string } | null
  item_count?: number
  total_quantity?: number
  created_at?: string
  remark?: string
  items?: ReturnDetailItem[]
}

const searchKey = ref('')
const dateRange = ref<[string, string] | null>(null)
const list = ref<StockRecord[]>([])
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
    const res = await get('/inventory/stock-records', { type: 'return', page, per_page: pagination.per_page })
    const pag = unwrapPaginate(res)
    let items = pag.list
    if (searchKey.value) {
      const kw = searchKey.value.toLowerCase()
      items = (items as Array<Record<string, unknown>>).filter(r => {
        const no = ((r.record_no as string) || '').toLowerCase()
        const pname = (((r.party as { name?: string } | undefined)?.name) || '').toLowerCase()
        return no.includes(kw) || pname.includes(kw)
      })
    }
    if (dateRange.value) {
      const [from, to] = dateRange.value
      items = (items as Array<Record<string, unknown>>).filter(r => {
        const t = ((r.created_at as string) || '').slice(0, 10)
        return t >= from && t <= to
      })
    }
    list.value = items as StockRecord[]
    pagination.total = pag.total
  } catch (e) {
    console.error('[loadList]', e)
    list.value = []
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
    const res = await get('/projects', { per_page: 500 })
    projectOptions.value = unwrapList(res) as unknown as BusinessOption[]
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

// 详情弹窗
const showDetail = ref(false)
const detailItem = ref<ReturnDetail | null>(null)
async function openDetail(recordNo: string) {
  detailItem.value = null
  showDetail.value = true
  try {
    const res = await get(`/inventory/stock-records/${recordNo}`)
    const raw = (res?.data ?? res) as Record<string, unknown>
    detailItem.value = raw as unknown as ReturnDetail
  } catch (e) {
    console.error(e)
    showDetail.value = false
    ElMessage.error('加载详情失败')
  }
}

// 弹窗表单
const showFormDialog = ref(false)
import type { FormInstance } from 'element-plus'
const formRef = ref<FormInstance | null>(null)
const submitting = ref(false)
const pickerVisible = ref(false)
const pickerIndex = ref(-1)
/** 已选物料 id 列表 (用于 picker 回显勾选状态) */
const pickedItemIds = computed(() => form.items.filter(r => r.item).map(r => r.item!.id))

const form = reactive({
  warehouse_id: null as number | null,
  project_id: null as number | null,
  sub_type: 'return',
  remark: '',
  items: [] as Array<{ uid?: string; item: InventoryItem | null; quantity: number }>,
})
const formRules = {
  warehouse_id: [{ required: true, message: '请选择仓库', trigger: 'change' }],
  remark:       [{ required: true, message: '请填写退库原因', trigger: 'blur' }],
}
const autoRecordNo = computed(() => {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `MR-${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-预生成`
})

// 从项目导入
const importDialogVisible = ref(false)
const importSelectedProject = ref<number | null>(null)
const importProjectMaterials = ref<{ id: number; material_name: string; specification: string; quantity: number; unit: string; unit_cost: number; inventory_item_id?: number }[]>([])
const importLoading = ref(false)
const importSelectedIds = ref<number[]>([])
const importSelectAll = ref(false)
async function openImportDialog() {
  importSelectedProject.value = form.project_id
  importProjectMaterials.value = []
  importSelectedIds.value = []
  importSelectAll.value = false
  importDialogVisible.value = true
  if (importSelectedProject.value) await loadProjectMaterials()
}
function onImportSelectionChange(sel: { id: number }[]) {
  importSelectedIds.value = sel.map(s => s.id)
}

function toggleImportAll(v: boolean) {
  importSelectedIds.value = v ? importProjectMaterials.value.map(m => m.id) : []
}
async function loadProjectMaterials() {
  if (!importSelectedProject.value) return
  importLoading.value = true
  importProjectMaterials.value = []
  try {
    const res = await get(`/projects/${importSelectedProject.value}/materials`)
    const raw = (res?.data?.data ?? res?.data ?? []) as Array<Record<string, unknown>>
    importProjectMaterials.value = raw as typeof importProjectMaterials.value
    importSelectedIds.value = []
    importSelectAll.value = false
  } catch (e) {
    console.warn(e)
    ElMessage.error('加载项目物料失败')
  } finally {
    importLoading.value = false
  }
}
function confirmImportMaterials() {
  const selected = importProjectMaterials.value.filter(m => importSelectedIds.value.includes(m.id))
  for (const m of selected) {
    form.items.push({
      uid: String(Date.now()) + Math.random(),
      item: {
        id: m.inventory_item_id || undefined,
        name: m.material_name,
        code: '',
        specification: m.specification || '',
        unit: m.unit,
        current_stock: 0,
      } as InventoryItem,
      quantity: Number(m.quantity) || 1,
    })
  }
  importDialogVisible.value = false
  ElMessage.success(`已导入 ${selected.length} 种物料`)
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
    form.items.push({ uid: String(Date.now()) + Math.random() + String(it.id), item: { ...it }, quantity: Math.min(1, it.current_stock || 1) })
  }
  pickerIndex.value = -1
}

function handleCreate() {
  form.warehouse_id = warehouseOptions.value[0]?.id || null
  form.project_id = null
  form.sub_type = 'return'
  form.remark = ''
  form.items = [{ uid: String(Date.now()) + Math.random(), item: null, quantity: 1 }]
  showFormDialog.value = true
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate()
  const validItems = form.items.filter(i => i.item)
  if (validItems.length === 0) { ElMessage.warning('请至少选择一种物料'); return }
  submitting.value = true
  try {
    const d = new Date()
    const ymd = `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`
    const batchNo = `MR${ymd}${Date.now().toString(36).toUpperCase()}`
    await post('/inventory/stock-in', {
      items: validItems.map(i => ({ item_id: i.item!.id, quantity: i.quantity })),
      warehouse_id: form.warehouse_id,
      project_id: form.project_id,
      type: 'return',
      batch_no: batchNo,
      remark: form.remark,
    })
    ElMessage.success('退库成功')
    showFormDialog.value = false
    loadList(pagination.page)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '退库失败')
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
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.content-card {
  background: #fff; border-radius: 8px; padding: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.muted { color: #c0c4cc; }
.section-card { background:#fff; border:1px solid #e8ecf1; border-radius:8px; padding:16px 16px 0 }
.section-title { font-size:14px; font-weight:600; color:#0C447C; margin-bottom:12px; padding-bottom:8px; border-bottom:2px solid #e6f1fb; display:flex; align-items:center; gap:6px }
.section-title .el-icon { font-size:16px }
.item-code { font-family:"DIN Pro",monospace; font-weight:500; color:#0C447C; font-size:12px }
.record-no { font-family:"DIN Pro",monospace; color:#0C447C; font-weight:600 }
:deep(.el-dialog__body) { padding-top:12px }
.unit-text { color: #909399; font-size: 12px; }
.text-muted { color: #c0c4cc; }
</style>
