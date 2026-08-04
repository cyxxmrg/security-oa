<template>
  <div class="boss-dashboard">
    <div class="page-header">
      <div>
        <p class="eyebrow">经营决策入口</p>
        <h2>老板看板</h2>
        <p>合并原“总览看板”和“BI 报表”，集中查看经营指标、风险和专题分析。</p>
      </div>
      <div class="page-actions">
        <el-button :icon="Refresh" @click="refreshAll">刷新数据</el-button>
        <el-button type="primary" :icon="Download" @click="exportFullPdf">导出经营报告</el-button>
      </div>
    </div>

    <el-alert :type="dataStatus" :closable="false" class="status-alert">
      <template #title>
        数据状态：{{ lastRefreshedAt || '正在加载' }}
        <span v-if="viewStatus" class="status-detail">{{ viewStatus }}</span>
      </template>
    </el-alert>

    <div class="kpi-grid" v-loading="loading">
      <el-card v-for="item in kpis" :key="item.label" shadow="never" class="kpi-card">
        <div class="kpi-title">{{ item.label }}</div>
        <div class="kpi-value" :class="item.type">{{ item.value }}</div>
        <div class="kpi-desc">{{ item.desc }}</div>
      </el-card>
    </div>

    <div class="main-grid">
      <el-card shadow="never" class="trend-card">
        <template #header>
          <div class="card-header">
            <h3>经营分析入口</h3>
            <span>点击进入专题报表</span>
          </div>
        </template>
        <div class="report-grid">
          <button v-for="report in reports" :key="report.title" class="report-card" @click="router.push(report.path)">
            <span class="report-icon">{{ report.icon }}</span>
            <strong>{{ report.title }}</strong>
            <p>{{ report.desc }}</p>
            <em>{{ report.stat }}</em>
          </button>
        </div>
      </el-card>

      <el-card shadow="never" class="risk-card">
        <template #header>
          <div class="card-header">
            <h3>经营风险</h3>
            <el-tag type="danger" effect="plain">需关注</el-tag>
          </div>
        </template>
        <div class="risk-list">
          <div v-for="risk in risks" :key="risk.title" class="risk-row">
            <span class="risk-dot" :class="risk.type" />
            <div>
              <strong>{{ risk.title }}</strong>
              <p>{{ risk.desc }}</p>
            </div>
          </div>
        </div>
      </el-card>
    </div>

    <el-card shadow="never" class="pdf-card">
      <template #header>
        <div class="card-header">
          <h3>报告导出</h3>
          <span>按管理场景导出 PDF</span>
        </div>
      </template>
      <div class="pdf-grid">
        <button v-for="template in pdfTemplates" :key="template.key" class="pdf-template" @click="exportTemplate(template.key)">
          <span>📄</span>
          <strong>{{ template.name }}</strong>
          <p>{{ template.desc }}</p>
          <em>{{ template.pages }}</em>
        </button>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Refresh, Download } from '@element-plus/icons-vue'
import { getRevenue, getSalesFunnel, getProjectHealth, getCustomerRfm, getInventoryAging, getFinancePnl, getRefreshStatus, exportAnalyticsPdf } from '@/api/analytics'

const router = useRouter()
const loading = ref(false)
const lastRefreshedAt = ref<string>('')
const viewStatus = ref<string>('')
const dataStatus = ref<'success' | 'warning' | 'error'>('success')

const snippet = ref({
  revenue: 0,
  conversion: 0,
  funnelWeeks: 12,
  projectTotal: 0,
  projectGreen: 0,
  projectYellow: 0,
  projectRed: 0,
  customerCount: 0,
  inventoryValue: 0,
  inventoryShort: 0,
  inventoryStagnant: 0,
  netProfit: 0,
  avgMargin: 0,
})

const pdfTemplates = [
  { key: 'executive', name: '经营摘要', desc: '1-2 页，关键指标 + 风险预警', pages: '1-2 页' },
  { key: 'full', name: '完整报告', desc: '8-15 页，6 个专题完整图表', pages: '8-15 页' },
  { key: 'deep', name: '专题深化', desc: '3-5 页，单主题多层钻取', pages: '3-5 页' },
] as const

const kpis = computed(() => [
  {
    label: '近 12 月营收',
    value: `¥${formatNumber(snippet.value.revenue)}`,
    desc: '来自营收分析专题',
    type: 'green',
  },
  {
    label: '销售转化率',
    value: `${snippet.value.conversion}%`,
    desc: `近 ${snippet.value.funnelWeeks} 周销售漏斗`,
    type: 'blue',
  },
  {
    label: '项目健康度',
    value: `${snippet.value.projectTotal}`,
    desc: `正常 ${snippet.value.projectGreen} / 关注 ${snippet.value.projectYellow} / 风险 ${snippet.value.projectRed}`,
    type: 'orange',
  },
  {
    label: '净利润',
    value: `¥${formatNumber(snippet.value.netProfit)}`,
    desc: `净利率 ${snippet.value.avgMargin}%`,
    type: snippet.value.netProfit >= 0 ? 'green' : 'red',
  },
])

