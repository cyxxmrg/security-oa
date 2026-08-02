<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="title"
    width="900px"
    destroy-on-close
  >
    <el-table v-if="employees.length" :data="employees" border stripe>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="name" label="姓名" width="120" />
      <el-table-column prop="username" label="账号" width="120" />
      <el-table-column prop="phone" label="手机号" width="140" />
      <el-table-column prop="email" label="邮箱" min-width="200" show-overflow-tooltip />
      <el-table-column label="状态" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
            {{ row.is_active ? '在职' : '离职' }}
          </el-tag>
        </template>
      </el-table-column>
    </el-table>
    <el-empty v-else :description="`${title} 暂无员工`" />
  </el-dialog>
</template>

<script setup lang="ts">
defineProps<{ visible: boolean; title: string; employees: Record<string, unknown>[] }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void }>()
</script>
