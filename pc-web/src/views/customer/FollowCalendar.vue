<script setup lang="ts">
/**
 * 跟进日历 - orchestration
 * 4 个子组件：FollowKpiCards / FollowFilterBar / FollowCalendarGrid / FollowUpcomingList / FollowDayDrawer
 */
import { ref, computed, onMounted } from 'vue'
import { get } from '@/utils/request'
import FollowKpiCards from './components/FollowKpiCards.vue'
import FollowFilterBar from './components/FollowFilterBar.vue'
import FollowCalendarGrid, { type CalCell } from './components/FollowCalendarGrid.vue'
import FollowUpcomingList from './components/FollowUpcomingList.vue'
import FollowDayDrawer from './components/FollowDayDrawer.vue'

// ==================== 类型 ====================
interface FollowEvent {
  id?: number
  scheduled_at?: string
  status?: string
  type?: string
  customer_name?: string
  content?: string
  result?: string
  [key: string]: unknown
}
interface RawUser {
  id: number
  name?: string
  real_name?: string
  username?: string
  [key: string]: unknown
}
interface RawCustomer {
  id: number
  name?: string
  [key: string]: unknown
}

// ==================== 筛选 ====================
const filterMonth = ref(formatCurrentMonth())
const filterUser = ref<number | null>(null)
const filterCustomer = ref<number | null>(null)
const userOptions = ref<{ id: number; name: string }[]>([])
const customerOptions = ref<{ id: number; name: string }[]>([])

// ==================== 数据 ====================
const summary = ref<{ total: number; completed: number; planned: number; overdue: number }>({
  total: 0, completed: 0, planned: 0, overdue: 0,
})
const calendarObj = ref<Record<string, FollowEvent[]>>({})
const upcoming = ref<FollowEvent[]>([])

// ==================== 抽屉 ====================
const drawerVisible = ref(false)
const drawerTitle = ref('')
const drawerEvents = ref<FollowEvent[]>([])

const weekdays = ['日', '一', '二', '三', '四', '五', '六']

// ==================== 工具函数 ====================
function formatCurrentMonth() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function todayDateStr() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function formatTime(t: string | undefined): string {
  if (!t) return ''
  const m = String(t).match(/(\d{1,2}):(\d{2})/)
  return m ? `${m[1]}:${m[2]}` : ''
}

function countdownLabel(t: string | undefined): string {
  if (!t) return ''
  const date = new Date(String(t).replace(/-/g, '/'))
  if (isNaN(date.getTime())) return ''
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const target = new Date(date)
  target.setHours(0, 0, 0, 0)
  const diff = Math.round((target.getTime() - today.getTime()) / 86400000)
  if (diff === 0) return '今天'
  if (diff === 1) return '明天'
  if (diff === -1) return '昨天'
  if (diff > 1 && diff <= 7) return `${diff} 天后`
  if (diff < -1) return `${Math.abs(diff)} 天前`
  return ''
}

function eventColor(ev: FollowEvent): string {
  const st = String(ev.status || '')
  if (st === 'completed' || st === 'done')     return '#1D9E75'
  if (st === 'overdue' || st === 'late')       return '#A32D2D'
  if (st === 'in_progress' || st === 'doing')  return '#BA7517'
  if (st === 'cancelled' || st === 'cancel')   return '#909399'
  return '#534AB7'
}

function statusLabel(s: string): string {
  const st = String(s || 'planned')
  const map: Record<string, string> = {
    planned: '计划中', completed: '已完成', done: '已完成',
    overdue: '逾期', late: '逾期',
    in_progress: '进行中', doing: '进行中',
    cancelled: '已取消', cancel: '已取消',
  }
  return map[st] || st
}

function statusTagType(s: string): 'success' | 'primary' | 'warning' | 'danger' | 'info' {
  const st = String(s || '')
  if (st === 'completed' || st === 'done')    return 'success'
  if (st === 'in_progress' || st === 'doing') return 'warning'
  if (st === 'overdue' || st === 'late')      return 'danger'
  if (st === 'cancelled' || st === 'cancel')  return 'info'
  return 'primary'
}

function followTypeLabel(t: string): string {
  const map: Record<string, string> = {
    phone: '电话拜访', visit: '上门拜访', wechat: '微信沟通', email: '邮件', other: '其他',
    call: '电话', online: '微信', '微信': '微信', '电话': '电话', '上门': '上门',
  }
  return map[String(t || '')] || String(t || '—')
}