const reports = computed(() => [
  { title: '营收分析', icon: '💰', path: '/analytics/revenue', desc: '收入趋势、行业分布、回款情况', stat: `¥${formatNumber(snippet.value.revenue)}` },
  { title: '销售漏斗', icon: '📊', path: '/analytics/funnel', desc: '线索到成交的转化效率', stat: `${snippet.value.conversion}%` },
  { title: '项目健康度', icon: '📋', path: '/analytics/projects', desc: '红黄绿风险、进度和交付质量', stat: `${snippet.value.projectTotal} 个项目` },
  { title: '客户 RFM', icon: '👥', path: '/analytics/rfm', desc: '客户价值分层和活跃度', stat: `${snippet.value.customerCount} 个客户` },
  { title: '库存周转', icon: '📦', path: '/analytics/inventory', desc: '库存金额、短缺和呆滞预警', stat: `¥${formatNumber(snippet.value.inventoryValue)}` },
  { title: '财务利润表', icon: '💹', path: '/analytics/pnl', desc: '收入、成本、费用和利润', stat: `¥${formatNumber(snippet.value.netProfit)}` },
])

const risks = computed(() => [
  {
    title: snippet.value.projectRed > 0 ? `${snippet.value.projectRed} 个项目处于风险状态` : '暂无高风险项目',
    desc: '建议查看项目健康度，确认责任人、交付节点和回款计划。',
    type: snippet.value.projectRed > 0 ? 'danger' : 'success',
  },
  {
    title: snippet.value.inventoryShort > 0 ? `${snippet.value.inventoryShort} 项库存短缺或缺货` : '库存短缺风险较低',
    desc: '建议结合采购协同和库存周转专题制定补货计划。',
    type: snippet.value.inventoryShort > 0 ? 'warning' : 'success',
  },
  {
    title: snippet.value.netProfit < 0 ? '当前净利润为负' : '利润表现正常',
    desc: '可进入财务利润表查看收入、成本、费用的具体构成。',
    type: snippet.value.netProfit < 0 ? 'danger' : 'primary',
  },
])

function formatNumber(n: number) {
  return (Number(n) || 0).toLocaleString('zh-CN', { maximumFractionDigits: 0 })
}

async function refreshAll() {
  loading.value = true
  try {
    const [revWrap, funWrap, projWrap, rfmWrap, invWrap, pnlWrap, statusWrap]: Record<string, unknown>[] = await Promise.all([
      getRevenue({}),
      getSalesFunnel({ weeks: 12 }),
      getProjectHealth({ limit: 50 }),
      getCustomerRfm({ limit: 200 }),
      getInventoryAging({}),
      getFinancePnl({}),
      getRefreshStatus(),
    ])

    // V0.6.3+ 后端返回 {code, data, message} 格式, request.ts 不再自动解包, 这里手动取 data
    const rev = revWrap?.data ?? revWrap
    const fun = funWrap?.data ?? funWrap
    const proj = projWrap?.data ?? projWrap
    const rfm = rfmWrap?.data ?? rfmWrap
    const inv = invWrap?.data ?? invWrap
    const pnl = pnlWrap?.data ?? pnlWrap
    const status = statusWrap?.data ?? statusWrap

    snippet.value.revenue = Number(rev?.summary?.total_gross || 0)
    const totals = (fun?.rows || []).reduce((acc: Record<string, number>, row: Record<string, unknown>) => {
      acc.lead += Number(row.s_lead || 0)
      acc.won += Number(row.s_won || 0)
      return acc
    }, { lead: 0, won: 0 })
    snippet.value.conversion = totals.lead > 0 ? Number(((totals.won / totals.lead) * 100).toFixed(1)) : 0
    snippet.value.funnelWeeks = (fun?.rows || []).length || 12
    snippet.value.projectTotal = Number(proj?.stats?.total || 0)
    snippet.value.projectGreen = Number(proj?.stats?.green || 0)
    snippet.value.projectYellow = Number(proj?.stats?.yellow || 0)
    snippet.value.projectRed = Number(proj?.stats?.red || 0)
    snippet.value.customerCount = (rfm?.rows || []).length
    snippet.value.inventoryValue = Number(inv?.stats?.total_value || 0)
    snippet.value.inventoryShort = Number(inv?.stats?.shortage || 0) + Number(inv?.stats?.stockout || 0)
    snippet.value.inventoryStagnant = Number(inv?.stats?.stagnant || 0) + Number(inv?.stats?.overstock || 0)
    snippet.value.netProfit = Number(pnl?.summary?.total_profit || 0)
    snippet.value.avgMargin = Number(pnl?.summary?.avg_margin || 0)

    const views = Array.isArray(status) ? status : []
    const allOk = views.length > 0 && views.every((view: Record<string, unknown>) => view.refreshed_at)
    dataStatus.value = allOk ? 'success' : 'warning'
    viewStatus.value = views.length ? `${views.length} 个视图，${allOk ? '全部已刷新' : '部分缺少刷新时间'}` : '暂无刷新状态'
    lastRefreshedAt.value = views[0]?.refreshed_at || '暂无刷新时间'
  } catch (e) {
    dataStatus.value = 'error'
    viewStatus.value = '数据加载失败，请稍后重试'
    console.error('Refresh failed', e)
  } finally {
    loading.value = false
  }
}

