<script setup lang="ts">
/**
 * GanttOverviewCard — 项目概览卡 (v0.3.14 C3)
 * 8 字段：项目名 / 开始 / 结束 / 总工期 / 完成 N / 进行中 / 未开始 / 整体进度
 */
import { ArrowLeft, Printer, Download, Flag, Refresh, ZoomIn, ZoomOut } from '@element-plus/icons-vue'
import type { TaskStatus } from '../ganttTypes'

const props = withDefaults(
  defineProps<{
    projectName: string
    projectStart: string
    projectEnd: string
    totalDays: number
    finishedCount: number
    inProgressCount: number
    notStartedCount: number
    overallProgress: number
    tasksLength: number
    viewMode: 'day' | 'week' | 'month'
    zoom: number
  }>(),
  { tasksLength: 0 },
)

const emit = defineEmits<{
  (e: 'update:viewMode', v: 'day' | 'week' | 'month'): void
  (e: 'update:zoom', v: number): void
  (e: 'back'): void
  (e: 'print'): void
  (e: 'export'): void
  (e: 'refresh'): void
}>()

const setView = (v: 'day' | 'week' | 'month') => emit('update:viewMode', v)
const zoomIn = () => emit('update:zoom', Math.min(props.zoom + 0.1, 2))
const zoomOut = () => emit('update:zoom', Math.max(props.zoom - 0.1, 0.5))
</script>

<template>
  <div class="page-header">
    <div class="title-area">
      <el-button :icon="ArrowLeft" text @click="emit('back')">返回</el-button>
      <span class="page-title">施工甘特图</span>
      <el-tag effect="light" type="info">{{ projectName }}</el-tag>
    </div>
    <div class="header-actions">
      <el-radio-group :model-value="viewMode" @update:model-value="(v: string) => setView(v)" size="default">
        <el-radio-button label="day">日</el-radio-button>
        <el-radio-button label="week">周</el-radio-button>
        <el-radio-button label="month">月</el-radio-button>
      </el-radio-group>
      <el-button :icon="Printer" @click="emit('print')">打印</el-button>
      <el-button type="primary" :icon="Download" @click="emit('export')">导出</el-button>
    </div>
  </div>

  <div class="overview-card">
    <el-row :gutter="0">
      <el-col :span="6" class="ov-item">
        <div class="ov-label">项目名称</div>
        <div class="ov-value">{{ projectName }}</div>
      </el-col>
      <el-col :span="6" class="ov-item">
        <div class="ov-label">开始时间</div>
        <div class="ov-value">{{ projectStart }}</div>
      </el-col>
      <el-col :span="6" class="ov-item">
        <div class="ov-label">结束时间</div>
        <div class="ov-value">{{ projectEnd }}</div>
      </el-col>
      <el-col :span="6" class="ov-item">
        <div class="ov-label">总工期</div>
        <div class="ov-value">{{ totalDays }} 天</div>
      </el-col>
    </el-row>
    <el-row :gutter="0">
      <el-col :span="6" class="ov-item">
        <div class="ov-label">已完成任务</div>
        <div class="ov-value text-success">{{ finishedCount }} / {{ tasksLength }}</div>
      </el-col>
      <el-col :span="6" class="ov-item">
        <div class="ov-label">进行中</div>
        <div class="ov-value text-warning">{{ inProgressCount }} 项</div>
      </el-col>
      <el-col :span="6" class="ov-item">
        <div class="ov-label">未开始</div>
        <div class="ov-value text-info">{{ notStartedCount }} 项</div>
      </el-col>
      <el-col :span="6" class="ov-item">
        <div class="ov-label">整体进度</div>
        <div class="ov-value">
          <el-progress :percentage="overallProgress" :stroke-width="14" />
        </div>
      </el-col>
    </el-row>
  </div>

  <div class="legend-bar">
    <span class="legend-title">图例：</span>
    <span class="legend-item"><span class="dot" style="background: #1D9E75"></span>已完成</span>
    <span class="legend-item"><span class="dot" style="background: #0C447C"></span>进行中</span>
    <span class="legend-item"><span class="dot" style="background: #909399"></span>未开始</span>
    <span class="legend-item"><span class="dot" style="background: #A32D2D"></span>延期</span>
    <span class="legend-item">
      <el-icon color="#BA7517" style="vertical-align: -2px"><Flag /></el-icon>
      里程碑
    </span>
    <div class="legend-actions">
      <el-button-group>
        <el-button :icon="Refresh" size="small" @click="emit('refresh')">刷新</el-button>
        <el-button :icon="ZoomIn" size="small" @click="zoomIn">放大</el-button>
        <el-button :icon="ZoomOut" size="small" @click="zoomOut">缩小</el-button>
      </el-button-group>
    </div>
  </div>
</template>

<style scoped>
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 12px; padding: 12px 16px;
  background: #fff; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.title-area { display: flex; align-items: center; gap: 10px; }
.page-title { font-size: 18px; font-weight: 700; color: #303133; }
.header-actions { display: flex; gap: 8px; }

.overview-card {
  background: #fff; border-radius: 8px; padding: 16px 20px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  margin-bottom: 12px;
}
.ov-item { padding: 8px 12px; }
.ov-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.ov-value { font-size: 16px; font-weight: 600; color: #303133; }
.ov-value.text-success { color: #1D9E75; }
.ov-value.text-warning { color: #BA7517; }
.ov-value.text-info    { color: #909399; }

.legend-bar {
  display: flex; align-items: center; gap: 12px;
  padding: 8px 16px; margin-bottom: 8px;
  background: #fff; border-radius: 6px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  flex-wrap: wrap;
}
.legend-title { font-size: 12px; color: #606266; font-weight: 500; }
.legend-item {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; color: #606266;
}
.legend-item .dot {
  display: inline-block; width: 10px; height: 10px;
  border-radius: 50%; margin-right: 2px;
}
.legend-actions { margin-left: auto; }
</style>