// ==================== 日历计算 ====================
const calendarCells = computed<CalCell[]>(() => {
  const [y, m] = filterMonth.value.split('-').map(Number)
  const firstDay = new Date(y, m - 1, 1)
  const startWeekday = firstDay.getDay()
  const daysInMonth = new Date(y, m, 0).getDate()
  const totalCells = Math.ceil((startWeekday + daysInMonth) / 7) * 7
  const cells: CalCell[] = []
  const todayStr = todayDateStr()
  const eventMap = new Map<string, FollowEvent[]>()
  for (const [date, events] of Object.entries(calendarObj.value)) {
    eventMap.set(date, Array.isArray(events) ? events : [])
  }
  for (let i = 0; i < totalCells; i++) {
    let yyyy = y, mm = m, dd = i - startWeekday + 1
    let inMonth = true
    if (dd <= 0) {
      const prev = new Date(y, m - 2, 0)
      yyyy = prev.getFullYear()
      mm = prev.getMonth() + 1
      dd = prev.getDate() + dd
      inMonth = false
    } else if (dd > daysInMonth) {
      const next = new Date(y, m, dd - daysInMonth)
      yyyy = next.getFullYear()
      mm = next.getMonth() + 1
      dd = next.getDate()
      inMonth = false
    }
    const date = `${yyyy}-${String(mm).padStart(2, '0')}-${String(dd).padStart(2, '0')}`
    const weekday = new Date(yyyy, mm - 1, dd).getDay()
    cells.push({
      date, day: dd, inMonth,
      isToday: date === todayStr,
      isWeekend: weekday === 0 || weekday === 6,
      events: eventMap.get(date) || [],
    })
  }
  return cells
})

// ==================== 操作 ====================
function changeMonth(delta: number) {
  const [y, m] = filterMonth.value.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  filterMonth.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
  loadAll()
}

function goToday() {
  filterMonth.value = formatCurrentMonth()
  loadAll()
}

function openDay(cell: CalCell) {
  drawerTitle.value = `${cell.date} 跟进记录`
  drawerEvents.value = cell.events
  drawerVisible.value = true
}

function openItem(item: FollowEvent) {
  drawerTitle.value = `${item.scheduled_at?.slice(0, 10) || ''} 跟进详情`
  drawerEvents.value = [item]
  drawerVisible.value = true
}

// ==================== 数据加载 ====================
async function loadAll() {
  try {
    const params: Record<string, unknown> = { month: filterMonth.value }
    if (filterUser.value)     params.user_id = filterUser.value
    if (filterCustomer.value) params.customer_id = filterCustomer.value
    const data = await get('/follow-ups/calendar', params)
    // V0.6.3: res = {code, data: <calendar>}
    const d = data?.data ?? {}
    const cal = d?.calendar
    calendarObj.value = (cal && typeof cal === 'object' && !Array.isArray(cal)) ? cal : {}
    const s = d?.summary || {}
    summary.value = {
      total:     s.total_count ?? 0,
      completed: s.by_result?.['已沟通'] ?? 0,
      planned:   s.by_result?.['待跟进'] ?? 0,
      overdue:   s.by_result?.['客户快'] ?? 0,
    }
    upcoming.value = d?.today_list || d?.upcoming || []
  } catch (e) {
    /* toast */
  }
}

async function loadFilters() {
  try {
    const u = await get('/users', { per_page: 200 })
    const d = u?.data ?? {}
    const arr = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : [])
    userOptions.value = arr.map((x: RawUser) => ({ id: x.id, name: String(x.name || x.real_name || x.username || '') }))
  } catch { userOptions.value = [] }
  try {
    const c = await get('/customers', { per_page: 200 })
    const d = c?.data ?? {}
    const arr = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : [])
    customerOptions.value = arr.map((x: RawCustomer) => ({ id: x.id, name: String(x.name || '') }))
  } catch { customerOptions.value = [] }
}

onMounted(async () => {
  await Promise.all([loadFilters(), loadAll()])
})
</script>

<template>
  <div class="page-container follow-page">
    <!-- 顶部 + 筛选 -->
    <div class="page-header">
      <span class="page-title">跟进日历</span>
    </div>
    <FollowFilterBar
      v-model:filter-month="filterMonth"
      v-model:filter-user="filterUser"
      v-model:filter-customer="filterCustomer"
      :user-options="userOptions"
      :customer-options="customerOptions"
      @refresh="loadAll"
    />

    <!-- KPI 4 卡 -->
    <FollowKpiCards :summary="summary" />

    <!-- 日历 + 待办 -->
    <div class="cal-layout">
      <FollowCalendarGrid
        :filter-month="filterMonth"
        :calendar-cells="calendarCells"
        :weekdays="weekdays"
        :format-time="formatTime"
        :event-color="eventColor"
        class="cal-main"
        @change-month="changeMonth"
        @go-today="goToday"
        @open-day="openDay"
      />
      <FollowUpcomingList
        :upcoming="upcoming"
        :format-time="formatTime"
        :countdown-label="countdownLabel"
        :status-tag-type="statusTagType"
        :status-label="statusLabel"
        class="cal-side"
        @open-item="openItem"
      />
    </div>

    <!-- 抽屉：某天详情 -->
    <FollowDayDrawer
      v-model:visible="drawerVisible"
      :title="drawerTitle"
      :events="drawerEvents"
      :format-time="formatTime"
      :status-tag-type="statusTagType"
      :status-label="statusLabel"
      :follow-type-label="followTypeLabel"
    />
  </div>
</template>

<style lang="scss" scoped>
.follow-page {
  padding: 16px;
  background: linear-gradient(180deg, #f5f7fa 0%, #eef2f7 100%);
  min-height: calc(100vh - 60px);
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 14px 20px;
  border-radius: 10px;
  margin-bottom: 14px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.page-title {
  font-size: 18px;
  font-weight: 600;
  color: #303133;
}
.cal-layout {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 16px;
  align-items: start;
}
@media (max-width: 1200px) {
  .cal-layout {
    grid-template-columns: 1fr;
  }
}
</style>