function exportFullPdf() {
  void exportAnalyticsPdf('full', 'full')
}

function exportTemplate(template: 'executive' | 'full' | 'deep') {
  void exportAnalyticsPdf('full', template)
}

onMounted(refreshAll)
</script>

<style scoped>
.boss-dashboard {
  min-height: 100%;
  padding: 22px 24px 28px;
  background: #f5f7fa;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 28px;
  color: #fff;
  border-radius: 18px;
  background: linear-gradient(135deg, #0c447c 0%, #1d9e75 100%);
  box-shadow: 0 14px 32px rgba(12, 68, 124, 0.18);
}

.page-header h2 {
  margin: 4px 0 8px;
  font-size: 26px;
}

.page-header p {
  margin: 0;
  color: rgba(255, 255, 255, 0.84);
}

.eyebrow {
  color: rgba(255, 255, 255, 0.76) !important;
  font-size: 13px;
}

.page-actions {
  display: flex;
  gap: 10px;
}

.status-alert {
  margin: 16px 0;
  border-radius: 12px;
}

.status-detail {
  margin-left: 16px;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.kpi-card,
.trend-card,
.risk-card,
.pdf-card {
  border: none;
  border-radius: 14px;
}

.kpi-title {
  color: #7d8794;
  font-size: 13px;
}

.kpi-value {
  margin: 12px 0 6px;
  color: #185fa5;
  font-size: 28px;
  font-weight: 800;
}

.kpi-value.green { color: #1d9e75; }
.kpi-value.orange { color: #ba7517; }
.kpi-value.red { color: #d85a30; }

.kpi-desc {
  color: #909399;
  font-size: 12px;
}

.main-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.55fr) minmax(320px, 1fr);
  gap: 16px;
  margin-top: 16px;
}

.card-header {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-header h3 {
  margin: 0;
  color: #1f2d3d;
  font-size: 16px;
}

.card-header span {
  color: #909399;
  font-size: 12px;
}

.report-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.report-card,
.pdf-template {
  text-align: left;
  border: 1px solid #e8edf5;
  border-radius: 14px;
  background: #fff;
  cursor: pointer;
  transition: all 0.18s ease;
}

.report-card {
  min-height: 142px;
  padding: 16px;
}

.report-card:hover,
.pdf-template:hover {
  transform: translateY(-2px);
  border-color: #bdd7f5;
  box-shadow: 0 12px 24px rgba(31, 45, 61, 0.08);
}

.report-icon {
  display: block;
  margin-bottom: 8px;
  font-size: 26px;
}

.report-card strong,
.pdf-template strong {
  display: block;
  color: #1f2d3d;
  font-size: 15px;
}

.report-card p,
.pdf-template p {
  min-height: 36px;
  margin: 7px 0 10px;
  color: #7d8794;
  font-size: 12px;
  line-height: 1.5;
}

.report-card em,
.pdf-template em {
  color: #185fa5;
  font-size: 13px;
  font-style: normal;
  font-weight: 700;
}

.risk-list {
  min-height: 304px;
}

.risk-row {
  display: flex;
  gap: 12px;
  padding: 15px 0;
  border-bottom: 1px solid #f0f2f5;
}

.risk-row:last-child {
  border-bottom: none;
}

.risk-row strong {
  color: #303133;
  font-size: 14px;
}

.risk-row p {
  margin: 6px 0 0;
  color: #7d8794;
  font-size: 13px;
  line-height: 1.5;
}

.risk-dot {
  width: 8px;
  height: 8px;
  margin-top: 6px;
  border-radius: 50%;
  background: #409eff;
  flex: none;
}

.risk-dot.warning { background: #e6a23c; }
.risk-dot.danger { background: #f56c6c; }
.risk-dot.success { background: #67c23a; }

.pdf-card {
  margin-top: 16px;
}

.pdf-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

.pdf-template {
  min-height: 116px;
  padding: 16px;
}

.pdf-template span {
  display: block;
  margin-bottom: 8px;
  font-size: 24px;
}

@media (max-width: 1280px) {
  .kpi-grid,
  .report-grid,
  .pdf-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .main-grid {
    grid-template-columns: 1fr;
  }
}
</style>
