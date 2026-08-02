<script setup lang="ts">
/**
 * GanttTaskTable — 任务列表表 (v0.3.14 C3)
 * 甘特图下方的详细任务表格，支持「更新进度」
 */
import { STATUS_TAG_TYPE, STATUS_LABEL, progressStatusOf, type GanttTask } from '../ganttTypes'

defineProps<{
  tasks: GanttTask[]
}>()

const emit = defineEmits<{
  (e: 'update', t: GanttTask): void
}>()
</script>

<template>
  <el-card shadow="never" class="task-list-card">
    <h3 class="section-title">任务列表 ({{ tasks.length }})</h3>
    <el-table :data="tasks" border size="default">
      <el-table-column type="index" label="#" width="55" align="center" />
      <el-table-column prop="name" label="任务名称" min-width="200" show-overflow-tooltip />
      <el-table-column prop="category" label="分类" width="100" align="center">
        <template #default="{ row }">
          <el-tag size="small" effect="plain">{{ row.category }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="startDate" label="开始" width="110" />
      <el-table-column prop="endDate" label="结束" width="110" />
      <el-table-column prop="duration" label="工期(天)" width="90" align="center" />
      <el-table-column prop="progress" label="进度" width="160">
        <template #default="{ row }">
          <el-progress :percentage="row.progress" :status="progressStatusOf(row)" :stroke-width="10" />
        </template>
      </el-table-column>
      <el-table-column prop="owner" label="负责人" width="100" />
      <el-table-column prop="status" label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="STATUS_TAG_TYPE[row.status] || 'info'" size="small" effect="light">
            {{ STATUS_LABEL[row.status] || '-' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="160" align="center">
        <template #default="{ row }">
          <el-button type="success" link size="small" @click="emit('update', row)">更新进度</el-button>
        </template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<style scoped>
.task-list-card { margin-top: 12px; border-radius: 8px; border: none; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.section-title { font-size: 15px; font-weight: 600; color: #303133; margin: 0 0 12px; }
</style>
