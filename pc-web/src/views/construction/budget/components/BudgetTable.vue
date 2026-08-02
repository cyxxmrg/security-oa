<template>
  <div>
    <el-table
      :data="data"
      stripe
      border
      v-loading="loading"
      :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
    >
      <el-table-column prop="code" label="预算编号" width="160" fixed show-overflow-tooltip />
      <el-table-column label="项目" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">
          <span>{{ row.project?.name || row.project?.code || row.project_id || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="version" label="版本" width="80" align="center" />
      <el-table-column label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="statusTagType(row.status)" effect="plain" size="small">
            {{ statusLabel(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="总预算" width="140" align="right">
        <template #default="{ row }">
          <span style="color:#0C447C;font-weight:600">¥ {{ formatMoney(row.total_amount) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="实际成本" width="140" align="right">
        <template #default="{ row }">
          <span>¥ {{ formatMoney(row.actual_amount) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="使用率" width="180" align="center">
        <template #default="{ row }">
          <div class="rate-cell">
            <el-progress
              :percentage="ratePercent(row?.usage_rate)"
              :color="getRateColor(row.usage_rate)"
              :stroke-width="10"
              :format="(p: number) => `${p}%`"
              style="width: 110px"
            />
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="creator?.name" label="创建人" width="100" align="center">
        <template #default="{ row }">
          {{ row.creator?.name || row.created_by || '-' }}
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="创建时间" width="160" align="center" show-overflow-tooltip />
      <el-table-column label="操作" width="280" fixed="right" align="center">
        <template #default="{ row }">
          <el-button link type="primary" :icon="View" @click="emit('view', row)">查看</el-button>
          <el-button
            v-if="row.status === 'draft'"
            link
            type="warning"
            :icon="Edit"
            @click="emit('edit', row)"
          >编辑</el-button>
          <el-button
            v-if="row.status === 'draft'"
            link
            type="success"
            :icon="Check"
            @click="emit('approve', row)"
          >提交审批</el-button>
          <el-button
            v-if="row.status === 'approved'"
            link
            type="primary"
            :icon="Refresh"
            @click="emit('revise', row)"
          >修订</el-button>
          <el-button
            v-if="row.status === 'draft'"
            link
            type="danger"
            :icon="Delete"
            @click="emit('delete', row)"
          >删除</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { View, Edit, Check, Delete, Refresh } from '@element-plus/icons-vue'
import { Budget, TagType } from '../types'

defineProps<{
  data: Budget[]
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'view', row: Budget): void
  (e: 'edit', row: Budget): void
  (e: 'approve', row: Budget): void
  (e: 'revise', row: Budget): void
  (e: 'delete', row: Budget): void
}>()

// 状态：draft/approved/revised/voided
const STATUS_MAP: Record<string, { label: string; tagType: TagType }> = {
  draft:    { label: '草稿',   tagType: 'info' },
  approved: { label: '已审批', tagType: 'success' },
  revised:  { label: '已修订', tagType: 'warning' },
  voided:   { label: '已作废', tagType: 'danger' },
}
const statusLabel = (s?: string) => STATUS_MAP[s || '']?.label || s || '-'
const statusTagType = (s?: string): TagType => STATUS_MAP[s || '']?.tagType || 'info'

const formatMoney = (n?: number | string | null) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const ratePercent = (rate?: number | string | null) => {
  const r = Number(rate || 0)
  return Math.min(Math.max(Math.round(r * 100), 0), 100)
}
const getRateColor = (rate?: number | string | null): string => {
  const r = Number(rate || 0)
  if (r >= 1.0) return '#f56c6c'
  if (r >= 0.9) return '#e6a23c'
  return '#67c23a'
}
</script>

<style lang="scss" scoped>
.rate-cell { display: flex; justify-content: center; }
</style>
