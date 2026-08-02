<template>
  <div class="page-container">
    <!-- 顶部标题 + 操作 -->
    <div class="page-header">
      <div class="title-area">
        <el-button :icon="ArrowLeft" text @click="$router.back()">返回项目列表</el-button>
        <span class="page-title">{{ project.name || '加载中...' }}</span>
        <el-tag :type="statusTagType(project.status)" effect="light">{{ statusLabel(project.status) }}</el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="Edit" @click="$router.push('/project/create')">编辑</el-button>
        <el-button :icon="Share" @click="handleShare">分享</el-button>
        <el-button :icon="Printer" @click="handlePrint">打印</el-button>
        <el-button type="primary" :icon="Document" @click="handleGenReport">导出报告</el-button>
      </div>
    </div>

    <!-- 顶部 overview 卡 (含风险 banner) -->
    <ProjectOverviewCard :project="project" :tracking="tracking" @risk-action="handleRiskAction" />

    <!-- Tabs 内容 -->
    <div class="content-card">
      <el-tabs v-model="activeTab" class="detail-tabs">
        <el-tab-pane label="项目信息" name="basic">
          <BasicInfoTab
            :project="project"
            :paid-amount="paidAmount"
            :contract-no="contractNo"
          />
        </el-tab-pane>

        <el-tab-pane label="阶段流程" name="stage">
          <StageFlowTab
            :project="project"
            :tracking="tracking"
            :display-progress="displayProgress"
            :paid-amount="paidAmount"
            :manager-name="managerName"
            @preview="handleDeliverablePreview"
            @download="handleDeliverableDownload"
          />
        </el-tab-pane>

        <el-tab-pane label="合同" name="contract">
          <ContractTab :project-id="projectId" />
        </el-tab-pane>

        <el-tab-pane label="采购入库" name="purchase">
          <PurchaseTab :project-id="projectId" :tracking="tracking" />
        </el-tab-pane>

        <el-tab-pane label="出库详情" name="outbound">
          <OutboundTab :project-id="projectId" />
        </el-tab-pane>

        <el-tab-pane label="施工日志" name="log">
          <ConstructionLogTab
            :logs="constructionLogs"
            @add="goDailyReport"
            @export="handleExportLogs"
            @view="handleViewLog"
          />
        </el-tab-pane>

        <el-tab-pane label="成本核算" name="cost">
          <CostTab
            :project="project"
            :material-stats="tracking.material_stats"
            :purchase-stats="tracking.purchase_stats"
            :total-contract="totalContract"
            :paid-amount="Number(paidAmount.replace(/,/g, '')) * 10000"
          />
        </el-tab-pane>

        <el-tab-pane label="付款日历" name="payment-calendar">
          <ProjectCalendar :project-id="projectId" embedded />
        </el-tab-pane>

        <el-tab-pane label="工序验收" name="process">
          <ProcessTab
            :instances="processInstances"
            :inspections="processInspections"
            :process-loading="processLoading"
            :project-id="projectId"
            @open-instance="goProcessInstance"
            @refresh="loadProcessData"
          />
        </el-tab-pane>

        <el-tab-pane label="施工进度" name="construction-progress">
          <ProjectConstructionProgressTab
            :process-instances="processInstances"
            :project-id="projectId"
          />
        </el-tab-pane>

        <!-- V0.4.8 C1: 跨页复用 Gantt.vue, 工序时间线嵌入项目详情 -->
        <el-tab-pane label="施工甘特图" name="gantt">
          <div class="gantt-tab">
            <div class="gantt-tab-header">
              <h3 style="margin: 0 0 16px 0">项目 #{{ projectId }} 工序时间线</h3>
              <el-alert
                title="甘特图数据来自工序实例, 跨项目看板见「施工 → 工序实例」"
                type="info"
                :closable="false"
                show-icon
                style="margin-bottom: 16px"
              />
              <ProjectGantt v-if="projectId" :id="Number(projectId)" mode="embedded" />
              <el-empty v-else description="项目 ID 缺失, 无法加载甘特图" />
            </div>
          </div>
        </el-tab-pane>

        <!-- V0.5.7 块1 — 售后记录 tab -->
        <el-tab-pane :label="`售后记录 (${(maintenanceData?.stats?.work_order_count || 0) + (maintenanceData?.stats?.repair_order_count || 0)})`" name="maintenance">
          <ProjectMaintenanceTab :project-id="projectId" :active-tab="activeTab" @loaded="maintenanceData = $event" />
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- 施工日志详情 dialog -->
    <LogDetailDialog v-model:visible="showLogDetailDialog" :log="currentLog" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { printTable, exportExcelLike } from '@/utils/exporter'
