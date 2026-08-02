<template>
  <div>
    <div class="tab-toolbar">
      <el-button type="primary" :icon="Plus" @click="emit('addPosition')">新增岗位</el-button>
      <el-input
        :model-value="search"
        @update:model-value="(v: string) => emit('update:search', v)"
        placeholder="搜索岗位名称" clearable style="width: 240px; margin-left: 12px;"
        @input="emit('searchChange')"
      />
    </div>
    <el-table :data="paged" border stripe style="width: 100%; margin-top: 16px;">
      <el-table-column prop="name"       label="岗位名称" width="180" />
      <el-table-column prop="department" label="所属部门" width="200" />
      <el-table-column prop="level"      label="级别"     width="80"  align="center" />
      <el-table-column prop="count"      label="人数"     width="100" align="center" />
      <el-table-column prop="description" label="岗位描述" min-width="240" />
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" :icon="Edit" @click="emit('edit', row)">编辑</el-button>
          <el-popconfirm :title="`确定删除岗位「${row.name}」?`" @confirm="emit('delete', row)">
            <template #reference>
              <el-button link type="danger" size="small" :icon="Delete">删除</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
    <el-pagination
      :current-page="page"
      :page-size="pageSize"
      :total="total"
      :page-sizes="[10, 20, 50]"
      layout="total, sizes, prev, pager, next"
      style="margin-top: 16px; justify-content: flex-end"
      @current-change="(p: number) => emit('pageChange', p)"
      @size-change="(s: number) => emit('sizeChange', s)"
    />
  </div>
</template>

<script setup lang="ts">
import { Plus, Edit, Delete } from '@element-plus/icons-vue'

export interface PositionRow {
  id: number
  name: string
  department_id?: number
  department?: string
  level?: string | number
  count?: number
  description?: string
}

defineProps<{
  search: string
  paged: PositionRow[]
  page: number
  pageSize: number
  total: number
}>()
const emit = defineEmits<{
  (e: 'update:search', v: string): void
  (e: 'searchChange'): void
  (e: 'addPosition'): void
  (e: 'edit', row: PositionRow): void
  (e: 'delete', row: PositionRow): void
  (e: 'pageChange', p: number): void
  (e: 'sizeChange', s: number): void
}>()
</script>

<style lang="scss" scoped>
.tab-toolbar { margin-bottom: 16px; display: flex; gap: 8px; align-items: center; }
</style>
