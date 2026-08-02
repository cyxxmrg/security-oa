<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">维修工单</span>
      <div class="header-actions">
        <el-tag type="info" size="large">共 {{ total }} 单</el-tag>
        <el-button type="primary" :icon="Plus" @click="goCreate" style="margin-left: 8px;">
          <span class="hide-mobile">新建工单</span>
          <el-icon class="show-mobile"><Plus /></el-icon>
        </el-button>
      </div>
    </div>

    <!-- 移动卡片 / PC 表格 共用数据源 -->
    <div class="content-card">
      <div class="filter-bar">
        <el-input
          v-model="keyword"
          placeholder="搜索工单号/客户/描述"
          clearable
          :prefix-icon="Search"
          style="width: 240px"
          @input="debouncedSearch"
        />
        <el-select v-model="filterStatus" placeholder="状态" clearable style="width: 140px" @change="loadData">
          <el-option v-for="s in STATUS_OPTIONS" :key="s.value" :label="s.label" :value="s.value">
            <el-tag :type="s.color" size="small" effect="dark">{{ s.label }}</el-tag>
          </el-option>
        </el-select>
        <el-select v-model="filterPriority" placeholder="优先级" clearable style="width: 120px" @change="loadData">
          <el-option v-for="p in PRIORITY_OPTIONS" :key="p.value" :label="p.label" :value="p.value">
            <el-tag :type="p.color" size="small" effect="plain">{{ p.label }}</el-tag>
          </el-option>
        </el-select>
        <el-button :icon="Refresh" @click="loadData">刷新</el-button>
      </div>

      <!-- 看板视图 (4 状态分列) -->
      <div class="board-row" v-if="viewMode === 'board'">
        <div v-for="col in boardColumns" :key="col.value" class="board-col">
          <div class="board-col-header" :class="`col-${col.value}`">
            <el-icon><component :is="col.icon" /></el-icon>
            <span>{{ col.label }}</span>
            <el-tag size="small" effect="dark" round>{{ col.items.length }}</el-tag>
          </div>
          <div class="board-col-body">
            <div
              v-for="wo in col.items"
              :key="wo.id"
              class="board-card"
              @click="goDetail(wo.id)"
            >
              <div class="board-card-head">
                <code class="wo-code">{{ wo.code }}</code>
                <el-tag :type="wo.priority_color" size="small" effect="plain">{{ wo.priority_label }}</el-tag>
              </div>
              <div class="board-card-body">
                <div class="fault">{{ wo.fault_description }}</div>
                <div class="meta">
                  <span><el-icon><User /></el-icon> {{ wo.contact_name || wo.customer_name || '—' }}</span>
                  <span v-if="wo.assignee_name"><el-icon><Avatar /></el-icon> {{ wo.assignee_name }}</span>
                </div>
              </div>
              <div class="board-card-foot">
                <span class="time">{{ timeAgo(wo.created_at) }}</span>
                <el-icon v-if="wo.is_locked" class="locked"><Lock /></el-icon>
              </div>
            </div>
            <div v-if="!col.items.length" class="board-empty">无</div>
          </div>
        </div>
      </div>

      <!-- 列表视图 (PC: 表格 / 移动: 卡片) -->
      <div v-else>
        <!-- 表格 (仅 PC) -->
        <el-table :data="filteredWOs" border stripe v-loading="loading" class="pc-only" style="width: 100%;">
          <el-table-column prop="code" label="工单号" width="140">
            <template #default="{ row }">
              <code class="link" @click="goDetail(row.id)">{{ row.code }}</code>
            </template>
          </el-table-column>
          <el-table-column label="客户" min-width="140">
            <template #default="{ row }">
              {{ row.customer_name || row.contact_name || '—' }}
              <div class="muted" v-if="row.contact_phone">{{ row.contact_phone }}</div>
            </template>
          </el-table-column>
          <el-table-column label="故障" min-width="220" show-overflow-tooltip>
            <template #default="{ row }">
              <div>{{ row.equipment_brand }} {{ row.equipment_model }}</div>
              <div class="muted">{{ row.fault_description }}</div>
            </template>
          </el-table-column>
          <el-table-column label="优先级" width="80">
            <template #default="{ row }">
              <el-tag :type="row.priority_color" size="small" effect="dark">{{ row.priority_label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status_color" size="small" effect="dark">{{ row.status_label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="工程师" width="100">
            <template #default="{ row }">
              <span v-if="row.assignee_name">{{ row.assignee_name }}</span>
              <span v-else class="muted">未派</span>
            </template>
          </el-table-column>
          <el-table-column label="创建" width="120">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
          <el-table-column label="操作" width="220" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="openView(row.id)">查看</el-button>
              <el-button v-if="row.status === 'pending' && !row.is_locked" link type="warning" size="small" @click="openDispatch(row)">派单</el-button>
              <el-button v-else-if="row.status === 'assigned' && !row.is_locked" link type="success" size="small" @click="startWork(row)">施工</el-button>
              <el-button v-else-if="row.status === 'in_progress' && !row.is_locked" link type="primary" size="small" @click="openResolve(row)">完成</el-button>
            </template>
          </el-table-column>
        </el-table>

        <!-- 卡片列表 (仅移动) -->
        <div class="mobile-cards">
          <div
            v-for="wo in filteredWOs"
            :key="wo.id"
            class="mobile-card"
            @click="goDetail(wo.id)"
          >
            <div class="card-row1">
              <code class="wo-code">{{ wo.code }}</code>
              <el-tag :type="wo.status_color" size="small" effect="dark">{{ wo.status_label }}</el-tag>
              <el-tag :type="wo.priority_color" size="small" effect="plain">{{ wo.priority_label }}</el-tag>
            </div>
            <div class="card-row2">
              <el-icon><User /></el-icon>
              {{ wo.customer_name || wo.contact_name || '—' }}
            </div>
            <div class="card-row3">
              <span v-if="wo.equipment_brand">{{ wo.equipment_brand }} {{ wo.equipment_model }}</span>
              <span v-else>{{ wo.fault_description }}</span>
            </div>
            <div class="card-row4">
              <span><el-icon><Avatar /></el-icon> {{ wo.assignee_name || '未派' }}</span>
              <span class="time">{{ timeAgo(wo.created_at) }}</span>
            </div>
          </div>
          <el-empty v-if="!filteredWOs.length && !loading" description="无工单" />
        </div>

        <div class="pagination-wrapper">
          <el-pagination
            v-model:current-page="page"
            v-model:page-size="perPage"
            :page-sizes="[10, 20, 50, 100]"
            :total="total"
            layout="total, sizes, prev, pager, next, jumper"
            @current-change="loadData"
            @size-change="loadData"
          />
        </div>
      </div>
    </div>
  </div>
  <CreateWorkOrderDialog v-model="showCreate" @done="loadData" />
  <WorkOrderViewDialog v-model="showView" :wo="viewWO" @done="loadData" @resolve="onViewDialogResolve" />
  <DispatchDialog v-model="showDispatch" :wo="dispatchWO" @done="loadData" />
  <ResolveWorkOrderDialog v-model="showResolve" :wo="resolveWO" @done="loadData" />
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Search, Refresh, Plus, User, Avatar, Lock, Clock, Tools, Warning, Promotion, CircleCheck, RemoveFilled, RefreshLeft, ChatLineRound } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { unwrapPaginate } from '@/utils/response'
import CreateWorkOrderDialog from './components/CreateWorkOrderDialog.vue'
import WorkOrderViewDialog from './components/WorkOrderViewDialog.vue'
import DispatchDialog from './components/DispatchDialog.vue'
import ResolveWorkOrderDialog from './components/ResolveWorkOrderDialog.vue'

const router = useRouter()

const STATUS_OPTIONS = [
  { value: 'pending', label: '待派单', color: 'info' },
  { value: 'assigned', label: '已派单', color: 'primary' },
  { value: 'in_progress', label: '进行中', color: 'warning' },
  { value: 'resolved', label: '已解决', color: 'success' },
  { value: 'cancelled', label: '已取消', color: 'info' },
  { value: 'converted_to_repair', label: '已转返修', color: 'danger' },
]
const PRIORITY_OPTIONS = [
  { value: 'low', label: '低', color: 'info' },
  { value: 'medium', label: '中', color: 'primary' },
  { value: 'high', label: '高', color: 'warning' },
  { value: 'urgent', label: '紧急', color: 'danger' },
]

const boardColumns = computed(() => {
  const groups: Record<string, Record<string, unknown>[]> = {
    pending: [],
    assigned: [],
    in_progress: [],
    resolved: [],
  }
  list.value.forEach((wo: Record<string, unknown>) => {
    if (groups[wo.status]) groups[wo.status].push(wo)
  })
  return [
    { value: 'pending', label: '待派单', icon: Clock, items: groups.pending, color: 'info' },
    { value: 'assigned', label: '已派单', icon: Promotion, items: groups.assigned, color: 'primary' },
    { value: 'in_progress', label: '进行中', icon: Tools, items: groups.in_progress, color: 'warning' },
    { value: 'resolved', label: '已完成', icon: CircleCheck, items: groups.resolved, color: 'success' },
  ]
})

const list = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const keyword = ref('')
const filterStatus = ref('')
const filterPriority = ref('')
const viewMode = ref<'list' | 'board'>('list')

let debounceTimer: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; loadData() }, 300)
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await get('/work-orders', {
      page: page.value,
      per_page: perPage.value,
      keyword: keyword.value,
      status: filterStatus.value,
      priority: filterPriority.value,
    })
    // V0.6.3 不再解包, 兼容两种形态: {code,data:{data,total}} 或直接 {data,total}
    const { list: l, total: t } = unwrapPaginate(res)
    list.value = l
    total.value = t
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + (e?.message || ''))
  } finally {
    loading.value = false
  }
}