import {
  ArrowLeft, Edit, Share, Printer, Document,
} from '@element-plus/icons-vue'
import BasicInfoTab from './components/BasicInfoTab.vue'
import StageFlowTab from './components/StageFlowTab.vue'
import ContractTab from './components/ContractTab.vue'
import PurchaseTab from './components/PurchaseTab.vue'
import OutboundTab from './components/OutboundTab.vue'
import ConstructionLogTab from './components/ConstructionLogTab.vue'
import CostTab from './components/CostTab.vue'
import ProcessTab from './components/ProcessTab.vue'
import ProjectGantt from './Gantt.vue'
import LogDetailDialog from './components/detail/LogDetailDialog.vue'
import ProjectOverviewCard from './components/ProjectOverviewCard.vue'
import ProjectMaintenanceTab from './components/ProjectMaintenanceTab.vue'
import ProjectConstructionProgressTab from './components/ProjectConstructionProgressTab.vue'
import ProjectCalendar from './Calendar.vue'
import { useProjectDetail } from '@/composables/useProjectDetail'
import {
  type TagType, type Risk,
  getManagerName, statusLabel,
  RISK_ACTION_MAP,
} from './types'

const route = useRoute()
const router = useRouter()
const projectId = computed(() => Number(route.params.id))

const activeTab = ref('stage')
const showLogDetailDialog = ref(false)
const currentLog = ref<Record<string, unknown> | null>(null)

const {
  loading, project, tracking, constructionLogs,
  processInstances, processInspections, processLoading,
  loadProject, loadTracking, loadLogs, loadProcessData,
} = useProjectDetail(() => projectId.value)

// ============== 计算属性 (传给各 tab 子组件) ==============
const managerName = computed(() => getManagerName(project.value))
const paidAmount = computed(() => (Number(tracking.value.payment?.paid_amount) / 10000 || 0).toFixed(2))
const displayProgress = computed(() => Number(tracking.value.display_progress) || Number(project.value.progress) || 0)
const totalContract = computed(() => Number(tracking.value.payment?.contract_amount) || 0)
const risks = computed(() => tracking.value.risks || [])

const statusTagType = (s?: string): TagType => {
  if (s === 'completed') return 'success'
  if (s === 'in_progress') return 'warning'
  if (s === 'suspended') return 'danger'
  return 'info'
}

// ============== 行为 ==============
const goDailyReport = () => {
  router.push('/construction/log/daily')
}

const handleViewLog = (row: Record<string, unknown>) => {
  currentLog.value = row
  showLogDetailDialog.value = true
}

const handleRiskAction = (risk: Risk) => {
  const m = RISK_ACTION_MAP[risk.type] || { tab: 'stage', msg: '请查看详情' }
  ElMessage.warning(m.msg)
  activeTab.value = m.tab
}

