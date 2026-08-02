<template>
  <div class="tab-content">
    <el-table :data="projects" border empty-text="暂无关联项目">
      <el-table-column prop="code" label="项目编号" width="160" />
      <el-table-column prop="name" label="项目名称" min-width="200" />
      <el-table-column prop="type" label="类型" width="100" align="center">
        <template #default="{ row }">
          <el-tag size="small" effect="plain">{{ row.type }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="stage" label="当前阶段" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="stageType(row.stage)" size="small">{{ row.stage }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="amount" label="合同金额" width="120" align="right">
        <template #default="{ row }">¥{{ (row.amount || 0).toLocaleString() }}</template>
      </el-table-column>
      <el-table-column prop="start_date" label="开始日期" width="120" />
      <el-table-column prop="end_date" label="计划完成" width="120" />
      <el-table-column label="操作" width="160" align="center">
        <template #default="{ row }">
          <el-button type="primary" link size="small" @click="emit('view', row)">查看</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import type { Project } from './types'
import { stageType } from './types'

// v0.3.20 抽自 customer/Detail.vue:125-153
defineProps<{
  projects: Project[]
}>()

const emit = defineEmits<{
  (e: 'view', project: Project): void
}>()
</script>
