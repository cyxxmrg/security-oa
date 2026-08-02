<template>
  <div class="page-container">
    <div class="page-header">
      <div class="title-area">
        <span class="page-title">施工管理</span>
        <el-tag effect="light" type="info">{{ list.length }} 个项目</el-tag>
      </div>
      <div class="header-actions">
        <ScopeToggle @change="loadList" />
        <el-button :icon="DataLine" plain @click="$router.push('/project/board')">看板视图</el-button>
        <el-button :icon="Calendar" plain @click="$router.push('/project/calendar')">付款日历</el-button>
        <el-button :icon="Download" plain @click="handleExport">导出</el-button>
        <el-button type="primary" :icon="Plus" @click="showCreateDialog = true">新建项目</el-button>
      </div>
    </div>

    <div class="scope-tabs">
      <div class="tab-item" :class="{ active: true }">
        <el-icon :size="14"><Files /></el-icon>
        <span>施工管理</span>
        <el-tag size="small" type="info" effect="plain">{{ list.length }}</el-tag>
      </div>
      <div class="tab-item" @click="$router.push('/project/pool')">
        <el-icon :size="14"><Box /></el-icon>
        <span>项目池</span>
        <el-tag size="small" type="warning" effect="plain">待转</el-tag>
      </div>
      <div class="tab-divider">|</div>
      <div class="tab-hint">商机签约后先进项目池，分配项目经理后转为施工项目</div>
    </div>

    <ProjectFilterBar
      :form="searchForm"
      :customer-options="customerOptions"
      :stage-options="stageOptions"
      :status-options="statusOptions"
      @update:form=" (v: Record<string, unknown>) => Object.assign(searchForm, v)"
      @search="handleSearch"
      @reset="handleReset"
    />

    <div class="content-card">
      <div class="stats-row">
        <div class="stat-card" v-for="s in stageStats" :key="s.stage">
          <div class="stat-stage" :style="{ background: s.bg, color: s.color }">
            {{ s.stage }}
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ s.count }}</div>
            <div class="stat-label">当前阶段数</div>
          </div>
        </div>
      </div>

      <div class="alert-row" v-if="dashboardSummary.overdue > 0 || dashboardSummary.deadline_30_days > 0">
        <el-alert
          v-if="dashboardSummary.overdue > 0"
          type="error"
          :closable="false"
          show-icon
        >
          <template #title>
            共有 <strong>{{ dashboardSummary.overdue }}</strong> 个项目工期已超期，请优先处理
          </template>
        </el-alert>
        <el-alert
          v-if="dashboardSummary.deadline_30_days > 0"
          type="warning"
          :closable="false"
          show-icon
          style="margin-top: 8px"
        >
          <template #title>
            <strong>{{ dashboardSummary.deadline_30_days }}</strong> 个项目将在 30 天内到期
          </template>
        </el-alert>
      </div>

      <el-table
        :data="pagedList"
        stripe
        border
        v-loading="loading"
        :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
      >
        <el-table-column type="selection" width="50" />
        <el-table-column prop="code" label="项目编号" width="150" fixed>
          <template #default="{ row }">
            <span>{{ row.code }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="项目名称" min-width="240" fixed>
          <template #default="{ row }">
            <div class="project-name" @click="handleView(row)">
              <el-icon :size="16" :color="typeColor(row.type)">
                <component :is="typeIcon(row.type)" />
              </el-icon>
              <span class="link-text">{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="所属客户" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <span>{{ row.customer?.name || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="type" label="项目类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="typeTagType(row.type)" effect="light" size="small">{{ typeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="stage" label="当前阶段" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="stageTagType(row.stage)" effect="dark" size="small">{{ stageLabel(row.stage) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="progress" label="进度" width="180">
          <template #default="{ row }">
            <el-progress
              :percentage="row.progress || 0"
              :status="progressStatus(row)"
              :stroke-width="10"
              :format="(p) => p + '%'"
            />
          </template>
        </el-table-column>
        <el-table-column label="负责人" min-width="100">
          <template #default="{ row }">
            <span>{{ row.manager?.name || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="end_date" label="截止日期" width="120">
          <template #default="{ row }">
            <span>{{ row.end_date ? row.end_date.slice(0, 10) : '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="项目状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="plain" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleView(row)">查看</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </div>
  </div>

  <!-- 新建项目弹窗 (1200px 大尺寸弹窗) -->
  <el-dialog v-model="showCreateDialog" title="新建项目" width="1200px" :close-on-click-modal="false" destroy-on-close>
    <CreateProject v-if="showCreateDialog" embedded @done="onCreateDone" @cancel="showCreateDialog = false" />
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, Download, VideoCamera, Lock, Warning, SetUp, Connection, DataLine, Calendar, Files, Box } from '@element-plus/icons-vue'
import { get, post, put, del } from '@/utils/request'
import { unwrapList, unwrapPaginate, unwrapStats } from '@/utils/response'
import { exportExcelLike, printTable } from '@/utils/exporter'
import ProjectFilterBar from './components/ProjectFilterBar.vue'
import ScopeToggle from '@/components/ScopeToggle.vue'
import CreateProject from './Create.vue'

interface Project {
  id: number
  code?: string
  name: string
  type: string
  stage: string
  status: string
  progress: number
  customer?: { id: number; name: string }
  manager?: { id: number; name: string }
  end_date?: string
  start_date?: string
  budget_device?: number
  budget_material?: number
  budget_labor?: number
  budget_outsource?: number
  budget_other?: number
  description?: string
  priority?: string
}

interface Customer { id: number; name: string }

const router = useRouter()
const loading = ref(false)
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)

// 新建项目弹窗
const showCreateDialog = ref(false)
const onCreateDone = () => {
  showCreateDialog.value = false
  loadList()
}

const searchForm = reactive({ name: '', customer_id: null as number | null, stage: '', status: '' })

const list = ref<Project[]>([])
const customerOptions = ref<Customer[]>([])
const dashboardSummary = ref<Record<string, unknown>>({
  by_stage: [],
  in_progress: 0,
  completed: 0,
  overdue: 0,
  deadline_30_days: 0,
  total: 0,
})

const stageOptions = [
  { value: 'mobilization', label: '进场准备' },
  { value: 'construction', label: '现场施工' },
  { value: 'acceptance', label: '验收交付' },
  { value: 'settlement', label: '项目结算' },
  { value: 'warranty', label: '售后质保' },
  { value: 'closed', label: '已关闭' },
]

const statusOptions = [
  { value: 'pending', label: '未启动' },
  { value: 'in_progress', label: '进行中' },
  { value: 'suspended', label: '已暂停' },
  { value: 'completed', label: '已完工' },
]

const stageLabel = (s: string) => stageOptions.find(o => o.value === s)?.label || s
const statusLabel = (s: string) => statusOptions.find(o => o.value === s)?.label || s
const typeLabel = (t: string) => {
  // 项目类型 — 兼容 4 套常见值
  const map: Record<string, string> = {
    // 老 v0.3.x 中文拼音版
    'integrated': '综合安防', 'access': '门禁', 'monitor': '监控', 'alarm': '报警', 'network': '网络',
    'cloud': '云平台', 'comprehensive': '综合', 'other': '其他', 'maintain': '运维',
    // 文档版
    'camera': '监控', 'access_control': '门禁',
    'cloud_platform': '云平台',
    // 兼容更多
    'fire': '消防', 'patrol': '巡更', 'parking': '停车',
  }
  return map[t] || t || '-'
}

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: page.value, per_page: pageSize.value }
    if (searchForm.name) params.keyword = searchForm.name
    if (searchForm.customer_id) params.customer_id = searchForm.customer_id
    if (searchForm.stage) params.stage = searchForm.stage
    if (searchForm.status) params.status = searchForm.status
    const res = await get('/projects', params)
    // V0.6.3: res = {code, data: paginator}
    const pag = unwrapPaginate(res)
    list.value = pag.list
    total.value = pag.total || (pag as Record<string, unknown>).meta?.total || 0
  } catch (e) {
    console.error('加载项目列表失败', e)
    ElMessage.error('加载项目列表失败')
  } finally {
    loading.value = false
  }
}

const loadCustomers = async () => {
  try {
    const res = await get('/customers', { per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    customerOptions.value = unwrapList(res)
  } catch (e) {
    console.error('加载客户列表失败', e)
  }
}

const loadDashboardSummary = async () => {
  try {
    const res = await get('/projects/dashboard-summary')
    // V0.6.3: res = {code, data: <summary>}
    dashboardSummary.value = unwrapStats(res)
  } catch (e) {
    console.error('加载项目概览失败', e)
  }
}

onMounted(() => {
  loadList()
  loadCustomers()
  loadDashboardSummary()
})

const filteredList = computed(() => list.value)
const pagedList = computed(() => filteredList.value)

const stageStats = computed(() => {
  // 颜色映射表
  const colorMap: Record<string, { bg: string; color: string }> = {
    mobilization: { bg: 'rgba(12, 68, 124, 0.1)', color: '#0C447C' },
    construction: { bg: 'rgba(163, 45, 45, 0.1)', color: '#A32D2D' },
    acceptance: { bg: 'rgba(186, 117, 23, 0.1)', color: '#BA7517' },
    settlement: { bg: 'rgba(29, 158, 117, 0.1)', color: '#1D9E75' },
    warranty: { bg: 'rgba(29, 158, 117, 0.15)', color: '#1D9E75' },
    closed: { bg: 'rgba(144, 147, 153, 0.1)', color: '#909399' },
  }
  // 优先用 dashboard-summary 接口真实数据
  if (dashboardSummary.value?.by_stage?.length > 0) {
    return dashboardSummary.value.by_stage.map((s: Record<string, unknown>) => ({
      stage: s.label,
      count: s.count,
      bg: colorMap[s.value]?.bg || '#f5f7fa',
      color: colorMap[s.value]?.color || '#909399',
    }))
  }
  // 兜底：前端按当前列表统计
  return stageOptions.map(o => ({
    stage: o.label,
    count: list.value.filter(p => p.stage === o.value).length,
    bg: colorMap[o.value]?.bg || '#f5f7fa',
    color: colorMap[o.value]?.color || '#909399',
  }))
})

const typeIcon = (t: string) => {
  const map: Record<string, unknown> = {
    // 老版
    camera: VideoCamera, access_control: Lock, alarm: Warning, comprehensive: SetUp,
    // 数据库实际值
    monitor: VideoCamera, access: Lock, integrated: SetUp, network: Connection, cloud: Connection,
  }
  return map[t] || SetUp
}
const typeColor = (t: string) => {
  const map: Record<string, string> = {
    camera: '#0C447C', access_control: '#534AB7', alarm: '#A32D2D', comprehensive: '#1D9E75',
    monitor: '#0C447C', access: '#534AB7', integrated: '#1D9E75', network: '#378ADD', cloud: '#7fdbca',
  }
  return map[t] || '#909399'
}
const typeTagType = (t: string): 'primary' | 'info' | 'danger' | 'success' => {
  const map: Record<string, 'primary' | 'info' | 'danger' | 'success'> = {
    camera: 'primary', access_control: 'info', alarm: 'danger', comprehensive: 'success',
    monitor: 'primary', access: 'info', integrated: 'success', network: 'primary', cloud: 'info',
  }
  return map[t] || 'info'
}
const stageTagType = (s: string): 'primary' | 'info' | 'warning' | 'danger' | 'success' => {
  const map: Record<string, 'primary' | 'info' | 'warning' | 'danger' | 'success'> = {
    mobilization: 'primary', construction: 'danger',
    acceptance: 'warning', settlement: 'success',
    warranty: 'success', closed: 'info'
  }
  return map[s] || 'info'
}
const statusTagType = (s: string): 'success' | 'warning' | 'info' | 'danger' => {
  if (s === 'completed') return 'success'
  if (s === 'in_progress') return 'warning'
  if (s === 'suspended') return 'danger'
  return 'info'
}
const progressStatus = (row: Record<string, unknown>): '' | 'success' | 'warning' | 'exception' => {
  if (row.status === 'completed') return 'success'
  if (row.status === 'suspended') return 'exception'
  if ((row.progress || 0) >= 80) return 'success'
  if ((row.progress || 0) >= 50) return 'warning'
  return ''
}

const handleSearch = () => { page.value = 1; loadList() }
const handleReset = () => {
  searchForm.name = ''
  searchForm.customer_id = null
  searchForm.stage = ''
  searchForm.status = ''
  page.value = 1
  loadList()
}
const handleView = (row: Record<string, unknown>) => {
  router.push(`/project/detail/${row.id}`)
}

const handleDelete = async (row: Record<string, unknown>) => {
  try {
    await ElMessageBox.confirm(`确定要删除项目「${row.name}」吗？`, '删除确认', { type: 'warning' })
  } catch { return }
  try {
    await del(`/projects/${row.id}`)
    ElMessage.success('删除成功')
    loadList()
  } catch (e: unknown) {
    ElMessage.error(e?.message || '删除失败')
  }
}

const handleExport = () => {
  const headers = ['项目编号', '项目名称', '客户', '类型', '阶段', '进度', '负责人', '截止日期', '状态']
  const rows = filteredList.value.map((r: Record<string, unknown>) => [
    r.code || '-', r.name, r.customer?.name || '-', typeLabel(r.type),
    stageLabel(r.stage), (r.progress || 0) + '%', r.manager?.name || '-',
    r.end_date ? r.end_date.slice(0, 10) : '-', statusLabel(r.status),
  ])
  exportExcelLike(headers, rows, '项目列表', { title: '项目列表导出' })
}
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .title-area { display: flex; align-items: center; gap: 10px; }
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.scope-tabs {
  display: flex; align-items: center; gap: 4px;
  background: #fff; padding: 8px 16px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .tab-item {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px;
    color: #606266; transition: all 0.2s;
    &:hover { background: rgba(12, 68, 124, 0.04); color: #0C447C; }
    &.active { background: rgba(12, 68, 124, 0.08); color: #0C447C; font-weight: 600; }
  }
  .tab-divider { color: #dcdfe6; margin: 0 4px; }
  .tab-hint { font-size: 11px; color: #909399; margin-left: 8px; }
}
.filter-bar { background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.stats-row {
  display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-bottom: 20px;
  .stat-card {
    display: flex; align-items: center; padding: 12px; border-radius: 6px; background: #fff; border: 1px solid #ebeef5;
    transition: all 0.2s;
    &:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
    .stat-stage { padding: 6px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; margin-right: 10px; }
    .stat-info {
      .stat-value { font-size: 18px; font-weight: 600; color: #303133; line-height: 1.2; }
      .stat-label { font-size: 12px; color: #909399; }
    }
  }
}
.project-name { display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; padding: 2px 4px; border-radius: 3px; margin: -2px -4px; transition: background 0.15s; &:hover { background: rgba(12, 68, 124, 0.06); } }
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; &:hover { text-decoration: underline; } }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
