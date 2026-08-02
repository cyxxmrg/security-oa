<template>
  <div class="content-card">
    <div class="table-header">
      <span class="table-title">验收记录列表</span>
      <span class="table-meta">共 {{ total }} 条</span>
    </div>

    <el-table
      v-loading="loading"
      :data="list"
      stripe
      border
      style="width: 100%"
      :row-style="{ height: '50px' }"
      :cell-style="{ padding: '0 8px' }"
      :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
    >
      <el-table-column prop="id" label="编号" width="70" align="center" />
      <el-table-column label="项目名称" min-width="180" show-overflow-tooltip>
        <template #default="{ row }">{{ getProjectName(row) }}</template>
      </el-table-column>
      <el-table-column label="项目编号" min-width="130" show-overflow-tooltip>
        <template #default="{ row }">{{ getProjectNo(row) }}</template>
      </el-table-column>
      <el-table-column label="工序名称" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ getInstanceName(row) }}</template>
      </el-table-column>
      <el-table-column label="工序编号" min-width="110" show-overflow-tooltip>
        <template #default="{ row }">{{ getInstanceCode(row) }}</template>
      </el-table-column>
      <el-table-column label="验收类型" width="90" align="center">
        <template #default="{ row }">{{ inspectionTypeLabel(row.inspection_type) }}</template>
      </el-table-column>
      <el-table-column label="验收人" min-width="100" show-overflow-tooltip>
        <template #default="{ row }">{{ getInspectorName(row) }}</template>
      </el-table-column>
      <el-table-column label="验收时间" min-width="160">
        <template #default="{ row }">{{ formatDate(row.inspection_date) }}</template>
      </el-table-column>
      <el-table-column label="结果" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="resultTagType(row.result)" effect="dark" size="small">
            {{ resultLabel(row.result) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="评分" width="80" align="center">
        <template #default="{ row }">{{ row.score ?? '-' }}</template>
      </el-table-column>
      <el-table-column label="备注" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">
          <span v-if="row.remark">{{ row.remark }}</span>
          <span v-else class="muted">-</span>
        </template>
      </el-table-column>
      <el-table-column label="整改项" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">
          <span v-if="formatIssues(row.issues)" class="defect-text">{{ formatIssues(row.issues) }}</span>
          <span v-else class="muted">-</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="100" align="center" fixed="right">
        <template #default="{ row }">
          <el-button
            link
            type="primary"
            size="small"
            :disabled="!row.process_instance_id"
            @click="emit('view', row as unknown as Inspection)"
          >
            查看详情
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap">
      <el-pagination
        :current-page="page"
        :page-size="perPage"
        :page-sizes="[10, 20, 50, 100]"
        :total="total"
        background
        layout="total, sizes, prev, pager, next, jumper"
        @current-change="(p: number) => emit('pageChange', p)"
        @size-change="(s: number) => emit('sizeChange', s)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Inspection } from './types'
import { formatDate, resultLabel, resultTagType, inspectionTypeLabel, formatIssues } from './types'

// V1.2.13: 辅助函数提取关联字段
// 后端 Laravel snake_case: processInstance() -> process_instance, inspector() -> inspector
type AnyRow = Record<string, unknown>
const getProjectName = (row: AnyRow): string => {
  const pi = row.process_instance as { project?: { name?: string } } | undefined
  return pi?.project?.name || '-'
}
const getProjectNo = (row: AnyRow): string => {
  const pi = row.process_instance as { project?: { project_no?: string } } | undefined
  return pi?.project?.project_no || '-'
}
const getInstanceName = (row: AnyRow): string => {
  return (row.process_instance as { name?: string } | undefined)?.name || '-'
}
const getInstanceCode = (row: AnyRow): string => {
  return (row.process_instance as { code?: string } | undefined)?.code || '-'
}
const getInspectorName = (row: AnyRow): string => {
  return (row.inspector as { name?: string } | undefined)?.name || '-'
}

defineProps<{
  list: Inspection[]
  loading: boolean
  total: number
  page: number
  perPage: number
}>()

const emit = defineEmits<{
  (e: 'view', row: Inspection): void
  (e: 'pageChange', p: number): void
  (e: 'sizeChange', s: number): void
}>()
</script>

<style lang="scss" scoped>
.content-card {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.table-title {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
  border-left: 3px solid #0C447C;
  padding-left: 8px;
}
.table-meta {
  font-size: 12px;
  color: #909399;
}
.muted { color: #c0c4cc; }
.defect-text { color: #A32D2D; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>