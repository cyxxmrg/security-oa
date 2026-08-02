<template>
  <div class="page-container">
    <!-- V0.5.6 B3 — 兼容期迁移横幅 -->
    <el-alert
      title="⚠️ 老工单模块已迁移到「维修中心」"
      type="warning"
      :closable="false"
      show-icon
      style="margin-bottom: 16px"
    >
      <template #default>
        此页面为兼容期保留, 数据只读, 不会再创建新单。
        <el-link type="primary" :underline="false" @click="$router.push('/maintenance/work-orders')" style="margin-left: 8px">
          → 前往新的维修中心
        </el-link>
      </template>
    </el-alert>
    <div class="page-header">
      <h2>维修工单管理 <el-tag size="small" type="info">只读</el-tag></h2>
    </div>
    <div class="filter-bar">
      <el-input v-model="searchForm.keyword" placeholder="搜索工单号/故障描述" clearable style="width: 240px" @keyup.enter="loadList(1)" />
      <el-select v-model="searchForm.status" placeholder="工单状态" clearable style="width: 140px">
        <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
      </el-select>
      <el-select v-model="searchForm.urgency" placeholder="紧急程度" clearable style="width: 140px">
        <el-option v-for="o in urgencyOptions" :key="o.value" :label="o.label" :value="o.value" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="loadList(1)">搜索</el-button>
      <el-button @click="resetSearch">重置</el-button>
      <el-button type="primary" plain :icon="Plus" @click="handleCreate">新建工单</el-button>
    </div>
    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column prop="order_no" label="工单号" width="160" />
        <el-table-column label="客户" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.customer">{{ row.customer.name }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="fault_description" label="故障描述" min-width="200" show-overflow-tooltip />
        <el-table-column label="紧急程度" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="urgencyTagType(row.urgency)" effect="dark">{{ urgencyLabel(row.urgency) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="维修人员" width="120">
          <template #default="{ row }">
            <span v-if="row.assigned_user">{{ row.assigned_user.name }}</span>
            <span v-else-if="row.assignedUser">{{ row.assignedUser.name }}</span>
            <span v-else class="muted">未派单</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="170">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="280" align="center" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'pending'"  link type="warning" size="small" @click="handleDispatch(row)">派单</el-button>
            <el-button v-if="row.status === 'assigned'" link type="primary" size="small" @click="handleDispatch(row)">改派</el-button>
            <el-button v-if="row.status === 'assigned'" link type="success" size="small" @click="handleStart(row)">开始</el-button>
            <el-button v-if="row.status === 'in_progress'" link type="primary" size="small" @click="handleComplete(row)">完成</el-button>
            <el-button v-if="row.status === 'completed'" link type="info" size="small" @click="handleReassign(row)">重新指派</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          background
          layout="total, sizes, prev, pager, next, jumper"
          :total="pagination.total"
          :current-page="pagination.page"
          :page-size="pagination.per_page"
          :page-sizes="[10, 20, 50]"
          @current-change="(p) => loadList(p)"
          @size-change="(s) => { pagination.per_page = s; loadList(1) }"
        />
      </div>
    </div>

    <!-- 派单对话框 -->
    <el-dialog v-model="showDispatchDialog" title="指派维修人员" width="1500px" :close-on-click-modal="false">
      <div v-if="dispatchingRow" class="dispatch-info">
        <div class="dispatch-order"><span class="label">工单号：</span><span class="value">{{ dispatchingRow.order_no }}</span></div>
        <div class="dispatch-order"><span class="label">客户：</span><span class="value">{{ dispatchingRow.customer?.name || '-' }}</span></div>
        <div class="dispatch-order"><span class="label">故障描述：</span><span class="value">{{ dispatchingRow.fault_description }}</span></div>
        <div class="dispatch-order">
          <span class="label">紧急程度：</span>
          <el-tag :type="urgencyTagType(dispatchingRow.urgency)" effect="dark" size="small">{{ urgencyLabel(dispatchingRow.urgency) }}</el-tag>
        </div>
      </div>
      <el-form label-width="90px" style="margin-top: 16px;">
        <el-form-item label="指派人员" required>
          <el-select v-model="assignedUserId" placeholder="请选择维修人员" filterable style="width: 100%">
            <el-option
              v-for="emp in technicianOptions"
              :key="emp.id"
              :label="`${emp.name}（${emp.department || '未分配'}）`"
              :value="emp.id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDispatchDialog = false">取消</el-button>
        <el-button type="primary" :loading="dispatchLoading" :disabled="!assignedUserId" @click="confirmDispatch">确认派单</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'

const router = useRouter()

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

const statusOptions = [
  { value: 'pending',     label: '待处理' },
  { value: 'assigned',    label: '已派单' },
  { value: 'in_progress', label: '维修中' },
  { value: 'completed',   label: '待确认' },
  { value: 'confirmed',   label: '已完成' },
]
const urgencyOptions = [
  { value: 'normal',  label: '普通' },
  { value: 'urgent',  label: '紧急' },
  { value: 'critical',label: '特急' },
]

const statusLabel = (s: string) => statusOptions.find(o => o.value === s)?.label || s
const urgencyLabel = (u: string) => urgencyOptions.find(o => o.value === u)?.label || u

const statusTagType = (s: string): TagType => {
  const map: Record<string, TagType> = {
    pending:     'danger',
    assigned:    'warning',
    in_progress: 'primary',
    completed:   'info',
    confirmed:   'success',
  }
  return map[s] || 'info'
}
const urgencyTagType = (u: string): TagType => {
  const map: Record<string, TagType> = {
    normal:   'info',
    urgent:   'warning',
    critical: 'danger',
  }
  return map[u] || 'info'
}

const formatDate = (s?: string) => {
  if (!s) return '-'
  const d = new Date(s)
  if (isNaN(d.getTime())) return s
  const pad = (n: number) => n.toString().padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const searchForm = ref({ keyword: '', status: '', urgency: '' })
const list = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const pagination = reactive({ page: 1, per_page: 15, total: 0 })
const technicianOptions = ref<Record<string, unknown>[]>([])

async function loadList(page = 1) {
  pagination.page = page
  loading.value = true
  try {
    const params: Record<string, unknown> = { page, per_page: pagination.per_page }
    if (searchForm.value.keyword)  params.keyword  = searchForm.value.keyword
    if (searchForm.value.status)   params.status   = searchForm.value.status
    if (searchForm.value.urgency)  params.urgency  = searchForm.value.urgency
    const res = await get('/service/orders', params)
    // V0.6.3: res = {code, data: paginator}
    const pag = unwrapPaginate(res)
    list.value = pag.list
    pagination.total = pag.total
    if (pag.current_page) pagination.page = pag.current_page
  } catch (e) {
    console.error('[loadList]', e)
    list.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

async function loadTechnicians() {
  try {
    const res = await get('/employees', { per_page: 200, is_active: true })
    // V0.6.3: res = {code, data: paginator}
    technicianOptions.value = unwrapList(res)
  } catch (e) {
    console.warn('[loadTechnicians]', e)
  }
}

function resetSearch() {
  searchForm.value = { keyword: '', status: '', urgency: '' }
  loadList(1)
}

function handleCreate() { router.push('/service/create') }

// ===== 派单 =====
const showDispatchDialog = ref(false)
const dispatchingRow = ref<Record<string, unknown> | null>(null)
const assignedUserId = ref<number | null>(null)
const dispatchLoading = ref(false)

function handleDispatch(row: Record<string, unknown>) {
  dispatchingRow.value = row
  assignedUserId.value = null
  showDispatchDialog.value = true
}

async function confirmDispatch() {
  if (!assignedUserId.value) {
    ElMessage.warning('请选择维修人员')
    return
  }
  dispatchLoading.value = true
  try {
    await post(`/service/orders/${dispatchingRow.value.id}/assign`, { assigned_to: assignedUserId.value })
    ElMessage.success(`工单 ${dispatchingRow.value.order_no} 派单成功`)
    showDispatchDialog.value = false
    loadList(pagination.page)
  } catch (e: unknown) {
    ElMessage.error(e?.response?.data?.message || e.message || '派单失败')
  } finally {
    dispatchLoading.value = false
  }
}

// ===== 开始维修 =====
async function handleStart(row: Record<string, unknown>) {
  await ElMessageBox.confirm(`确认开始维修工单 ${row.order_no}？`, '开始维修', { type: 'success' })
  try {
    await post(`/service/orders/${row.id}/start`, {})
    ElMessage.success('已开始维修')
    loadList(pagination.page)
  } catch (e: unknown) {
    ElMessage.error(e?.response?.data?.message || e.message || '操作失败')
  }
}

// ===== 完成维修 =====
async function handleComplete(row: Record<string, unknown>) {
  const { value } = await ElMessageBox.prompt('请输入维修内容（处理说明）', '完成维修', {
    type: 'success',
    confirmButtonText: '标记完成',
    inputPattern: /.+/,
    inputErrorMessage: '请输入维修内容',
    inputPlaceholder: '例如：更换硬盘阵列 RAID 控制器',
  })
  try {
    await post(`/service/orders/${row.id}/complete`, { repair_content: value, photos: [], parts: [] })
    ElMessage.success('已标记完成，待客户确认')
    loadList(pagination.page)
  } catch (e: unknown) {
    ElMessage.error(e?.response?.data?.message || e.message || '操作失败')
  }
}

// ===== 重新指派（已完成的工单） =====
async function handleReassign(row: Record<string, unknown>) {
  try {
    const { value: confirm } = await ElMessageBox.confirm(
      `确认将工单「${row.order_no}」重新指派给其他维修人员？\n原指派人：${row.assigned_user?.name || row.assignedUser?.name || '—'}\n完成时间：${row.completed_at || '—'}`,
      '重新指派',
      { type: 'warning', confirmButtonText: '继续指派' }
    ).catch(() => null)
    if (!confirm) return
    handleDispatch(row)
  } catch (e: unknown) {
    if (e === 'cancel' || e === 'close') return
    ElMessage.error(e?.response?.data?.message || e.message || '操作失败')
  }
}

onMounted(() => {
  loadList(1)
  loadTechnicians()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header {
  margin-bottom: 16px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
}
.filter-bar {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 16px; padding: 16px;
  background: #fff; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  flex-wrap: wrap;
}
.content-card {
  background: #fff; border-radius: 8px; padding: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.pagination-wrap {
  display: flex; justify-content: flex-end; margin-top: 16px;
}
.dispatch-info {
  background: #f5f7fa; border-radius: 8px; padding: 12px 16px;
  .dispatch-order {
    display: flex; gap: 8px; margin-bottom: 4px;
    &:last-child { margin-bottom: 0; }
    .label { color: #909399; white-space: nowrap; }
    .value { color: #303133; }
  }
}
.muted { color: #c0c4cc; }
</style>
