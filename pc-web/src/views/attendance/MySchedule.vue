<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">我的排班</span>
      <div class="header-actions">
        <el-date-picker
          v-model="month"
          type="month"
          value-format="YYYY-MM"
          placeholder="选择月份"
          style="width: 140px"
          @change="load"
        />
      </div>
    </div>

    <!-- 下一班次提醒横幅 -->
    <div v-if="reminder" class="reminder-banner" :style="{ background: `linear-gradient(135deg, ${reminder.shift_color}22 0%, #fff 70%)`, borderLeftColor: reminder.shift_color }">
      <div class="reminder-banner__icon" :style="{ background: reminder.shift_color }">
        <el-icon :size="24" color="#fff"><Clock /></el-icon>
      </div>
      <div class="reminder-banner__content">
        <div class="title">
          下一班次：{{ reminder.shift_name }}
          <el-tag v-if="minutesUntil > 0 && minutesUntil < 60" type="danger" size="small">{{ minutesUntil }} 分钟后开始</el-tag>
          <el-tag v-else-if="minutesUntil > 0 && minutesUntil < 24*60" type="warning" size="small">{{ Math.floor(minutesUntil / 60) }} 小时后开始</el-tag>
        </div>
        <div class="subtitle">
          {{ reminder.date }} {{ reminder.start_time?.slice(0,5) }} ~ {{ reminder.end_time?.slice(0,5) }}
        </div>
      </div>
    </div>

    <!-- 月历 -->
    <div class="month-calendar">
      <div class="month-calendar__weekdays">
        <div v-for="w in weekHeaders" :key="w" class="weekday" :class="{ 'is-weekend': w === '日' || w === '六' }">{{ w }}</div>
      </div>
      <div class="month-calendar__days">
        <div
          v-for="(cell, i) in cells"
          :key="i"
          class="day-cell"
          :class="{
            'is-other-month': !cell.inMonth,
            'is-today': cell.iso === today,
            'is-rest': cell.data?.status === 'rest',
            'is-leave': cell.data?.status === 'leave',
            'is-sick': cell.data?.status === 'sick',
          }"
          :style="cell.data ? { borderLeftColor: cell.data.shift_color } : {}"
        >
          <div class="day-cell__date">{{ cell.day }}</div>
          <div v-if="cell.data" class="day-cell__shift" :style="{ background: cell.data.shift_color || '#909399' }">
            <div class="day-cell__shift-name">{{ cell.data.shift_name }}</div>
            <div class="day-cell__shift-time">{{ cell.data.start_time?.slice(0,5) }}~{{ cell.data.end_time?.slice(0,5) }}</div>
            <div v-if="cell.data.is_overnight" class="day-cell__overnight">跨夜</div>
            <div v-if="cell.data.status === 'default'" class="day-cell__default-tag">默认</div>
          </div>
          <div v-else class="day-cell__empty">无排班</div>
        </div>
      </div>
    </div>

    <el-alert type="info" :closable="false" show-icon style="margin-top: 16px">
      <template #default>
        迟到/早退判定已与排班联动: 超过排班 start_time + 阈值 算迟到, 早于 end_time - 阈值 算早退。<br>
        调班请联系管理员, 换班申请功能将在下个版本上线。
      </template>
    </el-alert>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Clock } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { schedule } from '@/api/modules'
import type { DaySchedule, ShiftReminder, CalendarCell, ErrorWithMessage } from './types'

const month = ref(new Date().toISOString().slice(0, 7))
const byDate = ref<Record<string, DaySchedule>>({})
const reminder = ref<ShiftReminder | null>(null)
const minutesUntil = ref(0)

const weekHeaders = ['一', '二', '三', '四', '五', '六', '日']
const today = new Date().toISOString().slice(0, 10)

const cells = computed(() => {
  const [y, m] = month.value.split('-').map(Number)
  const first = new Date(y, m - 1, 1)
  const last  = new Date(y, m, 0)
  // 第一格对齐周一
  const firstDow = (first.getDay() + 6) % 7  // 周一=0
  const out: CalendarCell[] = []
  for (let i = 0; i < firstDow; i++) {
    const d = new Date(y, m - 1, -firstDow + i + 1)
    out.push({ iso: d.toISOString().slice(0,10), day: d.getDate(), inMonth: false, data: null })
  }
  for (let d = 1; d <= last.getDate(); d++) {
    const iso = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`
    out.push({ iso, day: d, inMonth: true, data: byDate.value[iso] || null })
  }
  // 补齐到 6 周
  while (out.length % 7 !== 0 || out.length < 42) {
    const lastD = new Date(out[out.length - 1].iso)
    lastD.setDate(lastD.getDate() + 1)
    out.push({ iso: lastD.toISOString().slice(0,10), day: lastD.getDate(), inMonth: false, data: null })
  }
  return out
})

const load = async () => {
  try {
    const r = await schedule.mySchedule({ month: month.value })
    byDate.value = r?.data?.by_date || {}
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithMessage)?.message || '加载失败')
  }
}

const loadReminder = async () => {
  try {
    const r = await schedule.nextReminder()
    reminder.value = r?.data || null
    if (reminder.value) minutesUntil.value = reminder.value.minutes_until_start ?? 0
  } catch { /* ignore */ }
}

onMounted(() => { load(); loadReminder() })
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; .page-title { font-size: 20px; font-weight: 600; color: #303133; } }
.reminder-banner { display: flex; gap: 16px; align-items: center; padding: 16px 20px; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #0C447C; box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  &__icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  &__content { flex: 1; .title { font-size: 16px; font-weight: 600; color: #303133; display: flex; align-items: center; gap: 8px; } .subtitle { font-size: 13px; color: #606266; margin-top: 4px; } }
}
.month-calendar { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  &__weekdays { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 8px; }
  &__days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
}
.weekday { text-align: center; font-size: 13px; font-weight: 600; color: #606266; padding: 8px 0; &.is-weekend { color: #A32D2D; } }
.day-cell { min-height: 92px; padding: 6px 8px; border-radius: 6px; border-left: 3px solid transparent; background: #fafbfc; display: flex; flex-direction: column; gap: 4px;
  &.is-other-month { opacity: 0.4; }
  &.is-today { background: #fffbe6; }
  &.is-rest { background: #f0f9eb; }
  &.is-leave { background: #fef0f0; }
  &.is-sick { background: #fdf6ec; }
  &__date { font-size: 14px; font-weight: 500; color: #303133; }
  &__shift { padding: 4px 6px; border-radius: 4px; color: #fff; }
  &__shift-name { font-size: 12px; font-weight: 600; }
  &__shift-time { font-size: 10px; opacity: 0.9; font-family: monospace; }
  &__overnight { font-size: 10px; background: rgba(0,0,0,0.2); padding: 0 4px; border-radius: 2px; margin-top: 2px; display: inline-block; }
  &__default-tag { font-size: 10px; background: rgba(255,255,255,0.3); padding: 0 4px; border-radius: 2px; margin-top: 2px; display: inline-block; }
  &__empty { color: #c0c4cc; font-size: 12px; margin-top: 8px; }
}
</style>
