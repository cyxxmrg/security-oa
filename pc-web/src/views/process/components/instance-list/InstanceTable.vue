<template>
  <el-table
    :data="list"
    stripe
    border
    v-loading="loading"
    :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
    :cell-style="{ height: '50px', padding: '4px 0' }"
    style="width: 100%"
  >
    <el-table-column prop="id" label="编号" width="70" align="center">
      <template #default="{ row }">
        <span class="id-text">#{{ row.id }}</span>
      </template>
    </el-table-column>
    <el-table-column label="项目名称" min-width="160" show-overflow-tooltip>
      <template #default="{ row }">
        <span class="link-text" @click="emit('viewProject', row as Instance)">{{ (row as Instance).project?.name || (row as Instance).project_name || '-' }}</span>
      </template>
    </el-table-column>
    <el-table-column label="工序名称" min-width="160" show-overflow-tooltip>
      <template #default="{ row }">
        <span class="link-text" @click="emit('view', row as Instance)">{{ (row as Instance).name || (row as Instance).template?.name || (row as Instance).template_name || '-' }}</span>
      </template>
    </el-table-column>
    <el-table-column label="工序编号" min-width="130" show-overflow-tooltip>
      <template #default="{ row }">
        <span class="code-text">{{ (row as Instance).code || (row as Instance).template?.code || '-' }}</span>
      </template>
    </el-table-column>
    <el-table-column label="负责人" min-width="100">
      <template #default="{ row }">
        <span>{{ (row as Instance).foreman?.name || (row as Instance).assignee_name || (row as Instance).foreman_name || '-' }}</span>
      </template>
    </el-table-column>
    <el-table-column label="进度" width="160">
      <template #default="{ row }">
        <el-progress
          :percentage="(row as Instance).progress || 0"
          :color="progressColor((row as Instance).progress)"
          :stroke-width="12"
          :format="(p: number) => p + '%'"
        />
      </template>
    </el-table-column>
    <el-table-column label="计划工期" min-width="200">
      <template #default="{ row }">
        <span class="date-text">
          {{ formatDateRange((row as Instance).planned_start_date || (row as Instance).planned_start, (row as Instance).planned_end_date || (row as Instance).planned_end) }}
        </span>
      </template>
    </el-table-column>
    <el-table-column label="实际工期" min-width="200">
      <template #default="{ row }">
        <span class="date-text">
          <template v-if="(row as Instance).actual_start_date || (row as Instance).actual_end_date || (row as Instance).actual_start || (row as Instance).actual_end">
            {{ formatDateRange((row as Instance).actual_start_date || (row as Instance).actual_start, (row as Instance).actual_end_date || (row as Instance).actual_end) }}
          </template>
          <span v-else class="muted">-</span>
        </span>
      </template>
    </el-table-column>
    <el-table-column label="状态" width="110" align="center">
      <template #default="{ row }">
        <el-tag :type="statusTagType((row as Instance).status)" effect="light" size="small">
          <el-icon v-if="(row as Instance).is_overdue" :size="11" style="vertical-align: -1px; margin-right: 2px"><Warning /></el-icon>
          {{ statusLabel((row as Instance).status) }}
        </el-tag>
      </template>
    </el-table-column>
    <el-table-column label="操作" width="280" fixed="right" align="center">
      <template #default="{ row }">
        <el-button link type="primary" size="small" @click="emit('view', row as Instance)">详情</el-button>
        <el-button
          v-if="(row as Instance).status === 'in_progress' || (row as Instance).status === 'pending' || (row as Instance).is_overdue"
          link
          type="success"
          size="small"
          @click="emit('accept', row as Instance)"
        >接受</el-button>
        <el-button
          v-if="(row as Instance).status === 'in_progress' || (row as Instance).status === 'pending'"
          link
          type="danger"
          size="small"
          @click="emit('reject', row as Instance)"
        >驳回</el-button>
        <el-button
          v-if="(row as Instance).status === 'in_progress'"
          link
          type="warning"
          size="small"
          @click="emit('progress', row as Instance)"
        >更新进度</el-button>
      </template>
    </el-table-column>
  </el-table>
</template>

<script setup lang="ts">
import { Warning } from '@element-plus/icons-vue'
import type { Instance } from './types'
import { progressColor, statusLabel, statusTagType, STATUS_TAG_TYPE_MAP } from './types'

// v0.3.19 抽自 process/InstanceList.vue:50-141
defineProps<{
  list: Instance[]
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'view', row: Instance): void
  (e: 'viewProject', row: Instance): void
  (e: 'accept', row: Instance): void
  (e: 'reject', row: Instance): void
  (e: 'progress', row: Instance): void
}>()

// 日期范围渲染 (start/end 都可能是空)
const formatDateRange = (start?: string | null, end?: string | null): string => {
  const s = (start || '').slice(0, 10) || '-'
  const e = (end || '').slice(0, 10) || '-'
  return `${s} ~ ${e}`
}
</script>

<style lang="scss" scoped>
.id-text {
  font-family: 'SF Mono', Consolas, monospace;
  color: #0C447C; font-weight: 600;
}
.link-text {
  color: #0C447C; cursor: pointer; font-weight: 500;
  &:hover { text-decoration: underline; }
}
.date-text {
  font-family: 'SF Mono', Consolas, monospace;
  font-size: 12px; color: #606266;
  .date-sep { color: #c0c4cc; margin: 0 4px; }
}
.muted { color: #c0c4cc; }
.code-text { font-family: 'SF Mono', Consolas, monospace; color: #606266; font-size: 12px; }
</style>
