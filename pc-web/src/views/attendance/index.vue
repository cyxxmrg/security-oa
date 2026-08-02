<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">考勤总览</span>
      <span class="page-date">{{ todayLabel }}</span>
    </div>

    <div class="stat-cards">
      <div class="stat-card stat-card--primary">
        <div class="stat-card__icon"><el-icon :size="32"><UserFilled /></el-icon></div>
        <div class="stat-card__info">
          <span class="stat-card__label">今日出勤</span>
          <span class="stat-card__value">{{ stats.present }}</span>
          <span class="stat-card__rate">总人数 {{ stats.totalUsers }}</span>
        </div>
      </div>
      <div class="stat-card stat-card--warning">
        <div class="stat-card__icon"><el-icon :size="32"><Clock /></el-icon></div>
        <div class="stat-card__info">
          <span class="stat-card__label">今日迟到</span>
          <span class="stat-card__value">{{ stats.late }}</span>
          <span class="stat-card__rate">迟到率 {{ lateRate }}%</span>
        </div>
      </div>
      <div class="stat-card stat-card--danger">
        <div class="stat-card__icon"><el-icon :size="32"><CircleCloseFilled /></el-icon></div>
        <div class="stat-card__info">
          <span class="stat-card__label">今日缺勤</span>
          <span class="stat-card__value">{{ stats.absent }}</span>
          <span class="stat-card__rate">缺勤率 {{ absentRate }}%</span>
        </div>
      </div>
      <div class="stat-card stat-card--info">
        <div class="stat-card__icon"><el-icon :size="32"><Position /></el-icon></div>
        <div class="stat-card__info">
          <span class="stat-card__label">今日外勤</span>
          <span class="stat-card__value">{{ stats.fieldWork }}</span>
          <span class="stat-card__rate">外勤记录</span>
        </div>
      </div>
    </div>

    <div class="content-row">
      <div class="content-card content-card--calendar">
        <div class="card-title">
          考勤日历
          <div class="month-switch">
            <el-button link size="small" @click="changeMonth(-1)">‹ 上月</el-button>
            <span class="month-label">{{ calendarMonth }}</span>
            <el-button link size="small" @click="changeMonth(1)">下月 ›</el-button>
          </div>
        </div>
        <el-calendar v-model="calendarValue">
          <template #date-cell="{ data }">
            <div class="calendar-day" :class="{ 'is-today': data.isSelected, 'is-future': isFuture(data.day) }" @click="handleDateClick(data.day)">
              <div class="calendar-day__date">{{ data.day.split('-')[2] }}</div>
              <div v-if="getDayStat(data.day)" class="calendar-day__stats">
                <span v-if="getDayStat(data.day).present > 0" class="stat-chip stat-chip--primary" :title="`出勤 ${getDayStat(data.day).present} 人`">
                  出勤 {{ getDayStat(data.day).present }}
                </span>
                <span v-if="getDayStat(data.day).late > 0" class="stat-chip stat-chip--warning" :title="`迟到 ${getDayStat(data.day).late} 人`">
                  迟到 {{ getDayStat(data.day).late }}
                </span>
                <span v-if="getDayStat(data.day).absent > 0" class="stat-chip stat-chip--danger" :title="`缺勤 ${getDayStat(data.day).absent} 人`">
                  缺勤 {{ getDayStat(data.day).absent }}
                </span>
                <span v-if="getDayStat(data.day).fieldWork > 0" class="stat-chip stat-chip--purple" :title="`外勤 ${getDayStat(data.day).fieldWork} 人`">
                  外勤 {{ getDayStat(data.day).fieldWork }}
                </span>
                <span v-if="getDayStat(data.day).leave > 0" class="stat-chip stat-chip--info" :title="`请假 ${getDayStat(data.day).leave} 人`">
                  请假 {{ getDayStat(data.day).leave }}
                </span>
                <span v-if="getDayStatTotal(data.day) === 0" class="stat-chip stat-chip--empty">无数据</span>
              </div>
            </div>
          </template>
        </el-calendar>
      </div>

      <div class="content-card content-card--actions">
        <div class="card-title">快捷操作</div>
        <div class="quick-actions">
          <div class="quick-action-item" @click="handleAction('补卡')">
            <el-icon :size="28" color="#0C447C"><Edit /></el-icon>
            <span>补卡申请</span>
            <p>忘记打卡时提交补卡说明</p>
          </div>
          <div class="quick-action-item" @click="handleAction('请假')">
            <el-icon :size="28" color="#1D9E75"><Document /></el-icon>
            <span>请假申请</span>
            <p>提交年假、事假、病假申请</p>
          </div>
          <div class="quick-action-item" @click="handleAction('加班')">
            <el-icon :size="28" color="#BA7517"><Timer /></el-icon>
            <span>加班申请</span>
            <p>工作日或休息日加班登记</p>
          </div>
          <div class="quick-action-item" @click="handleAction('外勤')">
            <el-icon :size="28" color="#534AB7"><Position /></el-icon>
            <span>外勤报备</span>
            <p>外出办公时进行报备登记</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { UserFilled, Clock, CircleCloseFilled, Position, Edit, Document, Timer } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import type { DayStat } from './types'

const router = useRouter()

