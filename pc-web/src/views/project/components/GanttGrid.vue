<script setup lang="ts">
/**
 * GanttGrid — 甘特图主体网格 (v0.3.14 C3)
 * 日期表头 + 任务行 + 任务条 + 里程碑
 */
import { computed } from 'vue'
import { Flag, Check, VideoPlay, Clock, CircleClose } from '@element-plus/icons-vue'
import {
  STATUS_COLOR, STATUS_LABEL, STATUS_TAG_TYPE, cellWidth,
  type GanttTask, type GanttDate,
} from '../ganttTypes'

const props = defineProps<{
  tasks: GanttTask[]
  dates: GanttDate[]
  zoom: number
}>()

const emit = defineEmits<{
  (e: 'task-click', t: GanttTask): void
}>()

const gridWidth = computed(() => 280 + props.dates.length * cellWidth)

const isWeekend = (d: GanttDate) => {
  const day = d.date.getDay()
  return day === 0 || day === 6
}
const isToday = (d: GanttDate) => d.full === new Date().toISOString().split('T')[0]
const isMonthStart = (d: GanttDate) => d.day === 1

const statusColor = (s: GanttTask['status']) => STATUS_COLOR[s] || '#909399'
const statusTagType = (s: GanttTask['status']) => STATUS_TAG_TYPE[s] || 'info'
const statusText = (s: GanttTask['status']) => STATUS_LABEL[s] || '-'
const statusIcon = (s: GanttTask['status']) => {
  if (s === 'done') return Check
  if (s === 'in-progress') return VideoPlay
  if (s === 'delayed') return CircleClose
  return Clock
}

const barStyle = (task: GanttTask) => {
  if (task.isMilestone) return {}
  const left = task.startOffset * cellWidth
  const width = Math.max(task.duration, 2) * cellWidth
  return { left: left + 'px', width: width + 'px' }
}

const milestoneStyle = (task: GanttTask) => ({
  left: (task.startOffset * cellWidth - 12) + 'px',
})

// 今日指示线
const todayOffset = computed(() => {
  const today = new Date()
  const start = new Date(props.dates[0]?.date || today)
  const diff = Math.ceil((today.getTime() - start.getTime()) / (1000 * 60 * 60 * 24))
  return diff * cellWidth
})

const todayStr = new Date().toISOString().split('T')[0]
</script>

<template>
  <div class="gantt-wrapper" :style="{ transform: `scale(${zoom})`, transformOrigin: '0 0' }">
    <div class="gantt-grid" :style="{ width: gridWidth + 'px' }">
      <!-- 表头：日期 -->
      <div class="gantt-header">
        <div class="task-col">任务</div>
        <div class="time-cols">
          <div
            v-for="(date, idx) in dates"
            :key="idx"
            class="time-cell"
            :class="{
              'weekend': isWeekend(date),
              'today': isToday(date),
              'month-start': isMonthStart(date),
            }"
          >
            <div class="day-num">{{ date.day }}</div>
            <div class="month-num" v-if="isMonthStart(date)">{{ date.month }}月</div>
          </div>
        </div>
      </div>

      <!-- 任务行 -->
      <div class="gantt-row" v-for="(task, idx) in tasks" :key="idx">
        <div class="task-col" :title="task.name">
          <el-icon v-if="task.isMilestone" color="#BA7517" class="ms-icon"><Flag /></el-icon>
          <el-icon v-else :color="statusColor(task.status)" class="task-icon">
            <component :is="statusIcon(task.status)" />
          </el-icon>
          <div class="task-info">
            <div class="task-name">{{ task.name }}</div>
            <div class="task-meta">
              <el-tag :type="statusTagType(task.status)" size="small" effect="light">
                {{ statusText(task.status) }}
              </el-tag>
              <span class="task-progress">{{ task.progress }}%</span>
            </div>
          </div>
        </div>
        <div class="time-cols">
          <div
            v-for="(date, dateIdx) in dates"
            :key="dateIdx"
            class="time-cell"
            :class="{
              'weekend': isWeekend(date),
              'today': isToday(date),
            }"
          ></div>
          <!-- 任务条 -->
          <div
            v-if="!task.isMilestone"
            class="task-bar"
            :class="['bar-' + task.status]"
            :style="barStyle(task)"
            @click="emit('task-click', task)"
          >
            <div class="bar-progress" :style="{ width: task.progress + '%' }"></div>
            <span class="bar-label">{{ task.name }} ({{ task.progress }}%)</span>
          </div>
          <!-- 里程碑 -->
          <div
            v-else
            class="milestone"
            :style="milestoneStyle(task)"
            @click="emit('task-click', task)"
          >
            <el-icon color="#fff" :size="14"><Flag /></el-icon>
            <span class="milestone-label">{{ task.name }}</span>
          </div>
        </div>
      </div>

      <el-empty v-if="!tasks.length" description="暂无施工任务" />
    </div>
  </div>

  <!-- 今日指示线 -->
  <div class="today-indicator" :style="{ left: (280 + todayOffset) + 'px' }">
    <div class="today-line"></div>
    <div class="today-text">今日 {{ todayStr }}</div>
  </div>