const handleDeliverableDownload = (row: Record<string, unknown>) => {
  // 模拟下载: 走通用下载通道
  if (row.url || row.file_url) {
    const a = document.createElement('a')
    a.href = row.url || row.file_url
    a.download = row.name || 'download'
    a.click()
  } else {
    ElMessage.info(`交付物「${row.name}」暂未上传文件`)
  }
}
const handleDeliverablePreview = (row: Record<string, unknown>) => ElMessage.info(`预览功能开发中：${row.name}`)
const handleExportLogs = () => {
  // 施工日志 tab 的日志
  const logs = (logList && logList.value && logList.value.length) ? logList.value : []
  if (logs.length === 0) {
    ElMessage.warning('暂无施工日志可导出')
    return
  }
  const headers = ['日期', '进度', '工时', '施工内容', '天气', '人员', '状态']
  const rows = logs.map((l: Record<string, unknown>) => [
    l.work_date || l.date || '-',
    (l.progress || 0) + '%',
    l.work_hours || 0,
    l.content || '-',
    l.weather || '-',
    l.workers || '-',
    l.status || '-',
  ])
  exportExcelLike(headers, rows, '施工日志', { title: '项目施工日志' })
}
const handlePrint = () => {
  // 整页打印: 走打印通道, 先隐藏不需要的元素
  const css = document.createElement('style')
  css.id = '__print_hide__'
  css.textContent = `
    @page { size: A4 portrait; margin: 1.5cm; }
    body { background: #fff !important; }
    .el-header, .el-aside, .sidebar, .nav, .page-actions, .toolbar, .no-print, .el-tabs__nav-wrap, .el-tabs__header { display: none !important; }
  `
  document.head.appendChild(css)
  window.print()
  setTimeout(() => css.remove(), 500)
}
const handleShare = async () => {
  if (!project.value) {
    ElMessage.warning('项目未加载')
    return
  }
  const url = `${window.location.origin}/project/detail/${project.value.id}`
  try {
    await navigator.clipboard.writeText(url)
    ElMessage.success('项目详情链接已复制到剪贴板')
  } catch {
    // 降级: 弹窗让用户手动复制
    ElMessageBox.prompt('复制以下链接', '分享项目', { inputValue: url, confirmButtonText: '关闭' })
      .catch(() => {})
  }
}
const goProcessInstance = (id: number | string) => {
  if (!id) {
    ElMessage.warning('缺少工序实例 ID')
    return
  }
  router.push(`/construction/process/instances/detail/${id}`)
}

// 导出项目跟踪报告 — 走 printTable (统一通道)
const handleGenReport = async () => {
  if (!project.value || !project.value.id) {
    ElMessage.warning('项目未加载完成')
    return
  }
  const p = project.value
  const headers = ['字段', '内容']
  const rows = [
    ['项目编号', p.code || '-'],
    ['项目名称', p.name || '-'],
    ['客户', p.customer?.name || '-'],
    ['项目类型', p.type || '-'],
    ['当前阶段', p.stage || '-'],
    ['项目进度', (p.progress || 0) + '%'],
    ['负责人', p.manager?.name || '-'],
    ['开始日期', p.start_date?.slice(0, 10) || '-'],
    ['截止日期', p.end_date?.slice(0, 10) || '-'],
    ['合同金额', p.contract_amount ? '¥' + Number(p.contract_amount).toFixed(2) : '-'],
    ['项目状态', p.status || '-'],
    ['项目描述', p.description || '-'],
  ]
  printTable(`项目跟踪报告 - ${p.name || p.code || ''}`, headers, rows, { orientation: 'portrait' })
}

// 售后 tab 标签计数引用 (实际数据由 ProjectMaintenanceTab 内部管理,
// 此处仅用于 tab 标题显示; 首次为 0, 切换后由子组件加载)
const maintenanceData = ref<Record<string, unknown> | null>(null)

onMounted(() => {
  loadProject()
  loadTracking()
  loadLogs()
  loadProcessData()
})
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 16px 20px;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.title-area {
  display: flex;
  align-items: center;
  gap: 12px;
}
.page-title {
  font-size: 20px;
  font-weight: 700;
  color: #303133;
}
.header-actions {
  display: flex;
  gap: 8px;
}

.content-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.detail-tabs {
  :deep(.el-tabs__header) {
    margin-bottom: 16px;
  }
}
.gantt-tab-header {
  padding: 16px 0;
}
</style>
