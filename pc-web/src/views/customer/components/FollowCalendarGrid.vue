<script setup lang="ts">
/**
 * 跟进日历主体 - 6x7 网格 + 任务条 + 月切换
 */
import { ArrowLeft, ArrowRight } from '@element-plus/icons-vue'

export interface CalCell {
  date: string
  day: number
  inMonth: boolean
  isToday: boolean
  isWeekend: boolean
  events: Record<string, unknown>[]
}

defineProps<{
  filterMonth: string
  calendarCells: CalCell[]
  weekdays: string[]
  formatTime: (t: string | undefined) => string
  eventColor: (ev: Record<string, unknown>) => string
}>()

const emit = defineEmits<{
  (e: 'changeMonth', delta: number): void
  (e: 'goToday'): void
  (e: 'openDay', cell: CalCell): void
}>()
</script>

<template>
  <div class="calendar-card glass">
    <div class="cal-head">
      <el-button :icon="ArrowLeft" size="small" plain @click="emit('changeMonth', -1)" />
      <span class="cal-title">{{ filterMonth }}</span>
      <el-button :icon="ArrowRight" size="small" plain @click="emit('changeMonth', 1)" />
      <el-button size="small" plain @click="emit('goToday')" class="cal-today-btn">今天</el-button>
    </div>
    <div class="cal-weekdays">
      <div v-for="w in weekdays" :key="w" class="cal-weekday">{{ w }}</div>
    </div>
    <div class="cal-grid">
      <div
        v-for="(cell, idx) in calendarCells"
        :key="idx"
        class="cal-cell"
        :class="{
          'is-other': !cell.inMonth,
          'is-today': cell.isToday,
          'is-weekend': cell.isWeekend,
          'has-events': cell.events.length > 0,
        }"
        @click="emit('openDay', cell)"
      >
        <div class="cal-date-row">
          <span class="cal-date">{{ cell.day }}</span>
          <el-badge
            v-if="cell.events.length > 0"
            :value="cell.events.length"
            :max="99"
            class="cal-badge"
            type="primary"
          />
        </div>
        <div class="cal-events">
          <div
            v-for="(ev, i) in cell.events.slice(0, 3)"
            :key="i"
            class="cal-event"
            :style="{ borderLeftColor: eventColor(ev) }"
          >
            <span class="cal-event-time">{{ formatTime(ev.scheduled_at || ev.time) }}</span>
            <span class="cal-event-name">{{ ev.customer_name || ev.title || '跟进' }}</span>
          </div>
          <div v-if="cell.events.length > 3" class="cal-more">+{{ cell.events.length - 3 }} 更多</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.calendar-card {
  background: rgba(255, 255, 255, 0.75);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.5);
  border-radius: 12px;
  padding: 16px 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.cal-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.cal-title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  min-width: 90px;
  text-align: center;
}
.cal-today-btn {
  margin-left: auto;
}
.cal-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  margin-bottom: 6px;
}
.cal-weekday {
  text-align: center;
  font-size: 12px;
  color: #909399;
  font-weight: 600;
  padding: 4px 0;
}
.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}
.cal-cell {
  min-height: 96px;
  padding: 6px 8px;
  border-radius: 8px;
  background: rgba(245, 247, 250, 0.4);
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);

  &:hover {
    background: rgba(64, 158, 255, 0.06);
    border-color: rgba(64, 158, 255, 0.2);
  }

  &.is-other {
    opacity: 0.35;
  }
  &.is-today {
    background: linear-gradient(135deg, #FFF7E6 0%, #FFE4B5 100%);
    border-color: #FAAD14;
  }
  &.is-weekend:not(.is-other) {
    background: rgba(245, 247, 250, 0.7);
  }
  &.has-events {
    background: rgba(64, 158, 255, 0.04);
  }
}
.cal-date-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}
.cal-date {
  font-size: 13px;
  font-weight: 600;
  color: #303133;
}
.cal-cell.is-today .cal-date {
  color: #D46B08;
}
.cal-events {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.cal-event {
  font-size: 11px;
  padding: 2px 6px;
  background: #fff;
  border-radius: 4px;
  border-left: 3px solid #534AB7;
  display: flex;
  align-items: center;
  gap: 4px;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.cal-event-time {
  color: #606266;
  font-weight: 600;
  flex-shrink: 0;
}
.cal-event-name {
  color: #303133;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cal-more {
  font-size: 11px;
  color: #409EFF;
  padding: 0 6px;
  font-weight: 600;
}
</style>
