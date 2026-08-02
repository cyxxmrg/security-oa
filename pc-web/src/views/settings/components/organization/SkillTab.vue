<template>
  <div>
    <div class="tab-toolbar">
      <el-button type="primary" :icon="Plus" @click="emit('addSkill')">新增技能</el-button>
      <el-input
        :model-value="search"
        @update:model-value="(v: string) => emit('update:search', v)"
        placeholder="搜索技能名称" clearable style="width: 240px; margin-left: 12px;"
        @input="emit('searchChange')"
      />
    </div>
    <el-table :data="paged" border stripe style="width: 100%; margin-top: 16px;">
      <el-table-column label="技能" width="200">
        <template #default="{ row }">
          <el-tag :color="row.color" effect="dark" style="color: #fff; border: none;">{{ row.name }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="category" label="分类" width="120">
        <template #default="{ row }">
          <el-tag size="small" :type="categoryType(row.category)">{{ categoryLabel(row.category) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="description" label="说明" min-width="240" show-overflow-tooltip />
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" :icon="Edit" @click="emit('edit', row)">编辑</el-button>
          <el-popconfirm :title="`确定删除技能「${row.name}」?`" @confirm="emit('delete', row)">
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

export interface SkillRow {
  id: number
  name: string
  color: string
  category: string
  description?: string
}

defineProps<{
  search: string
  paged: SkillRow[]
  page: number
  pageSize: number
  total: number
  categoryLabel: (c: string) => string
  categoryType: (c: string) => 'success' | 'warning' | 'info' | 'danger' | 'primary'
}>()
const emit = defineEmits<{
  (e: 'update:search', v: string): void
  (e: 'searchChange'): void
  (e: 'addSkill'): void
  (e: 'edit', row: SkillRow): void
  (e: 'delete', row: SkillRow): void
  (e: 'pageChange', p: number): void
  (e: 'sizeChange', s: number): void
}>()
</script>

<style lang="scss" scoped>
.tab-toolbar { margin-bottom: 16px; display: flex; gap: 8px; align-items: center; }
</style>
