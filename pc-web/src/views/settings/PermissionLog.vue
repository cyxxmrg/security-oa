<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">权限变更历史</span>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadData">刷新</el-button>
      </div>
    </div>

    <!-- 顶部统计卡片 -->
    <div class="stats-row">
      <div class="stat-card" v-for="(item, idx) in statCards" :key="idx" :class="item.cls">
        <div class="stat-num">{{ item.count }}</div>
        <div class="stat-label">{{ item.label }}</div>
        <div class="stat-sub">{{ item.sub }}</div>
      </div>
    </div>

    <!-- 7 天趋势 sparkline -->
    <div class="content-card" v-if="dailySeries.length">
      <div class="card-title">7 天变更趋势</div>
      <div class="sparkline">
        <div
          v-for="(d, i) in dailySeries"
          :key="i"
          class="spark-bar-group"
          :title="`${d.day}: 永久 ${d.role_changed || 0} / 临时 ${d.temporary_role_granted || 0} / 撤销 ${d.role_revoked || 0}`"
        >
          <div class="bar" :style="barStyle(d, 'role_changed', 'danger')"></div>
          <div class="bar" :style="barStyle(d, 'temporary_role_granted', 'warning')"></div>
          <div class="bar" :style="barStyle(d, 'role_revoked', 'info')"></div>
          <div class="bar-label">{{ shortDay(d.day) }}</div>
        </div>
      </div>
      <div class="legend">
        <span class="dot danger"></span>永久角色变更
        <span class="dot warning"></span>临时角色授权
        <span class="dot info"></span>角色撤销
      </div>
    </div>

    <!-- 过滤 + 列表 -->
    <div class="content-card">
      <div class="filter-bar">
        <el-select v-model="filterAction" placeholder="按类型筛选" clearable style="width: 200px" @change="loadData">
          <el-option label="永久角色变更" value="role_changed" />
          <el-option label="临时角色授权" value="temporary_role_granted" />
          <el-option label="角色撤销" value="role_revoked" />
        </el-select>
        <el-select v-model="filterDays" style="width: 140px" @change="loadData">
          <el-option label="最近 7 天" :value="7" />
          <el-option label="最近 30 天" :value="30" />
          <el-option label="最近 90 天" :value="90" />
          <el-option label="最近 365 天" :value="365" />
        </el-select>
        <el-input
          v-model="filterKeyword"
          placeholder="搜索描述 / 操作人"
          clearable
          style="width: 280px"
          :prefix-icon="Search"
          @input="debouncedSearch"
        />
        <el-button :icon="Refresh" @click="loadData">刷新</el-button>
      </div>

      <el-table :data="filteredRows" border stripe v-loading="loading" style="width: 100%;">
        <el-table-column label="时间" width="180" fixed="left">
          <template #default="{ row }">
            <span class="time-text">{{ formatTime(row.created_at) }}</span>
            <div class="time-ago">{{ timeAgo(row.created_at) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="操作类型" width="130">
          <template #default="{ row }">
            <el-tag :type="actionColor(row.action)" size="small" effect="dark">
              <el-icon style="vertical-align: middle;"><component :is="actionIcon(row.action)" /></el-icon>
              {{ actionLabel(row.action) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作人" width="140">
          <template #default="{ row }">
            <div class="operator-cell">
              <el-avatar :size="28" style="background: #409EFF; color: white;">
                {{ (row.operator || '?').charAt(0) }}
              </el-avatar>
              <div class="operator-info">
                <div>{{ row.operator }}</div>
                <div class="muted">@{{ row.operator_id }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="目标用户" width="160">
          <template #default="{ row }">
            <template v-if="row.target_username">
              <div class="target-cell">
                <strong>{{ row.target_name || row.target_username }}</strong>
                <div class="muted">@{{ row.target_username }} (id={{ row.target_user_id }})</div>
              </div>
            </template>
            <span v-else class="muted">—</span>
          </template>
        </el-table-column>
        <el-table-column label="变更详情" min-width="380" show-overflow-tooltip>
          <template #default="{ row }">
            <span class="desc-text">{{ row.description }}</span>
          </template>
        </el-table-column>
        <el-table-column label="IP" width="140">
          <template #default="{ row }">
            <code class="ip-text">{{ row.ip || '-' }}</code>
          </template>
        </el-table-column>
      </el-table>

      <div v-if="!filteredRows.length && !loading" class="empty">
        <el-empty description="该时间段无权限变更记录" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Refresh, UserFilled, Clock, CircleClose } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import { TagType, PermissionSummary, DailySeriesItem } from './types'

interface Row {
  id: number
  action: 'role_changed' | 'temporary_role_granted' | 'role_revoked'
  operator_id: number
  operator: string
  description: string
  target_user_id: number | null
  target_username?: string
  target_name?: string
  ip: string
  created_at: string
}

const loading = ref(false)
const rows = ref<Row[]>([])
const summary = ref<PermissionSummary>({ by_action: { role_changed: 0, temporary_role_granted: 0, role_revoked: 0 }, total: 0, daily_series: [] })

const filterAction = ref('')
const filterDays = ref(7)
const filterKeyword = ref('')

const dailySeries = computed(() => summary.value.daily_series || [])

const statCards = computed(() => [
  {
    label: '永久角色变更',
    sub: '最近 7 天',
    count: summary.value.by_action.role_changed || 0,
    cls: 'stat-danger',
  },
  {
    label: '临时角色授权',
    sub: '最近 7 天',
    count: summary.value.by_action.temporary_role_granted || 0,
    cls: 'stat-warning',
  },
  {
    label: '角色撤销',
    sub: '最近 7 天',
    count: summary.value.by_action.role_revoked || 0,
    cls: 'stat-info',
  },
  {
    label: '总变更',
    sub: '所有类型',
    count: summary.value.total || 0,
    cls: 'stat-primary',
  },
])

const filteredRows = computed(() => {
  if (!filterKeyword.value) return rows.value
  const kw = filterKeyword.value.toLowerCase()
  return rows.value.filter(r =>
    (r.description || '').toLowerCase().includes(kw) ||
    (r.operator || '').toLowerCase().includes(kw) ||
    (r.target_username || '').toLowerCase().includes(kw)
  )
})

let debounceTimer: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => loadData(), 300)
}

const loadData = async () => {
  loading.value = true
  try {
    const [listRes, sumRes] = await Promise.all([
      get('/audit/role-changes', { action: filterAction.value, days: filterDays.value }),
      get('/audit/role-changes/summary', { days: filterDays.value }),
    ])
    // V0.6.3: res = {code, data: <list|summary>}
    rows.value = (listRes?.data as Row[]) || []
    summary.value = (sumRes?.data as PermissionSummary) || { by_action: {}, total: 0, daily_series: [] }
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + ((e as { message?: string })?.message || ''))
  } finally {
    loading.value = false
  }
}

const actionLabel = (a: string) => ({
  role_changed: '永久变更',
  temporary_role_granted: '临时授权',
  role_revoked: '撤销',
}[a] || a)

const actionColor = (a: string): TagType => ({
  role_changed: 'danger',
  temporary_role_granted: 'warning',
  role_revoked: 'info',
}[a] || 'info')

const actionIcon = (a: string) => ({
  role_changed: UserFilled,
  temporary_role_granted: Clock,
  role_revoked: CircleClose,
}[a] || UserFilled)

const barStyle = (d: DailySeriesItem, key: string, color: string) => {
  const all = dailySeries.value.flatMap((x: DailySeriesItem) => [
    x.role_changed || 0,
    x.temporary_role_granted || 0,
    x.role_revoked || 0,
  ])
  const max = Math.max(1, ...all)
  const h = ((Number(d[key]) || 0) / max) * 60
  return `height: ${h}px; background: var(--el-color-${color});`
}

const shortDay = (d: string) => d.slice(5)  // MM-DD

const formatTime = (s: string) => {
  if (!s) return ''
  const d = new Date(s)
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
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

onMounted(() => {
  loadData()
})
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
.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}
.stat-card {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  text-align: center;
  border-top: 3px solid #409EFF;
}
.stat-card.stat-danger { border-top-color: #F56C6C; }
.stat-card.stat-warning { border-top-color: #E6A23C; }
.stat-card.stat-info { border-top-color: #909399; }
.stat-card.stat-primary { border-top-color: #409EFF; }
.stat-num { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #606266; margin-top: 4px; }
.stat-sub { font-size: 11px; color: #909399; margin-top: 2px; }

.content-card {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  margin-bottom: 16px;
}
.card-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; }

.sparkline {
  display: flex;
  align-items: flex-end;
  gap: 6px;
  height: 90px;
  padding: 8px 0;
}
.spark-bar-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
  height: 100%;
  justify-content: flex-end;
}
.bar {
  width: 60%;
  min-height: 2px;
  border-radius: 2px 2px 0 0;
  transition: all 0.3s;
}
.bar-label { font-size: 10px; color: #909399; margin-top: 4px; }
.legend {
  display: flex;
  gap: 24px;
  font-size: 12px;
  color: #606266;
  margin-top: 12px;
  .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
  .dot.danger { background: #F56C6C; }
  .dot.warning { background: #E6A23C; }
  .dot.info { background: #909399; }
}

.filter-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}

.time-text { font-size: 13px; color: #303133; }
.time-ago { font-size: 11px; color: #909399; }
.operator-cell { display: flex; align-items: center; gap: 8px; }
.operator-info { display: flex; flex-direction: column; line-height: 1.2; }
.muted { color: #909399; font-size: 12px; }
.target-cell { line-height: 1.3; }
.desc-text { font-size: 13px; }
.ip-text { font-size: 12px; color: #606266; background: #f5f7fa; padding: 1px 4px; border-radius: 2px; }
.empty { padding: 40px 0; text-align: center; }
</style>
