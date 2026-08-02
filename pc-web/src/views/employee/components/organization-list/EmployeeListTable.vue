<template>
  <el-table
    :data="list"
    stripe
    v-loading="loading"
    style="width: 100%"
    :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
  >
    <el-table-column type="index" label="#" width="55" />
    <el-table-column prop="username" label="账号" min-width="100" />
    <el-table-column prop="name" label="姓名" min-width="80" />
    <el-table-column label="部门" min-width="120">
      <template #default="{ row }">
        {{ row.department?.name || '—' }}
      </template>
    </el-table-column>
    <el-table-column label="岗位" min-width="120">
      <template #default="{ row }">
        {{ row.position?.name || '—' }}
      </template>
    </el-table-column>
    <el-table-column prop="phone" label="手机号" min-width="120" />
    <el-table-column prop="email" label="邮箱" min-width="180" />
    <el-table-column label="技能标签" min-width="180">
      <template #default="{ row }">
        <el-space wrap :size="6">
          <el-tag v-for="tag in (row.skills || [])" :key="tag.id" size="small">
            {{ tag.name }}
          </el-tag>
        </el-space>
      </template>
    </el-table-column>
    <el-table-column label="入职日期" min-width="110">
      <template #default="{ row }">
        {{ row.profile?.hire_date || '—' }}
      </template>
    </el-table-column>
    <el-table-column label="状态" min-width="80">
      <template #default="{ row }">
        <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
          {{ row.is_active ? '在职' : '离职' }}
        </el-tag>
      </template>
    </el-table-column>
    <el-table-column label="操作" width="180" fixed="right">
      <template #default="{ row }">
        <el-button type="primary" link size="small" @click="emit('edit', row)">编辑</el-button>
        <el-popconfirm
          title="确定删除该员工？删除后该账号将无法登录"
          confirm-button-text="确定"
          cancel-button-text="取消"
          @confirm="emit('delete', row)"
        >
          <template #reference>
            <el-button type="danger" link size="small">删除</el-button>
          </template>
        </el-popconfirm>
      </template>
    </el-table-column>
  </el-table>
  <div class="pagination-wrapper">
    <el-pagination
      :current-page="page"
      :page-size="pageSize"
      :total="total"
      :page-sizes="[10, 20, 50, 100]"
      layout="total, sizes, prev, pager, next, jumper"
      @current-change="(p: number) => emit('pageChange', p)"
      @size-change="(s: number) => emit('sizeChange', s)"
    />
  </div>
</template>

<script setup lang="ts">
defineProps<{
  list: Record<string, unknown>[]
  loading: boolean
  page: number
  pageSize: number
  total: number
}>()
const emit = defineEmits<{
  (e: 'edit', row: Record<string, unknown>): void
  (e: 'delete', row: Record<string, unknown>): void
  (e: 'pageChange', p: number): void
  (e: 'sizeChange', s: number): void
}>()
</script>

<style lang="scss" scoped>
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