const filteredWOs = computed(() => list.value)  // 后端已过滤

const goCreate = () => { showCreate.value = true }
const goDetail = (id: number) => router.push(`/maintenance/work-orders/${id}`)

const showCreate = ref(false)
const showView = ref(false)
const showDispatch = ref(false)
const viewWO = ref<Record<string, unknown> | null>(null)
const dispatchWO = ref<Record<string, unknown> | null>(null)
const resolveWO = ref<Record<string, unknown> | null>(null)
const showResolve = ref(false)

const openView = async (id: number) => {
  try {
    const res = await get(`/work-orders/${id}`)
    viewWO.value = (res as { data?: Record<string, unknown> })?.data || (res as Record<string, unknown>)
    showView.value = true
  } catch { ElMessage.error('加载工单详情失败') }
}
const openDispatch = (row: Record<string, unknown>) => {
  dispatchWO.value = row
  showDispatch.value = true
}
const openResolve = (row: Record<string, unknown>) => {
  resolveWO.value = row
  showResolve.value = true
}
const startWork = async (row: Record<string, unknown>) => {
  try {
    await post(`/work-orders/${row.id}/start`)
    ElMessage.success('已开始施工')
    loadData()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '操作失败')
  }
}

const onViewDialogResolve = (id: number) => {
  const wo = list.value.find((w: Record<string, unknown>) => Number(w.id) === id)
  if (wo) { resolveWO.value = wo; showResolve.value = true }
}

