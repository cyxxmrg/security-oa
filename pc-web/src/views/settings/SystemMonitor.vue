<template>
  <div class="monitor-page">
    <div class="page-header">
      <div class="header-left">
        <el-icon class="header-icon"><Monitor /></el-icon>
        <div>
          <h2 class="header-title">系统监控面板</h2>
          <p class="header-sub">{{ summary }}</p>
        </div>
      </div>
      <div class="header-right">
        <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
        <el-switch v-model="autoRefresh" @change="toggleAuto" active-text="自动 30s" />
      </div>
    </div>

    <!-- KPI 卡 -->
    <el-row :gutter="12" v-loading="loading">
      <el-col :xs="12" :sm="6" v-for="kpi in kpis" :key="kpi.label">
        <div class="kpi-card" :class="`sev-${kpi.severity}`">
          <div class="kpi-label">{{ kpi.label }}</div>
          <div class="kpi-value">{{ kpi.value }}</div>
          <div class="kpi-sub">{{ kpi.sub }}</div>
        </div>
      </el-col>
    </el-row>

    <!-- 磁盘 + 错误趋势 -->
    <el-row :gutter="16" class="row">
      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header><span>💽 磁盘用量</span></template>
          <div v-for="m in metrics.disk?.mounts || []" :key="m.mount" class="disk-row">
            <div class="disk-label">
              <span>{{ m.mount }}</span>
              <el-tag size="small" :type="severityTag(m.severity)">{{ m.percent }}%</el-tag>
            </div>
            <el-progress
              :percentage="m.percent"
              :stroke-width="14"
              :show-text="false"
              :color="severityColor(m.severity)"
            />
            <div class="disk-detail">
              {{ fmtSize(m.used) }} / {{ fmtSize(m.total) }} (剩余 {{ fmtSize(m.free) }})
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header><span>📊 24h 错误趋势 (laravel.log)</span></template>
          <div v-if="!metrics.errors?.available" class="empty">无日志</div>
          <div v-else>
            <div class="err-summary">
              <span class="err-num">{{ metrics.errors.total_24h }}</span>
              <span class="err-unit">次</span>
              <span v-if="metrics.errors.last_error" class="err-last">
                最近: {{ metrics.errors.last_error.time }}
              </span>
            </div>
            <div class="err-chart">
              <div
                v-for="(c, h) in metrics.errors.by_hour"
                :key="h"
                class="err-bar"
                :style="{ height: Math.min(80, c * 8) + 'px' }"
                :title="`${h}:00 - ${c} 次`"
              />
            </div>
            <div class="err-hours">
              <span v-for="h in 24" :key="h">{{ h - 1 }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- DB + 服务 -->
    <el-row :gutter="16" class="row">
      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header><span>🗄️ 数据库状态</span></template>
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item label="活跃连接">{{ metrics.db?.active_connections }} / {{ metrics.db?.running_queries }} 运行中</el-descriptions-item>
            <el-descriptions-item label="缓存命中率">
              <span :class="`sev-${cacheRateSeverity}`">{{ metrics.db?.cache_hit_rate }}%</span>
            </el-descriptions-item>
            <el-descriptions-item label="慢查询">
              <el-tag :type="metrics.db?.slow_count > 0 ? 'danger' : 'success'" size="small">
                {{ metrics.db?.slow_count }} 个 (>1s)
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="等待锁">{{ metrics.db?.waiting_locks }} 个未授权</el-descriptions-item>
          </el-descriptions>
          <h4 class="sub-title">表大小 Top 5</h4>
          <el-table :data="metrics.db?.big_tables || []" border size="small">
            <el-table-column prop="name" label="表名" min-width="200" />
            <el-table-column label="大小" width="120" align="right">
              <template #default="{ row }">{{ fmtSize(row.size) }}</template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>

      <el-col :xs="24" :md="12">
        <el-card shadow="never">
          <template #header><span>⚙️ 服务状态</span></template>
          <el-descriptions :column="2" border size="small">
            <el-descriptions-item label="PHP-FPM workers">{{ metrics.services?.php_fpm_workers }} 个</el-descriptions-item>
            <el-descriptions-item label="opcache">
              <el-tag :type="metrics.services?.opcache_enabled ? 'success' : 'info'" size="small">
                {{ metrics.services?.opcache_enabled ? '启用' : '关闭' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="opcache 命中率">{{ metrics.services?.opcache_hit_rate }}%</el-descriptions-item>
            <el-descriptions-item label="opcache 内存">
              {{ fmtSize(metrics.services?.opcache_memory_used) }} / {{ fmtSize(metrics.services?.opcache_memory_used + metrics.services?.opcache_memory_free) }}
            </el-descriptions-item>
            <el-descriptions-item label="Load Average (1m)">{{ metrics.services?.load_avg_1?.toFixed(2) }}</el-descriptions-item>
            <el-descriptions-item label="Load Average (5m)">{{ metrics.services?.load_avg_5?.toFixed(2) }}</el-descriptions-item>
            <el-descriptions-item label="PHP 版本">{{ metrics.services?.php_version }}</el-descriptions-item>
            <el-descriptions-item label="Laravel">{{ metrics.services?.laravel_version }}</el-descriptions-item>
            <el-descriptions-item label="环境">{{ metrics.services?.environment }}</el-descriptions-item>
            <el-descriptions-item label="Debug">
              <el-tag :type="metrics.services?.debug ? 'warning' : 'success'" size="small">
                {{ metrics.services?.debug ? '开' : '关' }}
              </el-tag>
            </el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>

    <!-- 备份 -->
    <el-card shadow="never" class="backup-card">
      <template #header>
        <span>💾 最近备份</span>
        <el-tag :type="severityTag(metrics.backups?.severity || 'info')" size="small" style="margin-left: 8px">
          {{ metrics.backups?.age_days !== null ? `最新 ${metrics.backups?.age_days} 天前` : '无备份' }}
        </el-tag>
      </template>
      <el-table :data="metrics.backups?.files || []" border size="small">
        <el-table-column prop="name" label="文件名" min-width="280" />
        <el-table-column label="大小" width="120" align="right">
          <template #default="{ row }">{{ fmtSize(row.size) }}</template>
        </el-table-column>
        <el-table-column prop="mtime_human" label="时间" width="160" />
        <el-table-column prop="path" label="路径" min-width="280" />
      </el-table>
      <el-empty v-if="!metrics.backups?.files?.length" description="尚无备份" />
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { get } from '@/utils/request'
import { unwrapStats } from '@/utils/response'
import { ElMessage } from 'element-plus'
import { Monitor, Refresh } from '@element-plus/icons-vue'

// 后端路由: /api/admin/monitor/* (SystemMonitorController)
const API = '/admin/monitor'
const loading = ref(false)
const autoRefresh = ref(false)
const metrics = ref<Record<string, number>>({})

let timer: ReturnType<typeof setInterval> | null = null

const summary = computed(() => {
  if (!metrics.value.timestamp) return '加载中...'
  return `更新于 ${new Date(metrics.value.timestamp).toLocaleTimeString()}`
})

const kpis = computed(() => {
  const m = metrics.value
  const diskMax = m.disk?.max_percent || 0
  return [
    { label: '磁盘最大用量', value: diskMax + '%', sub: '5 个挂载点中最高', severity: diskMax > 95 ? 'danger' : (diskMax > 85 ? 'warning' : 'success') },
    { label: '活跃 DB 连接', value: m.db?.active_connections || 0, sub: `${m.db?.running_queries || 0} 运行中`, severity: (m.db?.active_connections || 0) > 50 ? 'warning' : 'success' },
    { label: '慢查询', value: (m.db?.slow_count || 0) + ' 个', sub: '> 1s', severity: (m.db?.slow_count || 0) > 0 ? 'danger' : 'success' },
    { label: '24h 错误数', value: m.errors?.total_24h || 0, sub: 'laravel.log', severity: (m.errors?.total_24h || 0) > 10 ? 'warning' : 'success' },
    { label: 'opcache 命中率', value: (m.services?.opcache_hit_rate || 0) + '%', sub: 'Laravel 加速', severity: (m.services?.opcache_hit_rate || 0) < 80 ? 'warning' : 'success' },
    { label: '缓存命中率', value: (m.db?.cache_hit_rate || 0) + '%', sub: 'PG buffer', severity: cacheRateSeverity.value === 'success' ? 'success' : (cacheRateSeverity.value === 'warning' ? 'warning' : 'danger') },
    { label: 'PHP-FPM', value: (m.services?.php_fpm_workers || 0) + ' 个', sub: 'workers', severity: 'success' },
    { label: '最近备份', value: m.backups?.age_days !== null ? (m.backups?.age_days + ' 天前') : '无', sub: m.backups?.latest?.name || '—', severity: m.backups?.severity || 'info' },
  ]
})

const cacheRateSeverity = computed(() => {
  const r = metrics.value.db?.cache_hit_rate || 0
  if (r >= 95) return 'success'
  if (r >= 80) return 'warning'
  return 'danger'
})

const fmtSize = (bytes?: number) => {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let i = 0
  let n = bytes
  while (n >= 1024 && i < units.length - 1) { n /= 1024; i++ }
  return n.toFixed(1) + ' ' + units[i]
}

const severityTag = (s: string): 'success' | 'warning' | 'danger' | 'info' => s === 'warning' ? 'warning' : s === 'danger' ? 'danger' : 'info'
const severityColor = (s: string) => ({ success: '#67C23A', warning: '#E6A23C', danger: '#F56C6C', info: '#909399' }[s] || '#909399')

const loadAll = async () => {
  loading.value = true
  try {
    // V0.6.3: res = {code, data: <metrics>}
    const res = await get('/admin/monitor/metrics')
    metrics.value = unwrapStats(res)
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + (e?.response?.data?.message || e?.message))
  } finally { loading.value = false }
}

const toggleAuto = (val: boolean | string | number) => {
  if (val) {
    timer = setInterval(loadAll, 30000)
  } else {
    clearInterval(timer)
    timer = null
  }
}

onMounted(loadAll)
onUnmounted(() => clearInterval(timer))
</script>

<style scoped lang="scss">
.monitor-page { padding: 20px; background: #f4f4f5; min-height: 100vh; }

.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 24px; border-radius: 12px; margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.header-left { display: flex; gap: 16px; align-items: center; }
.header-icon { font-size: 32px; color: #67C23A; }
.header-title { font-size: 18px; font-weight: 600; margin: 0; color: #303133; }
.header-sub { font-size: 12px; color: #909399; margin: 4px 0 0 0; }

.kpi-card {
  background: #fff; border-radius: 8px; padding: 16px; margin-bottom: 12px;
  border-left: 4px solid #909399;
}
.kpi-card.sev-success { border-left-color: #67C23A; }
.kpi-card.sev-warning { border-left-color: #E6A23C; }
.kpi-card.sev-danger { border-left-color: #F56C6C; }
.kpi-label { font-size: 12px; color: #909399; }
.kpi-value { font-size: 22px; font-weight: 700; color: #303133; margin-top: 4px; }
.kpi-sub { font-size: 11px; color: #909399; margin-top: 4px; }

.row { margin-bottom: 16px; }

.disk-row { margin-bottom: 14px; }
.disk-label { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px; }
.disk-detail { font-size: 11px; color: #909399; margin-top: 2px; }

.err-summary { text-align: center; padding: 12px 0; }
.err-num { font-size: 32px; font-weight: 700; color: #F56C6C; }
.err-unit { font-size: 14px; color: #909399; margin-left: 4px; }
.err-last { display: block; font-size: 12px; color: #909399; margin-top: 4px; }
.err-chart {
  display: flex; gap: 2px; align-items: flex-end; height: 80px; padding: 8px 0;
  border-bottom: 1px solid #ebeef5;
}
.err-bar { flex: 1; background: linear-gradient(180deg, #F56C6C 0%, #FAB6B6 100%); border-radius: 2px 2px 0 0; min-height: 1px; }
.err-hours { display: flex; gap: 2px; font-size: 9px; color: #c0c4cc; padding-top: 4px; }
.err-hours span { flex: 1; text-align: center; }

.sub-title { font-size: 14px; font-weight: 600; margin: 12px 0 8px 0; color: #303133; }

.backup-card { margin-bottom: 16px; }
.empty { text-align: center; color: #909399; padding: 24px 0; }
</style>
