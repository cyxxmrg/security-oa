<template>
  <div class="screen-container">
    <div class="screen-header">
      <div class="screen-logo">{{ systemShortName }}</div>
      <div class="screen-title">经营管理驾驶舱</div>
      <div class="screen-time">{{ currentTime }}</div>
      <div class="screen-refresh" :class="{ spinning: loading }" @click="loadData" title="手动刷新">
        <el-icon><Refresh /></el-icon>
      </div>
    </div>
    <div class="screen-body">
      <!-- 第一行：6 个指标卡 -->
      <div class="metric-row">
        <div class="metric-card" v-for="m in metrics" :key="m.label">
          <div class="metric-value" :style="{ color: m.color }">{{ m.value }}</div>
          <div class="metric-label">{{ m.label }}</div>
          <div v-if="m.trend !== 0" class="metric-trend" :class="m.trend > 0 ? 'up' : 'down'">
            {{ m.trend > 0 ? '↑' : '↓' }} {{ Math.abs(m.trend) }}%
          </div>
          <div v-else class="metric-trend muted">—</div>
        </div>
      </div>

      <!-- 第二行：营收柱图 + 项目阶段 -->
      <div class="chart-row">
        <div class="chart-panel">
          <div class="panel-title">
            营收趋势（近 12 个月）
            <span class="panel-sub">合计 ¥{{ formatNumber(revenueTotal) }}</span>
          </div>
          <div class="bar-chart">
            <div v-for="(item, i) in revenueData" :key="i" class="bar-col">
              <div class="bar-tip" v-if="item.value > 0">¥{{ formatNumber(item.value) }}</div>
              <div class="bar-fill" :style="{ height: Math.max(item.height, 2) + '%' }"></div>
              <span class="bar-month">{{ item.month }}</span>
            </div>
          </div>
        </div>
        <div class="chart-panel narrow">
          <div class="panel-title">项目阶段分布</div>
          <div class="status-list">
            <div v-for="s in projectStatus" :key="s.label" class="status-item">
              <div class="status-bar" :style="{ width: s.pct + '%', background: s.color }"></div>
              <div class="status-info">
                <span class="status-label">
                  <span class="status-dot" :style="{ background: s.color }"></span>
                  {{ s.label }}
                </span>
                <span><b>{{ s.count }}</b> 个 · {{ s.pct }}%</span>
              </div>
            </div>
            <div v-if="projectStatus.every(s => s.count === 0)" class="empty-state">暂无项目</div>
          </div>
        </div>
      </div>

      <!-- 第三行：售后 KPI + 待办 -->
      <div class="chart-row">
        <div class="chart-panel narrow">
          <div class="panel-title">维修中心</div>
          <div class="service-metrics">
            <div class="sm-item">
              <span class="sm-val" style="color:#1D9E75">{{ serviceMetrics.slaText }}</span>
              <span class="sm-label">SLA 达标率</span>
            </div>
            <div class="sm-item">
              <span class="sm-val" style="color:#185FA5">{{ serviceMetrics.avgResponseText }}</span>
              <span class="sm-label">平均响应</span>
            </div>
            <div class="sm-item">
              <span class="sm-val" style="color:#BA7517">{{ serviceMetrics.satisfaction }}<small>/5.0</small></span>
              <span class="sm-label">客户满意度</span>
            </div>
          </div>
        </div>
        <div class="chart-panel">
          <div class="panel-title">
            待办事项
            <span class="panel-sub">共 {{ todoTotal }} 项</span>
          </div>
          <div class="todo-grid">
            <div v-for="t in todos" :key="t.label" class="todo-card">
              <div class="todo-num" :style="{ color: t.color }">{{ t.count }}</div>
              <div class="todo-label">{{ t.label }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import { useSystemConfigStore } from '@/stores/systemConfig'

interface Metric { label: string; value: string; color: string; trend: number }
interface RevenuePoint { month: string; value: number; height: number }
interface ProjectStatus { label: string; count: number; pct: number; color: string }
interface ServiceMetrics { sla: number; slaText: string; avgResponse: number; avgResponseText: string; satisfaction: number }
interface Todo { label: string; count: number; color: string }

const systemConfigStore = useSystemConfigStore()
const systemShortName = computed(() => {
  const name = systemConfigStore.sysConfig.systemName || 'OA 办公系统'
  if (name.length <= 8) return name
  return name.replace('办公系统', '').replace('OA', '').trim() || name.slice(0, 8)
})

// 时钟
const currentTime = ref('')
let clockTimer: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  const update = () => { currentTime.value = new Date().toLocaleString('zh-CN', { hour12: false }) }
  update()
  clockTimer = setInterval(update, 1000)
})
onUnmounted(() => clearInterval(clockTimer))

// 数据
const metrics = ref<Metric[]>([])
const revenueData = ref<RevenuePoint[]>([])
const projectStatus = ref<ProjectStatus[]>([])
const serviceMetrics = ref<ServiceMetrics>({ sla: 0, slaText: '-', avgResponse: 0, avgResponseText: '-', satisfaction: 0 })
const todos = ref<Todo[]>([])
const loading = ref(false)

const revenueTotal = computed(() => revenueData.value.reduce((s, r) => s + (r.value || 0), 0))
const todoTotal = computed(() => todos.value.reduce((s, t) => s + (t.count || 0), 0))

const revenueColors = ['#1D9E75', '#378ADD', '#534AB7', '#BA7517', '#D85A30']

