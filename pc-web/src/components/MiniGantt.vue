<script setup lang="ts">
import { statusLabel as commonStatusLabel } from '@/utils/labels'
/**
 * V0.4.9 A1 - MiniGantt 简化甘特图组件
 *
 * 用途: 项目详情施工甘特 tab 显示 processInstances 的计划/实际时间
 * 不用 ECharts Gantt, 纯 CSS flex 简化实现
 *
 * Props:
 *   - instances: ProcessInstance[]  (含 planned_start_date/planned_end_date/actual_start_date/actual_end_date)
 *   - rangeStart/End: 时间窗口 (默认自动算 min/max + padding)
 */
import { computed } from 'vue'

interface Instance {
  id: number | string
  code?: string
  name: string
  status: string
  progress?: number
  planned_start_date?: string | null
  planned_end_date?: string | null
  actual_start_date?: string | null
  actual_end_date?: string | null
  foreman?: { name: string } | null
}

const props = withDefaults(defineProps<{
  instances: Instance[]
}>(), {
  instances: () => [],
})

// 计算时间窗口 (含 padding)
const range = computed(() => {
  if (props.instances.length === 0) {
    const now = new Date()
    return { start: now, end: new Date(now.getTime() + 30 * 86400000), totalDays: 30 }
  }
  let minMs = Infinity
  let maxMs = -Infinity
  for (const inst of props.instances) {
    for (const d of [inst.planned_start_date, inst.actual_start_date]) {
      if (d) {
        const ms = new Date(d).getTime()
        if (ms < minMs) minMs = ms
      }
    }
    for (const d of [inst.planned_end_date, inst.actual_end_date]) {
      if (d) {
        const ms = new Date(d).getTime()
        if (ms > maxMs) maxMs = ms
      }
    }
  }
  if (minMs === Infinity || maxMs === -Infinity) {
    const now = new Date()
    return { start: now, end: new Date(now.getTime() + 30 * 86400000), totalDays: 30 }
  }
  // padding 3 天
  const start = new Date(minMs - 3 * 86400000)
  const end = new Date(maxMs + 3 * 86400000)
  const totalDays = Math.ceil((end.getTime() - start.getTime()) / 86400000)
  return { start, end, totalDays }
})

// 月份分隔标
const monthTicks = computed(() => {
  const ticks: { label: string; left: number }[] = []
  const cur = new Date(range.value.start)
  cur.setDate(1)
  cur.setHours(0, 0, 0, 0)
  while (cur.getTime() <= range.value.end.getTime()) {
    const left = ((cur.getTime() - range.value.start.getTime()) / (range.value.totalDays * 86400000)) * 100
    if (left >= 0 && left <= 100) {
      ticks.push({ label: `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}`, left })
    }
    cur.setMonth(cur.getMonth() + 1)
  }
  return ticks
})

// 单条 bar 计算
function getBar(inst: Instance, type: 'planned' | 'actual') {
  const startKey = type === 'planned' ? 'planned_start_date' : 'actual_start_date'
  const endKey   = type === 'planned' ? 'planned_end_date'   : 'actual_end_date'
  const start = inst[startKey]
  const end   = inst[endKey]
  if (!start || !end) return null
  const startMs = new Date(start).getTime()
  const endMs   = new Date(end).getTime()
  const totalMs = range.value.totalDays * 86400000
  const left  = ((startMs - range.value.start.getTime()) / totalMs) * 100
  const width = Math.max(((endMs - startMs) / totalMs) * 100, 0.6)
  return { left, width }
}

const STATUS_COLOR: Record<string, string> = {
  pending: '#909399',
  in_progress: '#409EFF',
  completed: '#67C23A',
  accepted: '#1D9E75',
  rejected: '#F56C6C',
  blocked: '#E6A23C',
}

const STATUS_LABEL: Record<string, string> = {
  pending: '待开始',
  in_progress: '进行中',
  completed: '已完成',
  accepted: '已验收',
  rejected: '已驳回',
  blocked: '阻塞',
}
</script>

