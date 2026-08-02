<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">总览看板</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="loadAll">刷新</el-button>
        <span style="color: #909399; font-size: 12px; margin-left: 12px">
          <el-icon><Clock /></el-icon>
          缓存 5 分钟 · {{ generatedAt }}
        </span>
      </div>
    </div>

    <div v-loading="loading" v-if="overview">
      <!-- 1. KPI 头条 -->
      <div class="kpi-row">
        <el-card v-for="kpi in kpiCards" :key="kpi.label" shadow="hover" :body-style="{ padding: '18px' }" class="kpi-card">
          <div style="display: flex; align-items: center; gap: 12px">
            <div :style="{ background: kpi.bg, color: kpi.color, width: '44px', height: '44px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center' }">
              <el-icon :size="24"><component :is="kpi.icon" /></el-icon>
            </div>
            <div>
              <div style="color: #909399; font-size: 13px">{{ kpi.label }}</div>
              <div :style="{ color: kpi.color, fontSize: '24px', fontWeight: 700, lineHeight: '32px' }">
                {{ kpi.value }}
                <span v-if="kpi.unit" style="font-size: 13px; color: #909399; font-weight: 400; margin-left: 4px">{{ kpi.unit }}</span>
              </div>
            </div>
          </div>
        </el-card>
      </div>

      <!-- 2. 项目阶段 + 质保总览 -->
      <el-row :gutter="16" style="margin-bottom: 16px">
        <el-col :xs="24" :md="12">
          <el-card shadow="hover" style="height: 100%">
            <template #header><span style="font-weight: 600">项目阶段分布</span></template>
            <div v-if="overview.project_stage_distribution" class="stage-grid">
              <div v-for="(count, stage) in overview.project_stage_distribution" :key="stage" class="stage-item" :style="{ borderColor: stageColor(stage) }">
                <div :style="{ color: stageColor(stage), fontSize: '22px', fontWeight: 700 }">{{ count }}</div>
                <div style="color: #606266; font-size: 13px; margin-top: 4px">{{ stageLabel(stage) }}</div>
              </div>
            </div>
          </el-card>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-card shadow="hover" style="height: 100%">
            <template #header>
              <div style="display: flex; justify-content: space-between; align-items: center">
                <span style="font-weight: 600">质保期总览</span>
                <el-link type="primary" :underline="false" @click="$router.push('/project/warranty/list')">查看全部 →</el-link>
              </div>
            </template>
            <div v-if="overview.warranty_summary" class="warranty-summary">
              <div class="warranty-row" v-for="(item, idx) in warrantyStatusList" :key="idx">
                <el-tag :type="item.tagType" effect="plain">{{ item.label }}</el-tag>
                <div style="flex: 1; margin-left: 12px">
                  <el-progress
                    :percentage="calcWarrantyPercent(item.value)"
                    :color="item.color"
                    :stroke-width="10"
                    :show-text="false"
                  />
                </div>
                <div style="font-weight: 600; min-width: 40px; text-align: right">{{ item.value }}</div>
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 3. 施工健康度 + 财务速览 -->
      <el-row :gutter="16" style="margin-bottom: 16px">
        <el-col :xs="24" :md="12">
          <el-card shadow="hover" style="height: 100%">
            <template #header><span style="font-weight: 600">施工健康度</span></template>
            <div v-if="overview.construction_health" class="health-grid">
              <div class="health-item">
                <el-icon :size="28" color="#67C23A"><UserFilled /></el-icon>
                <div>
                  <div class="health-num">{{ overview.construction_health.active_teams || 0 }}</div>
                  <div class="health-label">活跃团队</div>
                </div>
              </div>
              <div class="health-item">
                <el-icon :size="28" color="#409EFF"><Operation /></el-icon>
                <div>
                  <div class="health-num">{{ overview.construction_health.ongoing_processes || 0 }}</div>
                  <div class="health-label">在施工序</div>
                </div>
              </div>
              <div class="health-item">
                <el-icon :size="28" color="#E6A23C"><Warning /></el-icon>
                <div>
                  <div class="health-num">{{ overview.construction_health.pending_rectifications || 0 }}</div>
                  <div class="health-label">待整改</div>
                </div>
              </div>
              <div class="health-item">
                <el-icon :size="28" color="#F56C6C"><Box /></el-icon>
                <div>
                  <div class="health-num">{{ overview.construction_health.open_external_works || 0 }}</div>
                  <div class="health-label">外发包</div>
                </div>
              </div>
            </div>
          </el-card>
        </el-col>
        <el-col :xs="24" :md="12">
          <el-card shadow="hover" style="height: 100%">
            <template #header><span style="font-weight: 600">财务速览</span></template>
            <div v-if="overview.finance_snapshot" class="finance-grid">
              <div class="finance-item finance-up">
                <div class="finance-label">本月已收</div>
                <div class="finance-amount">¥ {{ fmt(overview.finance_snapshot.monthly_received) }}</div>
              </div>
              <div class="finance-item finance-down">
                <div class="finance-label">本月应付</div>
                <div class="finance-amount">¥ {{ fmt(overview.finance_snapshot.monthly_payable) }}</div>
              </div>
              <div class="finance-item">
                <div class="finance-label">应收未收</div>
                <div class="finance-amount">¥ {{ fmt(overview.finance_snapshot.outstanding_receivable) }}</div>
              </div>
              <div class="finance-item">
                <div class="finance-label">应付未付</div>
                <div class="finance-amount">¥ {{ fmt(overview.finance_snapshot.outstanding_payable) }}</div>
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 4. 审批待办 + 设备状态 -->
      <el-row :gutter="16" style="margin-bottom: 16px">
        <el-col :xs="24" :md="14">
          <el-card shadow="hover">
            <template #header>
              <div style="display: flex; justify-content: space-between; align-items: center">
                <span style="font-weight: 600">审批待办 (14 类)</span>
                <el-tag type="warning" effect="plain">总计 {{ overview.approval_todo?.total_pending || 0 }}</el-tag>
              </div>
            </template>
            <div v-if="overview.approval_todo?.by_type" class="approval-grid">
              <div v-for="(count, type) in overview.approval_todo.by_type" :key="type" class="approval-item">
                <div class="approval-type">{{ approvalTypeLabel(String(type)) }}</div>
                <div :class="['approval-count', count > 0 ? 'has-pending' : '']">{{ count }}</div>
              </div>
            </div>
          </el-card>
        </el-col>
        <el-col :xs="24" :md="10">
          <el-card shadow="hover">
            <template #header><span style="font-weight: 600">设备状态</span></template>
            <div v-if="overview.device_status" class="device-grid">
              <div v-for="(count, status) in overview.device_status" :key="status" class="device-item">
                <el-icon :size="20" :color="deviceColor(status)"><Monitor /></el-icon>
                <div class="device-count">{{ count }}</div>
                <div class="device-label">{{ deviceStatusLabel(String(status)) }}</div>
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <!-- V0.4.8 C2: 项目阶段分布饼图 + 月度营收趋势双 Y 轴图 -->
      <el-row :gutter="16" style="margin-bottom: 16px">
        <el-col :xs="24" :md="10">
          <el-card shadow="hover" style="height: 100%">
            <template #header><span style="font-weight: 600">项目阶段分布</span></template>
            <div v-if="projectStageDist.length > 0" ref="pieChartEl" class="pie-chart"></div>
            <el-empty v-else description="暂无项目数据" :image-size="60" />
          </el-card>
        </el-col>
        <el-col :xs="24" :md="14">
          <el-card shadow="hover" style="height: 100%">
            <template #header>
              <div style="display: flex; justify-content: space-between; align-items: center">
                <span style="font-weight: 600">近 6 月营收 vs 支出</span>
                <el-tag effect="plain" size="small">单位: 万元</el-tag>
              </div>
            </template>
            <div v-if="monthlyRevenue.length > 0" ref="trendChartEl" class="trend-chart"></div>
            <el-empty v-else description="暂无营收数据" :image-size="60" />
          </el-card>
        </el-col>
      </el-row>

      <!-- 5. 即将到期质保期 (前 10) -->
      <el-card v-if="overview.warranty_summary?.expiring_soon?.length" shadow="hover" style="margin-bottom: 16px">
        <template #header>
          <div style="display: flex; justify-content: space-between; align-items: center">
            <span style="font-weight: 600">⏰ 即将到期质保期 (30 天内)</span>
            <el-link type="primary" :underline="false" @click="$router.push('/project/warranty/expiring')">查看全部 →</el-link>
          </div>
        </template>
        <el-table :data="overview.warranty_summary.expiring_soon" stripe size="small">
          <el-table-column prop="warranty_no" label="质保编号" width="160" />
          <el-table-column label="客户" min-width="120">
            <template #default="{ row }">{{ row.customer_name || row.customer?.name || '-' }}</template>
          </el-table-column>
          <el-table-column prop="end_date" label="到期日期" width="120" />
          <el-table-column label="剩余天数" width="120" align="center">
            <template #default="{ row }">
              <el-tag :type="row.days_left <= 7 ? 'danger' : (row.days_left <= 30 ? 'warning' : 'info')" effect="plain" size="small">
                {{ row.days_left }} 天
              </el-tag>
            </template>
          </el-table-column>
        </el-table>
      </el-card>

      <!-- 6. 24h 实时活动流 -->
      <el-card shadow="hover">
        <template #header><span style="font-weight: 600">🔄 实时活动流 (最新 20 条)</span></template>
        <el-timeline v-if="overview.recent_activity?.length">
          <el-timeline-item
            v-for="(item, idx) in overview.recent_activity"
            :key="idx"
            :timestamp="item.time"
            :type="activityTypeColor(item.type)"
            placement="top"
          >
            <el-tag size="small" :type="activityTypeColor(item.type)" effect="plain">{{ activityTypeLabel(item.type) }}</el-tag>
            <span style="margin-left: 8px">{{ item.title }}</span>
            <el-link v-if="item.link" type="primary" :underline="false" :href="item.link" style="margin-left: 8px">查看</el-link>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-else description="暂无活动" :image-size="80" />
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick, onUnmounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Clock, UserFilled, Operation, Warning, Box, Monitor } from '@element-plus/icons-vue'
import { getOverview, getWarrantyStats } from '@/api/dashboard'
import { unwrapStats } from '@/utils/response'
import * as echarts from 'echarts/core'
import { PieChart, BarChart, LineChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, LegendComponent, GridComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([PieChart, BarChart, LineChart, TitleComponent, TooltipComponent, LegendComponent, GridComponent, CanvasRenderer])

const loading = ref(false)
const overview = ref<Record<string, unknown> | null>(null)
const generatedAt = ref('')

const kpiCards = computed(() => {
  if (!overview.value?.kpi) return []
  const k = overview.value.kpi
  return [
    { label: '在建项目', value: k.active_projects || 0, unit: '/ ' + (k.total_projects || 0) + ' 总', color: '#409EFF', bg: '#ecf5ff', icon: Operation },
    { label: '在保质保', value: k.warranty_active || 0, color: '#67C23A', bg: '#f0f9ff', icon: Monitor },
    { label: '即将到期 (30天)', value: k.warranty_expiring_30 || 0, color: '#E6A23C', bg: '#fdf6ec', icon: Clock },
    { label: '已过期', value: k.warranty_expired || 0, color: '#F56C6C', bg: '#fef0f0', icon: Warning },
    { label: '待审批', value: k.pending_approvals || 0, color: '#909399', bg: '#f4f4f5', icon: Box },
    { label: '本月已收', value: '¥' + fmt(k.monthly_revenue), color: '#67C23A', bg: '#f0f9ff', icon: UserFilled },
  ]
})

const warrantyStatusList = computed(() => {
  const s = overview.value?.warranty_summary?.by_status
  if (!s) return []
  return [
    { label: '在保',     value: s.active || 0,     color: '#67C23A', tagType: 'success' as const },
    { label: '即将到期', value: s.expiring || 0,   color: '#E6A23C', tagType: 'warning' as const },
    { label: '已过期',   value: s.expired || 0,    color: '#F56C6C', tagType: 'danger' as const },
    { label: '已续约',   value: s.renewed || 0,    color: '#409EFF', tagType: 'primary' as const },
    { label: '已终止',   value: s.terminated || 0, color: '#909399', tagType: 'info' as const },
  ]
})

const calcWarrantyPercent = (v: number) => {
  const total = warrantyStatusList.value.reduce((s, x) => s + x.value, 0)
  if (!total) return 0
  return Math.round((v / total) * 100)
}

const stageLabel = (s: string) => ({
  initiation: '立项', inquiry: '询价', contract: '合同', purchase: '采购',
  construction: '施工', settlement: '结算', warranty: '质保',
} as Record<string, string>)[s] || s

const stageColor = (s: string) => ({
  initiation: '#909399', inquiry: '#409EFF', contract: '#67C23A', purchase: '#E6A23C',
  construction: '#F56C6C', settlement: '#9c27b0', warranty: '#00bcd4',
} as Record<string, string>)[s] || '#909399'

const deviceColor = (s: string) => ({ normal: '#67C23A', fault: '#F56C6C', maintaining: '#E6A23C', scrapped: '#909399' } as Record<string, string>)[s] || '#909399'

const approvalTypeLabel = (s: string) => ({
  project: '项目',
  contract: '合同',
  purchase: '采购',
  expense: '报销',
  leave: '请假',
  attendance: '考勤',
  overtime: '加班',
  vehicle: '用车',
  recruitment: '招聘',
  resignation: '离职',
  customer: '客户',
  supplier: '供应商',
  warranty: '质保',
  other: '其他',
} as Record<string, string>)[s] || s

const deviceStatusLabel = (s: string) => ({
  normal: '正常',
  fault: '故障',
  maintaining: '维修中',
  scrapped: '已报废',
} as Record<string, string>)[s] || s

const activityTypeColor = (t: string) => ({
  approval: 'primary', log: 'success', warranty: 'warning', project: 'info',
} as Record<string, string>)[t] || 'info'

const activityTypeLabel = (t: string) => ({
  approval: '审批', log: '日志', warranty: '质保', project: '项目',
} as Record<string, string>)[t] || t

const fmt = (v: Record<string, unknown>) => {
  const n = parseFloat(v) || 0
  return n.toLocaleString('zh-CN', { maximumFractionDigits: 0 })
}

async function loadAll() {
  loading.value = true
  try {
    // V0.6.3: res = {code, data: <overview>}
    const res = await getOverview()
    overview.value = unwrapStats(res)
    generatedAt.value = new Date().toLocaleTimeString('zh-CN', { hour12: false })
    // V0.4.8 C2: 渲染图表
    await nextTick()
    renderCharts()
  } catch (e: unknown) {
    ElMessage.error('加载总览失败: ' + (e.message || 'unknown'))
  } finally {
    loading.value = false
  }
}

// V0.4.8 C2: 项目阶段分布 + 月度营收
const pieChartEl = ref<HTMLDivElement>()
const trendChartEl = ref<HTMLDivElement>()
let pieChart: echarts.ECharts | null = null
let trendChart: echarts.ECharts | null = null

const projectStageDist = computed(() => {
  const dist = overview.value?.project_stage_distribution
  if (!dist) return []
  return Object.entries(dist).map(([name, value]) => ({ name: stageLabel(name), value: Number(value) }))
})

const monthlyRevenue = computed(() => {
  const trend = overview.value?.monthly_revenue_trend
  if (!trend || !Array.isArray(trend)) return []
  return trend
})

function renderCharts() {
  // 阶段分布饼图
  if (pieChartEl.value && projectStageDist.value.length > 0) {
    pieChart?.dispose()
    pieChart = echarts.init(pieChartEl.value)
    pieChart.setOption({
      tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
      legend: { bottom: 0, type: 'scroll' },
      series: [{
        name: '项目阶段',
        type: 'pie',
        radius: ['38%', '70%'],
        avoidLabelOverlap: true,
        itemStyle: { borderRadius: 4, borderColor: '#fff', borderWidth: 2 },
        label: { show: true, formatter: '{b}\n{d}%' },
        data: projectStageDist.value,
      }],
    })
  }
  // 月度营收趋势 (双 Y 轴: 营收/支出)
  if (trendChartEl.value && monthlyRevenue.value.length > 0) {
    trendChart?.dispose()
    trendChart = echarts.init(trendChartEl.value)
    const months = monthlyRevenue.value.map((m: Record<string, unknown>) => m.month)
    trendChart.setOption({
      tooltip: { trigger: 'axis' },
      legend: { top: 0, right: 0 },
      grid: { top: 30, left: 50, right: 50, bottom: 30 },
      xAxis: { type: 'category', data: months },
      yAxis: [
        { type: 'value', name: '营收', position: 'left', axisLabel: { formatter: '{value} 万' } },
        { type: 'value', name: '支出', position: 'right', axisLabel: { formatter: '{value} 万' } },
      ],
      series: [
        { name: '营收', type: 'bar', data: monthlyRevenue.value.map((m: Record<string, unknown>) => m.revenue), itemStyle: { color: '#409EFF' } },
        { name: '支出', type: 'line', yAxisIndex: 1, smooth: true, data: monthlyRevenue.value.map((m: Record<string, unknown>) => m.expense), itemStyle: { color: '#F56C6C' } },
      ],
    })
  }
}

onMounted(loadAll)
onUnmounted(() => {
  pieChart?.dispose()
  trendChart?.dispose()
})
</script>

<style scoped lang="scss">
.kpi-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.stage-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 12px;
}
.stage-item {
  text-align: center;
  padding: 14px 8px;
  border-radius: 8px;
  border: 2px solid #ebeef5;
  transition: all 0.2s;
}
.stage-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.warranty-summary { display: flex; flex-direction: column; gap: 10px; }
.warranty-row { display: flex; align-items: center; }
.health-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}
.health-item { display: flex; align-items: center; gap: 12px; padding: 8px; }
.health-num { font-size: 22px; font-weight: 700; color: #303133; }
.health-label { font-size: 12px; color: #909399; }
.finance-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
.finance-item {
  padding: 14px;
  border-radius: 8px;
  background: #f8f9fb;
  text-align: center;
}
.finance-up { background: linear-gradient(135deg, #f0f9ff 0%, #e0f5ff 100%); }
.finance-down { background: linear-gradient(135deg, #fef0f0 0%, #ffe0e0 100%); }
.finance-label { font-size: 12px; color: #909399; }
.finance-amount { font-size: 18px; font-weight: 700; color: #303133; margin-top: 6px; }
.approval-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 8px;
}
.approval-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-radius: 6px;
  background: #fafbfc;
  border: 1px solid #ebeef5;
}
.approval-type { font-size: 12px; color: #606266; }
.approval-count {
  font-size: 14px;
  font-weight: 600;
  color: #c0c4cc;
  min-width: 24px;
  text-align: right;
}
.approval-count.has-pending { color: #F56C6C; }
.device-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
.device-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: 6px;
  background: #fafbfc;
}
.device-count { font-size: 20px; font-weight: 700; color: #303133; }
.device-label { font-size: 12px; color: #909399; }

/* V0.4.8 C2: 图表容器 */
.pie-chart { height: 280px; }
.trend-chart { height: 280px; }
</style>