</template>

<style scoped>
.gantt-wrapper {
  overflow-x: auto;
  overflow-y: hidden;
  background: #fff;
  border-radius: 8px;
  padding: 8px 0 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.gantt-grid {
  position: relative;
  display: flex; flex-direction: column;
  min-width: 100%;
}

.gantt-header {
  display: flex; height: 60px; position: sticky; top: 0;
  background: #f5f7fa; border-bottom: 1px solid #ebeef5; z-index: 4;
}
.task-col {
  width: 280px; min-width: 280px;
  padding: 0 12px; display: flex; align-items: center;
  font-size: 13px; font-weight: 600; color: #303133;
  border-right: 1px solid #ebeef5; background: #f5f7fa;
}
.time-cols { display: flex; flex: 1; }
.time-cell {
  width: 40px; min-width: 40px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  font-size: 12px; color: #606266;
  border-right: 1px solid #f0f2f5; position: relative;
}
.time-cell.weekend { background: #fafbfc; color: #c0c4cc; }
.time-cell.today { background: #FDF6EC; color: #BA7517; font-weight: 700; }
.time-cell.month-start { border-left: 1px solid #dcdfe6; }
.day-num { font-size: 13px; }
.month-num { font-size: 10px; color: #909399; margin-top: 2px; }

.gantt-row {
  display: flex; height: 56px; border-bottom: 1px solid #f5f7fa;
  transition: background 0.15s;
}
.gantt-row:hover { background: #fafbfc; }
.gantt-row .task-col {
  background: #fff; gap: 10px;
  border-right: 1px solid #ebeef5;
}
.task-icon, .ms-icon { flex-shrink: 0; }
.task-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.task-name { font-size: 13px; font-weight: 500; color: #303133; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.task-meta { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #909399; }
.task-progress { color: #185FA5; font-weight: 600; }

.task-bar {
  position: absolute; top: 12px; height: 32px;
  border-radius: 4px; cursor: pointer;
  display: flex; align-items: center; padding: 0 8px;
  overflow: hidden; transition: transform 0.15s, box-shadow 0.15s;
  z-index: 2;
}
.task-bar:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.task-bar.bar-done { background: linear-gradient(180deg, #58C499 0%, #1D9E75 100%); }
.task-bar.bar-in-progress { background: linear-gradient(180deg, #185FA5 0%, #0C447C 100%); }
.task-bar.bar-todo { background: linear-gradient(180deg, #B1B3B8 0%, #909399 100%); }
.task-bar.bar-delayed { background: linear-gradient(180deg, #D85A5A 0%, #A32D2D 100%); }
.bar-progress {
  position: absolute; left: 0; top: 0; bottom: 0;
  background: rgba(255,255,255,0.25); pointer-events: none;
}
.bar-label {
  position: relative; z-index: 1;
  color: #fff; font-size: 11px; font-weight: 500;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.milestone {
  position: absolute; top: 12px; width: 24px; height: 24px;
  background: #BA7517; transform: rotate(45deg);
  display: flex; align-items: center; justify-content: center;
  z-index: 2; box-shadow: 0 2px 4px rgba(0,0,0,0.2); cursor: pointer;
}
.milestone :deep(.el-icon) { transform: rotate(-45deg); }
.milestone-label {
  position: absolute; left: 30px; top: 0;
  font-size: 12px; color: #BA7517; font-weight: 600;
  white-space: nowrap;
}

.today-indicator {
  position: absolute; top: 60px; bottom: 0;
  width: 2px; z-index: 5; pointer-events: none;
}
.today-line {
  width: 2px; height: 100%;
  background: linear-gradient(180deg, #BA7517 0%, transparent 100%);
}
.today-text {
  position: absolute; top: -28px; left: -28px;
  background: #BA7517; color: #fff;
  padding: 2px 8px; border-radius: 4px;
  font-size: 11px; white-space: nowrap;
}
</style>
