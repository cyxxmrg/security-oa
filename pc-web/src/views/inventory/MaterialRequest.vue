<template>
  <div class="page-container">
    <div class="page-header">
      <h2>物料申领</h2>
    </div>
    <div class="filter-bar">
      <el-input v-model="searchKey" placeholder="搜索单号/物料" clearable style="width: 240px" :prefix-icon="Search" @keyup.enter="loadList(1)" @clear="loadList(1)" />
      <el-button type="primary" plain :icon="Plus" @click="handleCreate">新增申领</el-button>
    </div>

    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column label="审批单号" width="160">
          <template #default="{row}"><span class="record-no">{{ row.code }}</span></template>
        </el-table-column>
        <el-table-column label="申领内容" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.title }}
          </template>
        </el-table-column>
        <el-table-column label="申领人" width="100" align="center">
          <template #default="{ row }">{{ row.initiator?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="关联项目" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <el-tag v-if="row.projectName" type="info" size="small" effect="plain">{{ row.projectName }}</el-tag>
            <span v-else class="muted">未关联</span>
          </template>
        </el-table-column>
        <el-table-column label="申领总数" width="120" align="right">
          <template #default="{ row }">
            <span style="font-weight: 600; color: #A32D2D">{{ row.quantityTotal || 0 }}</span>
            <span class="unit-text" style="margin-left:4px">{{ row.payload?.items?.length || 1 }} 种</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{row}">
            <el-tag :type="row.status==='approved'?'success':row.status==='rejected'?'danger':'warning'" size="small">{{ ({pending:'待审批',approved:'已通过',rejected:'已驳回',transferred:'已转交',cancelled:'已取消'} as Record<string, string>)[row.status as string] || row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="提交时间" width="160">
          <template #default="{ row }">{{ row.created_at?.slice(0,16)||'-' }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="申领说明" min-width="200" show-overflow-tooltip />
        <el-table-column label="操作" width="80" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openDetail(row.id)">详情</el-button>
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

    <RequestFormDialog
      ref="dialogRef"
      v-model:visible="showFormDialog"
      :form="form"
      :rules="rules"
      :project-options="projectOptions"
      :warehouse-options="warehouseOptions"
      :current-user-name="curUser?.name || ''"
      :submitting="submitting"
      @add-item="openPickerBatch"
      @remove-item="(i: number) => form.items.splice(i, 1)"
      @pick-item="openPickerByUid"
      @submit="handleSubmit"
    />

    <ItemPickerDialog
      v-model:visible="pickerVisible"
      :selected-ids="pickedItemIds"
      @select="onPickerSelect"
    />

    <!-- 详情弹窗 -->
    <el-dialog v-model="showDetail" title="物料申领详情" width="1100px" :close-on-click-modal="false">
      <template v-if="detailItem">
        <!-- 基本信息 -->
        <el-descriptions :column="4" border>
          <el-descriptions-item label="单号" :span="2">{{ detailItem.code }}</el-descriptions-item>
          <el-descriptions-item label="状态" :span="2">
            <el-tag :type="detailStatusType(detailItem.status)" size="small">{{ detailStatusLabel(detailItem.status) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="申请人">{{ detailItem.initiator?.name||'-' }}</el-descriptions-item>
          <el-descriptions-item label="关联项目">
            <el-tag v-if="detailItem.projectName" type="info" size="small" effect="plain">{{ detailItem.projectName }}</el-tag>
            <span v-else class="muted">未关联</span>
          </el-descriptions-item>
          <el-descriptions-item label="申领总数" :span="2">
            <span style="font-weight:600;color:#A32D2D">{{ detailItem.quantityTotal || 0 }}</span>
            <span class="unit-text" style="margin-left:8px">{{ detailItem.payload?.items?.length || 1 }} 种物料</span>
          </el-descriptions-item>
          <el-descriptions-item label="提交时间" :span="2">{{ formatDate(detailItem.created_at) }}</el-descriptions-item>
          <el-descriptions-item label="最近更新" :span="2">{{ formatDate(detailItem.updated_at) }}</el-descriptions-item>
          <el-descriptions-item label="申领说明" :span="4">{{ detailItem.payload?.remark || detailItem.comment || '-' }}</el-descriptions-item>
        </el-descriptions>

        <!-- 物料明细表 -->
        <h4 style="margin:16px 0 8px;color:#0C447C">物料明细</h4>
        <el-table :data="detailItem.itemsEnriched||detailItem.payload?.items||[]" stripe border style="width:100%">
          <el-table-column type="index" label="#" width="42" />
          <el-table-column label="物料编码" min-width="140">
            <template #default="{ row }">
              <span class="item-code">{{ row.inventoryItem?.code || row.inventory_item_id || '-' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="物料名称" min-width="180" show-overflow-tooltip>
            <template #default="{ row }">{{ row.inventoryItem?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="规格" min-width="120" show-overflow-tooltip>
            <template #default="{ row }">{{ row.inventoryItem?.specification || row.inventoryItem?.spec || '-' }}</template>
          </el-table-column>
          <el-table-column label="单位" width="60" align="center">
            <template #default="{ row }">{{ row.inventoryItem?.unit || '-' }}</template>
          </el-table-column>
          <el-table-column label="申领数量" width="120" align="right">
            <template #default="{ row }">
              <span style="font-weight:600;color:#A32D2D">{{ row.quantity }}</span>
            </template>
          </el-table-column>
          <el-table-column label="仓库" min-width="120">
            <template #default="{ row }">{{ row.warehouse?.name || '-' }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!(detailItem.itemsEnriched||detailItem.payload?.items||[]).length" :image-size="50" description="无物料明细" />

        <!-- 审批流程时间线 -->
        <h4 style="margin:16px 0 8px;color:#0C447C">审批流程</h4>
        <el-timeline v-if="detailItem.flow?.length">
          <el-timeline-item
            v-for="(step, idx) in detailItem.flow"
            :key="idx"
            :timestamp="step.time"
            :type="step.action==='approve' ?'primary': step.action==='reject'?'danger':'info'"
            :hollow="step.action==='submit'"
          >
            <b>{{ step.operator }}</b>
            <span style="margin-left:8px;color:#909399">{{ flowLabel(step.action) }}</span>
            <div v-if="step.comment" style="margin-top:4px;color:#606266;font-size:12px">{{ step.comment }}</div>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-else :image-size="50" description="无审批流程记录" />
      </template>
      <template #footer>
        <el-button @click="showDetail = false">关闭</el-button>
      </template>
    </el-dialog>

</div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Search, Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'
import RequestFormDialog from './components/material-request/RequestFormDialog.vue'
import ItemPickerDialog from './components/material-request/ItemPickerDialog.vue'
import type { MaterialRequest, InventoryItem, WarehouseOption } from './types'

interface BusinessOption { id: number; name: string }

const searchKey = ref('')
const list = ref<MaterialRequest[]>([])
const loading = ref(false)
const pagination = reactive({ page: 1, per_page: 15, total: 0 })
const itemOptions = ref<InventoryItem[]>([])
const warehouseOptions = ref<WarehouseOption[]>([])
const projectOptions = ref<BusinessOption[]>([])
const userOptions = ref<BusinessOption[]>([])

const formatDate = (s?: string) => {
  if (!s) return '-'
  const d = new Date(s)
  if (isNaN(d.getTime())) return s
  const pad = (n: number) => n.toString().padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}



async function loadList(page = 1) {
  pagination.page = page
  loading.value = true
  try {
    const res = await get('/approvals/operation', { sub_type: 'material-request', page, per_page: pagination.per_page })
    // V0.6.3: res = {code, data: paginator}
    const pag = unwrapPaginate(res)
    list.value = pag.list
    pagination.total = pag.total
  } catch (e) {
    console.error(e)
    list.value = []
  } finally {
    loading.value = false
  }
}

async function loadItems() {
  // V1.2.7m: ItemPickerDialog 自带按需拉取
}

async function loadProjects() {
  try { const res = await get('/projects', { per_page: 500 }); projectOptions.value = unwrapList(res) }
  catch(e){ console.warn(e) }
}

async function loadUsers() {
  try { const res=await get('/employees',{per_page:500}); userOptions.value=unwrapList(res) }
  catch(e){ console.warn(e) }
}

async function loadCurrentUser() {
  // V1.2.14p: 直接从 localStorage 读取 (跟入库单的"操作员"逻辑一致)
  // 优先 auth/me 异步刷新, 否则用 localStorage
  try {
    const stored = localStorage.getItem('oa_user_info')
    if (stored) {
      const u = JSON.parse(stored)
      curUser.value = { id: u.id, name: u.name || u.username || '' }
    }
    // 异步刷新
    const res = await get('/auth/me')
    const u = (res?.data?.user || res?.data) as { id?: number; name?: string } | null
    if (u && u.id) curUser.value = { id: u.id, name: u.name || u.username || '' }
  } catch(e) { /* ignore */ }
}

async function loadWarehouses() {
  try {
    const res = await get('/inventory/warehouses')
    warehouseOptions.value = res.data || res || []
  } catch (e) { console.warn('[loadWarehouses]', e) }
}

const curUser = ref<BusinessOption | null>(null)

const showFormDialog = ref(false)
// V1.2.14p: 详情弹窗
const showDetail = ref(false)
const detailItem = ref<Record<string, unknown> | null>(null)
async function openDetail(id: number | string) {
  showDetail.value = true
  detailItem.value = null
  try {
    const res = await get(`/approvals/operation/${id}`)
    detailItem.value = (res?.data || res) as Record<string, unknown>
  } catch(e) { console.error(e); showDetail.value = false; ElMessage.error('加载详情失败') }
}
function detailStatusLabel(s?: string) {
  return ({ pending:'待审批', approved:'已通过', rejected:'已驳回', cancelled:'已取消' } as Record<string, string>)[s || ''] || s || '-'
}
function detailStatusType(s?: string): 'success'|'warning'|'danger'|'info' {
  if (s === 'approved') return 'success'
  if (s === 'rejected') return 'danger'
  if (s === 'cancelled') return 'info'
  return 'warning'
}
function flowLabel(a?: string) {
  return ({ submit:'提交申请', approve:'审批通过', reject:'审批驳回', complete:'已完成' } as Record<string, string>)[a || ''] || a || '-'
}
import type { FormInstance } from 'element-plus'
const dialogRef = ref<InstanceType<typeof RequestFormDialog> | null>(null)
const submitting = ref(false)
const form = reactive({
  warehouse_id: null as number | null,
  type: 'outbound',
  project_id: null as number | null,
  applicant_id: null as number | null,
  remark: '',
  items: [] as Array<{ uid: string; item: InventoryItem | null; quantity: number }>,
})
const rules = {
  project_id:   [{ required: true, message: "请选择项目", trigger: "change" }],
  warehouse_id: [{ required: true, message: "请选择仓库", trigger: "change" }],
  remark:       [{ required: true, message: "请填写申领说明", trigger: "blur" }],
}

function handleCreate() {
  form.warehouse_id = warehouseOptions.value[0]?.id||null
  form.project_id = null
  form.applicant_id = curUser.value?.id||null
  form.remark = ""
  form.items = [{ uid: String(Date.now()) + String(Math.random()), item: null, quantity: 1 }]
  showFormDialog.value = true
}

const pickerVisible = ref(false)
const pickerIndex = ref(-1)
/** 已选物料 id 列表 (用于 picker 回显勾选状态) */
const pickedItemIds = computed(() => form.items.filter(r => r.item).map(r => r.item!.id))

function addItemRow() {
  form.items.push({ uid: String(Date.now()) + String(Math.random()), item: null, quantity: 1 })
}

function openPickerBatch() {
  // V1.2.9: 一次性多选物料
  pickerIndex.value = form.items.length
  pickerVisible.value = true
}

function removeItemRow(idx: number) {
  form.items.splice(idx, 1)
}


function openPickerByUid(uid: string) {
  const idx = form.items.findIndex(i => i.uid === uid)
  if (idx >= 0) openPicker(idx)
}


function openPicker(idx: number) {
  pickerIndex.value = idx
  pickerVisible.value = true
}

function onPickerSelect(items: InventoryItem[]) {
  // V1.2.9 多选模式: 一次性把选中的物料全部追加到 form.items
  if (!items || !items.length) return
  let idx = pickerIndex.value
  // 截掉从 idx 开始的空行
  while (idx < form.items.length && !form.items[idx]?.item) {
    form.items.splice(idx, 1)
  }
  // 去重
  for (const it of items) {
    const existingIdx = form.items.findIndex(r => r.item?.id === it.id)
    if (existingIdx >= 0) form.items.splice(existingIdx, 1)
  }
  // 逐行追加
  for (const it of items) {
    form.items.push({ uid: String(Date.now()) + String(Math.random()) + String(it.id), item: { ...it }, quantity: Math.min(1, it.current_stock || 1) })
  }
  pickerIndex.value = -1
}

async function handleSubmit() {
  if (!dialogRef.value?.formRef) { ElMessage.error("表单未初始化"); return }
  try {
    const valid = await dialogRef.value.formRef.validate()
    if (!valid) { ElMessage.warning("请检查表单填写"); submitting.value = false; return }
  } catch(e) {
    ElMessage.warning("请检查表单填写")
    submitting.value = false
    return
  }
  const validItems = form.items.filter(i=>i.item)
  if (validItems.length === 0) { ElMessage.warning("请至少选择一种物料"); return }

  submitting.value = true
  try {
    const itemsPayload = validItems.map(i => ({
      inventory_item_id: i.item!.id,
      quantity: i.quantity,
      warehouse_id: form.warehouse_id,
    }))
    await post("/approvals/operation", {
      sub_type: "material-request",
      title: "物料申领",
      payload: {
        items: itemsPayload,
        project_id: form.project_id,
        remark: form.remark,
      },
      applicant_id: form.applicant_id,
    })
    ElMessage.success("申领已提交，等待审批通过后自动出库")
    showFormDialog.value = false
    loadList(pagination.page)
  } catch(e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || "提交审批失败")
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadList(1)
  loadItems()
  loadWarehouses()
  loadProjects()
  loadUsers()
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
:deep(.el-dialog__body) { padding-top:12px }
.unit-text { color: #909399; font-size: 12px; }
.form-tip { font-size: 12px; color: #909399; margin-top: 4px; }
</style>
