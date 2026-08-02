<template>
  <el-table
    :data="list"
    stripe
    border
    v-loading="loading"
    :row-style="{ height: '50px' }"
    :cell-style="{ padding: '6px 0' }"
    :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
  >
    <el-table-column prop="id" label="编号" width="70" align="center" />
    <el-table-column label="项目类型" width="110" align="center">
      <template #default="{ row }">
        <el-tag
          :color="industryColor(row.industry).bg"
          :style="{ color: industryColor(row.industry).color, borderColor: industryColor(row.industry).color, fontWeight: 500 }"
          effect="light"
          size="small"
        >
          {{ INDUSTRY_MAP[row.industry] || row.industry }}
        </el-tag>
      </template>
    </el-table-column>
    <el-table-column prop="category" label="子分类" width="120" align="center" show-overflow-tooltip />
    <el-table-column prop="code" label="编码" width="110" />
    <el-table-column prop="name" label="名称" min-width="180" show-overflow-tooltip />
    <el-table-column prop="sort_order" label="排序" width="70" align="center" />
    <el-table-column prop="standard_duration_days" label="工期(天)" width="90" align="center">
      <template #default="{ row }">
        <span>{{ (row as ProcessTemplate).standard_duration_days ?? '-' }}</span>
      </template>
    </el-table-column>
    <el-table-column prop="standard_man_hours" label="工时" width="80" align="center">
      <template #default="{ row }">
        <span>{{ (row as ProcessTemplate).standard_man_hours ?? 0 }}</span>
      </template>
    </el-table-column>
    <el-table-column label="所需资质" min-width="160" show-overflow-tooltip>
      <template #default="{ row }">
        <span v-if="(row as ProcessTemplate).required_qualifications && (row as ProcessTemplate).required_qualifications!.length">
          <el-tag v-for="q in (row as ProcessTemplate).required_qualifications" :key="q" size="small" type="info" effect="plain" class="quali-tag">{{ q }}</el-tag>
        </span>
        <span v-else class="muted">-</span>
      </template>
    </el-table-column>
    <el-table-column label="状态" width="90" align="center">
      <template #default="{ row }">
        <el-switch
          v-model="(row as ProcessTemplate).is_active"
          :loading="(row as ProcessTemplate)._statusLoading"
          inline-prompt
          active-text="启"
          inactive-text="停"
          @change="(v: unknown) => emit('statusChange', row as ProcessTemplate, v)"
        />
      </template>
    </el-table-column>
    <el-table-column label="创建时间" width="160">
      <template #default="{ row }">
        <span>{{ formatDate(row.created_at) }}</span>
      </template>
    </el-table-column>
    <el-table-column label="操作" width="170" align="center" fixed="right">
      <template #default="{ row }">
        <el-button link type="primary" :icon="Edit" @click="emit('edit', row as ProcessTemplate)">编辑</el-button>
        <el-popconfirm
          :title="`确定要删除「${(row as ProcessTemplate).name}」吗?`"
          confirm-button-text="删除"
          cancel-button-text="取消"
          confirm-button-type="danger"
          width="220"
          @confirm="emit('delete', row as ProcessTemplate)"
        >
          <template #reference>
            <el-button link type="danger" :icon="Delete">删除</el-button>
          </template>
        </el-popconfirm>
      </template>
    </el-table-column>
  </el-table>
</template>

<script setup lang="ts">
import { Edit, Delete } from '@element-plus/icons-vue'
import type { ProcessTemplate } from './types'
import { INDUSTRY_MAP, INDUSTRY_COLORS, formatDate } from './types'

// v1.2.12p 字段对齐后端: is_active / standard_duration_days / standard_man_hours / required_qualifications
defineProps<{
  list: ProcessTemplate[]
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'edit', row: ProcessTemplate): void
  (e: 'delete', row: ProcessTemplate): void
  (e: 'statusChange', row: ProcessTemplate, val: unknown): void
}>()

function industryColor(industry: string): { bg: string; color: string } {
  return INDUSTRY_COLORS[industry] || { bg: '#f1f5f9', color: '#475569' }
}
</script>

<style lang="scss" scoped>
.muted { color: #c0c4cc; }
.quali-tag { margin-right: 4px; margin-bottom: 2px; }
</style>