const formatTime = (s: string) => {
  if (!s) return ''
  const d = new Date(s)
  return `${d.getMonth()+1}/${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

const timeAgo = (s: string) => {
  if (!s) return ''
  const ms = Date.now() - new Date(s).getTime()
  const min = Math.floor(ms / 60000)
  if (min < 1) return '刚刚'
  if (min < 60) return `${min} 分钟前`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `${hr} 小时前`
  const day = Math.floor(hr / 24)
  return `${day} 天前`
}

// 移动端检测 (简单粗暴 — 768px 断点)
const isMobile = ref(false)
const onResize = () => {
  isMobile.value = window.innerWidth < 768
  viewMode.value = isMobile.value ? 'list' : 'list'  // 列表永远可见, board 仅 PC
}

onMounted(() => {
  loadData()
  onResize()
  window.addEventListener('resize', onResize)
})
onUnmounted(() => window.removeEventListener('resize', onResize))
</script>

<style scoped lang="scss">
.page-container { padding: 20px; }
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.page-title { font-size: 20px; font-weight: 600; }
.header-actions { display: flex; align-items: center; gap: 8px; }
.content-card {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.filter-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

// 看板
.board-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  min-height: 400px;
}
.board-col {
  background: #f5f7fa;
  border-radius: 8px;
  padding: 8px;
  display: flex;
  flex-direction: column;
}
.board-col-header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px;
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 8px;
  &.col-pending { color: #909399; }
  &.col-assigned { color: #409EFF; }
  &.col-in_progress { color: #E6A23C; }
  &.col-resolved { color: #67C23A; }
}
.board-col-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
  overflow-y: auto;
  max-height: 600px;
}
.board-card {
  background: white;
  border-radius: 6px;
  padding: 10px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
  cursor: pointer;
  transition: all 0.2s;
  border-left: 3px solid #409EFF;
  &:hover { transform: translateX(2px); box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
}
.board-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.board-card-body .fault {
  font-size: 13px;
  color: #303133;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.board-card-body .meta {
  display: flex;
  gap: 12px;
  font-size: 11px;
  color: #909399;
  margin-top: 4px;
  span { display: flex; align-items: center; gap: 2px; }
}
.board-card-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 6px;
  font-size: 11px;
  color: #909399;
  .locked { color: #F56C6C; }
}
.wo-code { font-size: 12px; color: #409EFF; cursor: pointer; }
.board-empty {
  text-align: center;
  color: #c0c4cc;
  font-size: 12px;
  padding: 20px;
}

// 表格 (PC)
.pc-only { display: block; }
.mobile-cards { display: none; }
.muted { font-size: 12px; color: #909399; }
.link { color: #409EFF; cursor: pointer; text-decoration: underline; }
.pagination-wrapper {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

// 卡片 (移动)
.mobile-card {
  display: none;
  background: #fafbfc;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 8px;
  border: 1px solid #ebeef5;
  cursor: pointer;
  &:active { background: #f0f2f5; }
}
.card-row1 { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.card-row2 { font-size: 13px; color: #303133; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; }
.card-row3 { font-size: 13px; color: #606266; margin-bottom: 4px; }
.card-row4 { display: flex; justify-content: space-between; font-size: 11px; color: #909399; }
.card-row4 span { display: flex; align-items: center; gap: 4px; }
.card-row4 .time { }

// 响应式
@media (max-width: 768px) {
  .page-container { padding: 12px; }
  .pc-only { display: none; }
  .mobile-cards { display: block; }
  .mobile-card { display: block; }
  .hide-mobile { display: none; }
  .board-row { display: none; }
  .filter-bar { gap: 8px; }
  .filter-bar .el-input, .filter-bar .el-select { flex: 1; min-width: 100px; }
  .pagination-wrapper { justify-content: center; }
}
</style>
