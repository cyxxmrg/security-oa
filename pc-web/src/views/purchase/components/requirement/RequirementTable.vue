<template>
  <el-table
    :data="list"
    stripe
    border
    v-loading="loading"
    :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
  >
    <el-table-column type="index" label="#" width="55" align="center" fixed />
    <el-table-column prop="code" label="需求编号" width="150" fixed>
      <template #default="{ row }">
        <span class="link-text" @click="emit('view', row)">{{ row.code }}</span>
      </template>
    </el-table-column>
    <el-table-column label="关联项目" min-width="220" show-overflow-tooltip>
      <template #default="{ row }">
        <span class="link-text" @click="emit('viewProject', row)">{{ row.project_name }}</span>
      </template>
    </el-table-column>
    <el-table-column prop="material" label="需求物资" min-width="220" show-overflow-tooltip>
      <template #default="{ row }">
        <div>{{ row.material }}</div>
        <div v-if="row.spec" class="sub-text">规格：{{ row.spec }}</div>
      </template>
    </el-table-column>
    <el-table-column prop="quantity" label="数量" width="100" align="right">
      <template #default="{ row }">
        <span class="qty-text">{{ row.quantity }}</span>
        <span class="unit-text">{{ row.unit || '件' }}</span>
      </template>
    </el-table-column>
    <el-table-column prop="need_date" label="需求日期" width="120" align="center">
      <template #default="{ row }">
        <span :class="{ 'text-danger': isOverdue(row.need_date, row.status) }">
          {{ formatDate(row.need_date) }}
        </span>
      </template>
    </el-table-column>
    <el-table-column prop="priority" label="优先级" width="100" align="center">
      <template #default="{ row }">
        <el-tag :type="priorityTagType(row.priority)" effect="dark" size="small">
          {{ priorityLabel(row.priority) }}
        </el-tag>
      </template>
    </el-table-column>
    <el-table-column prop="creator" label="发起人" width="100" align="center" />
    <el-table-column prop="status" label="状态" width="100" align="center">
      <template #default="{ row }">
        <el-tag :type="statusTagType(row.status)" effect="light" size="small">
          {{ statusLabel(row.status) }}
        </el-tag>
      </template>
    </el-table-column>
    <el-table-column prop="created_at" label="发起时间" width="120" align="center">
      <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
    </el-table-column>
    <el-table-column label="操作" width="320" fixed="right">
      <template #default="{ row }">
        <el-button type="primary" link size="small" @click="emit('view', row)">查看</el-button>
        <el-tag
          v-if="row.status === 'pending'"
          size="small"
          type="info"
          effect="plain"
        >去审批中心</el-tag>
        <el-button
          v-if="row.status === 'approved'"
          type="warning"
          link
          size="small"
          @click="emit('convertToPlan', row)"
        >转采购计划</el-button>
        <el-button
          v-if="row.status === 'pending'"
          type="warning"
          link
          size="small"
          @click="emit('edit', row)"
        >编辑</el-button>
        <el-button
          v-if="row.status !== 'approved' && row.status !== 'pending'"
          type="danger"
          link
          size="small"
          @click="emit('delete', row)"
        >删除</el-button>
      </template>
    </el-table-column>
  </el-table>
</template>

<script setup lang="ts">
import type { Requirement } from './types'
import { formatDate, statusLabel, statusTagType, priorityLabel, priorityTagType, isOverdue } from './types'

// v0.3.23 抽自 purchase/Requirement.vue:52-114
defineProps<{
  list: Requirement[]
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'view', row: Requirement): void
  (e: 'viewProject', row: Requirement): void
  (e: 'edit', row: Requirement): void
  (e: 'delete', row: Requirement): void
  (e: 'approve', row: Requirement): void
  (e: 'convertToPlan', row: Requirement): void
}>()
</script>

<style lang="scss" scoped>
.sub-text { font-size: 11px; color: #909399; }
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; &:hover { text-decoration: underline; } }
.text-danger { color: #A32D2D; font-weight: 600; }
.qty-text { font-weight: 600; margin-right: 4px; }
.unit-text { color: #909399; font-size: 12px; }
</style>