function formatNumber(n: number): string {
  if (!n) return '0'
  if (n >= 1e8) return (n / 1e8).toFixed(2) + ' 亿'
  if (n >= 1e4) return (n / 1e4).toFixed(1) + ' 万'
  return Math.round(n).toString()
}

async function loadData() {
  loading.value = true
  try {
    // V0.6.3: res = {code, data: <screen>} — 业务结构在 res.data 下
    const resp = await get('/dashboard/screen')
    const d = resp?.data ?? resp ?? {}
    if (d && (d.metrics || d.revenueChart || d.projectStatus)) {
      metrics.value        = d.metrics || []
      revenueData.value    = d.revenueChart || []
      projectStatus.value  = d.projectStatus || []
      serviceMetrics.value = d.serviceMetrics || serviceMetrics.value
      todos.value          = d.todos || []
    } else {
      console.warn('[screen] unexpected response shape:', d)
      ElMessage.warning('大屏数据格式异常')
    }
  } catch (e: unknown) {
    console.error('[screen] load error', e)
    ElMessage.error('大屏数据加载失败：' + (e?.message || e))
  } finally {
    loading.value = false
  }
}

// 自动刷新：每 60s
let refreshTimer: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  loadData()
  refreshTimer = setInterval(loadData, 60000)
})
onUnmounted(() => {
  clearInterval(clockTimer)
  clearInterval(refreshTimer)
})
</script>

<style lang="scss" scoped>
.screen-container {
  width: 100%;
  height: 100vh;
  background: #0d1b2a;
  color: #e0e6ed;
  padding: 20px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.screen-header {
  display: flex;
  align-items: center;
  gap: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  margin-bottom: 20px;
  flex-shrink: 0;
}
.screen-logo {
  background: linear-gradient(135deg, #1D9E75, #7fdbca);
  color: #0d1b2a;
  font-size: 14px;
  font-weight: 700;
  padding: 6px 14px;
  border-radius: 6px;
}
.screen-title {
  font-size: 22px;
  font-weight: 600;
  background: linear-gradient(90deg, #7fdbca, #378ADD);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.screen-time { margin-left: auto; font-size: 13px; color: #7a8ba0; }
.screen-refresh {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s;
  &:hover { border-color: #7fdbca; color: #7fdbca; }
  &.spinning :deep(svg) { animation: spin 1s linear infinite; }
}
@keyframes spin { to { transform: rotate(360deg); } }

.screen-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
  overflow: hidden;
}

.metric-row {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
}
.metric-card {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px;
  padding: 16px;
  text-align: center;
  position: relative;
  transition: all 0.3s;
  &:hover { background: rgba(255,255,255,0.07); border-color: rgba(127,219,202,0.4); }
}
.metric-value { font-size: 22px; font-weight: 700; line-height: 1.2; }
.metric-label { font-size: 12px; color: #7a8ba0; margin-top: 6px; }
.metric-trend {
  font-size: 11px; margin-top: 4px;
  &.up   { color: #1D9E75; }
  &.down { color: #A32D2D; }
  &.muted { color: #4a5a6a; }
}

.chart-row { display: flex; gap: 16px; flex: 1; min-height: 0; }
.chart-panel {
  flex: 1;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  &.narrow { flex: 0 0 35%; }
}
.panel-title {
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 12px;
  color: #b0bec5;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.panel-sub { font-size: 11px; color: #5a6a7a; font-weight: 400; }

.bar-chart { flex: 1; display: flex; align-items: flex-end; gap: 6px; padding-top: 20px; }
.bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  justify-content: flex-end;
  position: relative;
}
.bar-tip {
  position: absolute;
  top: -18px;
  font-size: 10px;
  color: #7fdbca;
  white-space: nowrap;
}
.bar-fill {
  width: 70%;
  background: linear-gradient(to top, #1D9E75, #378ADD);
  border-radius: 3px 3px 0 0;
  min-height: 4px;
  transition: height 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.bar-month { font-size: 10px; color: #5a6a7a; margin-top: 6px; }

.status-list { flex: 1; display: flex; flex-direction: column; gap: 10px; overflow-y: auto; }
.status-item { position: relative; padding: 4px 0; }
.status-bar {
  height: 22px;
  border-radius: 4px;
  opacity: 0.75;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s;
}
.status-item:hover .status-bar { opacity: 1; }
.status-info {
  display: flex; justify-content: space-between; align-items: center;
  font-size: 12px; color: #7a8ba0; margin-top: 4px;
  b { color: #e0e6ed; font-weight: 600; }
}
.status-label { display: flex; align-items: center; gap: 6px; }
.status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
.empty-state { text-align: center; color: #4a5a6a; padding: 20px 0; font-size: 13px; }

.service-metrics { flex: 1; display: flex; flex-direction: column; justify-content: space-around; }
.sm-item { text-align: center; }
.sm-val {
  font-size: 28px; font-weight: 700; display: block;
  small { font-size: 14px; opacity: 0.5; margin-left: 2px; }
}
.sm-label { font-size: 12px; color: #7a8ba0; margin-top: 4px; display: block; }

.todo-grid { flex: 1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; align-content: start; }
.todo-card {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: 6px;
  padding: 14px;
  text-align: center;
  transition: all 0.3s;
  &:hover { background: rgba(255,255,255,0.07); transform: translateY(-2px); }
}
.todo-num { font-size: 24px; font-weight: 700; }
.todo-label { font-size: 12px; color: #7a8ba0; margin-top: 4px; }
</style>
