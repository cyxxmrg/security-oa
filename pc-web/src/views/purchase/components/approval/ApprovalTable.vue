<template>
  <div>
    <el-table :data="list" stripe border v-loading="loading" :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }">
      <el-table-column width="140" fixed>
        <template #default="{ row }">
          {{ row.code || '-' }}
        </template>
      </el-table-column>
      <el-table-column min-width="200" show-overflow-tooltip>
        <template #default="{ row }">
          {{ row.title || '-' }}
        </template>
      </el-table-column>
      <el-table-column width="100" align="center">
        <template #default="{ row }">
          {{ row.status || '-' }}
        </template>
      </el-table-column>
      <el-table-column width="160" align="center">
        <template #default="{ row }">
          {{ row.created_at || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="emit('view', row)">查看</el-button>
          <el-button link type="warning" @click="emit('edit', row)">编辑</el-button>
          <el-button link type="danger" @click="emit('delete', row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <div class="pagination-wrapper">
      <el-pagination
        :current-page="page" :page-size="pageSize"
        :page-sizes="[5, 10, 20]" :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @current-change="(p: number) => emit('pageChange', p)"
        @size-change="(s: number) => emit('sizeChange', s)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PurchaseApproval } from '../../types'

defineProps<{
  list: PurchaseApproval[]
  loading: boolean
  page: number
  pageSize: number
  total: number
}>()
const emit = defineEmits<{
  (e: 'view', row: PurchaseApproval): void
  (e: 'edit', row: PurchaseApproval): void
  (e: 'delete', row: PurchaseApproval): void
  (e: 'pageChange', p: number): void
  (e: 'sizeChange', s: number): void
}>()
</script>

<style lang="scss" scoped>
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