const todayLabel = computed(() => {
  const d = new Date()
  const week = ['日', '一', '二', '三', '四', '五', '六'][d.getDay()]
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} 星期${week}`
})

const stats = reactive({
  totalUsers: 0,
  present: 0,
  late: 0,
  absent: 0,
  fieldWork: 0,
})

const lateRate = computed(() => {
  if (!stats.totalUsers) return 0
  return ((stats.late / stats.totalUsers) * 100).toFixed(1)
})
const absentRate = computed(() => {
  if (!stats.totalUsers) return 0
  return ((stats.absent / stats.totalUsers) * 100).toFixed(1)
})

const calendarValue = ref(new Date())
const calendarMonth = computed(() => {
  const d = calendarValue.value
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
})
// key=YYYY-MM-DD -> { present, late, absent, fieldWork, leave }
const calendarDays = ref<Record<string, DayStat>>({})

function changeMonth(delta: number) {
  const d = new Date(calendarValue.value)
  d.setDate(1)
  d.setMonth(d.getMonth() + delta)
  calendarValue.value = d
  loadCalendar()
}

function getDayStat(day: string) {
  return calendarDays.value[day] || null
}

function getDayStatTotal(day: string) {
  const s = getDayStat(day)
  if (!s) return 0
  return (s.present ?? 0) + (s.late ?? 0) + (s.absent ?? 0) + (s.fieldWork ?? 0) + (s.leave ?? 0)
}

function isFuture(day: string) {
  const t = new Date(day)
  const now = new Date()
  now.setHours(23, 59, 59, 999)
  return t > now
}

async function loadCalendar() {
  try {
    const r = await get('/attendance/calendar', { month: calendarMonth.value })
    // V0.6.3: res = {code, data: <calendar>}
    const d = r?.data ?? r ?? {}
    calendarDays.value = d?.days || {}
  } catch (e) {
    // ignore
  }
}

const loadOverview = async () => {
  try {
    const r = await get('/attendance/overview')
    // V0.6.3: res = {code, data: <overview>}
    const d = r?.data ?? r ?? {}
    Object.assign(stats, d || {})
  } catch (e) {
    // ignore
  }
}

onMounted(() => {
  loadOverview()
  loadCalendar()
})

const routeMap: Record<string, string> = {
  '补卡': '/attendance/record',
  '请假': '/attendance/leave',
  '加班': '/attendance/overtime',
  '外勤': '/attendance/record',
}

function handleAction(action: string) {
  const path = routeMap[action]
  if (path) router.push(path)
}

function handleDateClick(day: string) {
  const clicked = new Date(day)
  const now = new Date()
  now.setHours(23, 59, 59, 999)
  if (clicked > now) return
  router.push({ path: '/attendance/record', query: { date: day } })
}
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; .page-title { font-size: 20px; font-weight: 600; color: #303133; } .page-date { color: #909399; font-size: 14px; } }
.stat-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
.stat-card { background: #fff; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); transition: transform 0.2s, box-shadow 0.2s; &:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); } &__icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; } &__info { display: flex; flex-direction: column; } &__label { font-size: 13px; color: #909399; margin-bottom: 4px; } &__value { font-size: 28px; font-weight: 700; color: #303133; line-height: 1.2; } &__rate { font-size: 12px; color: #909399; margin-top: 4px; } &--primary &__icon { background: rgba(12, 68, 124, 0.1); color: #0C447C; } &--warning &__icon { background: rgba(186, 117, 23, 0.1); color: #BA7517; } &--danger &__icon { background: rgba(163, 45, 45, 0.1); color: #A32D2D; } &--info &__icon { background: rgba(83, 74, 183, 0.1); color: #534AB7; } }
.content-row { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }
.content-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); .card-title { font-size: 16px; font-weight: 600; color: #303133; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #ebeef5; display: flex; align-items: center; justify-content: space-between; } }

/* 考勤日历标题区 (上个月/下个月 切换) */
.month-switch { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #606266; .month-label { min-width: 64px; text-align: center; font-weight: 500; color: #303133; } }

/* 日历单元格 */
.calendar-day { text-align: left; padding: 4px 2px; cursor: pointer; border-radius: 4px; transition: background 0.2s; min-height: 56px; display: flex; flex-direction: column; gap: 2px; &:hover { background: rgba(12, 68, 124, 0.08); } &.is-today { background: rgba(12, 68, 124, 0.1); font-weight: 700; } &.is-future { opacity: 0.55; cursor: default; pointer-events: none; } }
.calendar-day__date { font-size: 13px; color: #303133; padding-left: 2px; }
.calendar-day__stats { display: flex; flex-wrap: wrap; gap: 2px; margin-top: 2px; }
.stat-chip { display: inline-block; font-size: 10px; line-height: 1; padding: 2px 5px; border-radius: 3px; font-weight: 500; white-space: nowrap; }
.stat-chip--primary { color: #0C447C; background: rgba(12, 68, 124, 0.1); }
.stat-chip--warning { color: #BA7517; background: rgba(186, 117, 23, 0.12); }
.stat-chip--danger  { color: #A32D2D; background: rgba(163, 45, 45, 0.1); }
.stat-chip--purple  { color: #534AB7; background: rgba(83, 74, 183, 0.1); }
.stat-chip--info    { color: #1D9E75; background: rgba(29, 158, 117, 0.1); }
.stat-chip--empty   { color: #c0c4cc; background: #f5f7fa; }

.quick-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.quick-action-item { text-align: center; padding: 20px 12px; border-radius: 8px; background: #f5f7fa; cursor: pointer; transition: all 0.2s; &:hover { background: rgba(12, 68, 124, 0.06); transform: translateY(-1px); } span { display: block; font-size: 14px; font-weight: 600; color: #303133; margin-top: 8px; } p { font-size: 12px; color: #909399; margin-top: 4px; } }
</style>