<template>
  <div class="mini-gantt">
    <!-- 头部: 月份标尺 -->
    <div class="gantt-timeline">
      <div
        v-for="t in monthTicks"
        :key="t.label"
        class="gantt-tick"
        :style="{ left: t.left + '%' }"
      >{{ t.label }}</div>
    </div>

    <!-- 主体: 每行一个工序 -->
    <div class="gantt-rows">
      <div
        v-for="inst in instances"
        :key="inst.id"
        class="gantt-row"
      >
        <!-- 左侧工序名 (固定 200px) -->
        <div class="gantt-row-label">
          <div class="row-name" :title="inst.name">{{ inst.name }}</div>
          <div class="row-meta">
            <el-tag
              :type="STATUS_COLOR[inst.status] ? 'primary' : 'info'"
              size="small"
              effect="plain"
              :color="STATUS_COLOR[inst.status] || '#909399'"
              style="color: #fff; border: none"
            >{{ STATUS_LABEL[inst.status] || commonStatusLabel(inst.status) }}</el-tag>
            <span v-if="inst.progress != null" class="row-progress">{{ inst.progress }}%</span>
          </div>
        </div>
        <!-- 右侧甘特区 -->
        <div class="gantt-row-track">
          <!-- 计划 bar (半透明背景) -->
          <div
            v-if="getBar(inst, 'planned')"
            class="gantt-bar planned"
            :style="{
              left: getBar(inst, 'planned')!.left + '%',
              width: getBar(inst, 'planned')!.width + '%',
            }"
          >
            <span class="bar-text">计划</span>
          </div>
          <!-- 实际 bar (实色) -->
          <div
            v-if="getBar(inst, 'actual')"
            class="gantt-bar actual"
            :style="{
              left: getBar(inst, 'actual')!.left + '%',
              width: getBar(inst, 'actual')!.width + '%',
              background: STATUS_COLOR[inst.status] || '#409EFF',
            }"
          >
            <span class="bar-text">实际</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 图例 -->
    <div class="gantt-legend">
      <span><i class="dot planned" /> 计划</span>
      <span><i class="dot actual" /> 实际</span>
      <span class="meta">共 {{ instances.length }} 条工序 · 跨度 {{ range.totalDays }} 天</span>
    </div>
  </div>
</template>

<style scoped>
.mini-gantt {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  overflow: hidden;
}

/* 月份标尺 */
.gantt-timeline {
  position: relative;
  height: 28px;
  background: #f5f7fa;
  border-bottom: 1px solid #ebeef5;
  margin-left: 220px;
}
.gantt-tick {
  position: absolute;
  top: 0;
  height: 100%;
  border-left: 1px dashed #c0c4cc;
  padding-left: 4px;
  font-size: 11px;
  color: #606266;
  line-height: 28px;
  white-space: nowrap;
}

/* 行 */
.gantt-rows {
  max-height: 400px;
  overflow-y: auto;
}
.gantt-row {
  display: flex;
  align-items: center;
  border-bottom: 1px solid #f0f0f0;
  min-height: 56px;
  transition: background 0.2s;
}
.gantt-row:hover {
  background: #fafbfc;
}
.gantt-row-label {
  flex: 0 0 220px;
  padding: 8px 12px;
  border-right: 1px solid #ebeef5;
  background: #fafbfc;
}
.row-name {
  font-size: 13px;
  color: #303133;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 4px;
}
.row-meta {
  display: flex;
  align-items: center;
  gap: 6px;
}
.row-progress {
  font-size: 11px;
  color: #909399;
}

/* 甘特区 */
.gantt-row-track {
  position: relative;
  flex: 1;
  height: 100%;
  min-height: 56px;
  background: linear-gradient(to right, transparent 0, transparent calc(100%/12 - 1px), #f0f0f0 calc(100%/12 - 1px), #f0f0f0 calc(100%/12), transparent calc(100%/12)) 0 0 / 8.333% 100%;
}
.gantt-bar {
  position: absolute;
  height: 14px;
  border-radius: 3px;
  display: flex;
  align-items: center;
  padding: 0 6px;
  color: #fff;
  font-size: 10px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: transform 0.2s;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}
.gantt-bar:hover {
  transform: scaleY(1.1);
  z-index: 1;
}
.gantt-bar.planned {
  top: 12px;
  background: rgba(64, 158, 255, 0.25);
  border: 1px dashed #409EFF;
  color: #409EFF;
  height: 12px;
}
.gantt-bar.actual {
  top: 30px;
  height: 14px;
}
.bar-text {
  font-weight: 500;
}

/* 图例 */
.gantt-legend {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 10px 14px;
  border-top: 1px solid #ebeef5;
  font-size: 12px;
  color: #606266;
  background: #fafbfc;
}
.dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 2px;
  margin-right: 4px;
  vertical-align: middle;
}
.dot.planned {
  background: rgba(64, 158, 255, 0.25);
  border: 1px dashed #409EFF;
}
.dot.actual {
  background: #67C23A;
}
.meta {
  margin-left: auto;
  color: #909399;
}
</style>
