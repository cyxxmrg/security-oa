<script setup lang="ts">
/**
 * ProjectProgressTable — 项目进度表 (v0.3.14 C1)
 */
defineProps<{
  projects: Array<{
    id: number | string
    name: string
    stage: string
    progress: number
    manager?: string
    deadline?: string
  }>
}>()

const emit = defineEmits<{
  (e: 'viewAll'): void
}>()

const stageTagType = (stage: string): 'info' | 'success' | 'warning' | 'danger' | 'primary' => {
  const map: Record<string, 'info' | 'success' | 'warning' | 'danger' | 'primary'> = {
    立项: 'info', 询价: 'primary', 合同: 'success', 采购: 'warning', 施工: 'danger', 结算: 'primary', 质保: 'success',
  }
  return (map[stage] || 'info') as Record<string, unknown>
}

const progressColor = (p: number): string => {
  if (p >= 80) return '#1D9E75'
  if (p >= 50) return '#185FA5'
  if (p >= 30) return '#BA7517'
  return '#A32D2D'
}
</script>

<template>
  <el-card shadow="never">
    <template #header>
      <div class="card-header">
        <span class="card-title">项目进度概览</span>
        <el-button text type="primary" @click="emit('viewAll')">查看全部</el-button>
      </div>
    </template>
    <el-table :data="projects" stripe style="width: 100%">
      <el-table-column prop="name" label="项目名称" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">
          <span>{{ row.name }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="stage" label="阶段" width="100">
        <template #default="{ row }">
          <el-tag :type="stageTagType(row.stage)" size="small">{{ row.stage }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="progress" label="进度" width="140">
        <template #default="{ row }">
          <el-progress :percentage="row.progress" :stroke-width="8" :color="progressColor(row.progress)" />
        </template>
      </el-table-column>
      <el-table-column prop="manager" label="负责人" width="80" />
      <el-table-column prop="deadline" label="截止日期" width="110" />
    </el-table>
  </el-card>
</template>

<style scoped>
.card-header {
  display: flex; justify-content: space-between; align-items: center;
  width: 100%; height: 48px;
}
.card-title {
  font-size: 15px; font-weight: 600; color: #2c3e50;
  display: flex; align-items: center; gap: 8px;
}
.card-title::before {
  content: ''; display: inline-block;
  width: 3px; height: 14px;
  background: linear-gradient(180deg, #0C447C 0%, #1D9E75 100%);
  border-radius: 2px; flex-shrink: 0;
}
</style>
