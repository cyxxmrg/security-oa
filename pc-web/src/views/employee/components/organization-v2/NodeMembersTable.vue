<template>
  <div>
    <el-table
      v-if="node && (node.type === 'dept' || node.type === 'position')"
      :data="members"
      stripe
      size="small"
      style="width: 100%"
      v-loading="loading"
    >
      <el-table-column prop="name"     label="姓名"   min-width="80" />
      <el-table-column prop="username" label="账号"   min-width="100" />
      <el-table-column prop="position" label="岗位"   min-width="100" />
      <el-table-column prop="phone"    label="手机号" min-width="120" />
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
            {{ row.is_active ? '在职' : '离职' }}
          </el-tag>
        </template>
      </el-table-column>
    </el-table>
    <el-empty
      v-if="node && members.length === 0 && !loading"
      description="暂无员工" :image-size="60"
    />
  </div>
</template>

<script setup lang="ts">
export interface Member {
  id: number
  name: string
  username?: string
  position: string
  phone: string
  is_active: boolean
}

defineProps<{
  node: Record<string, unknown>
  members: Member[]
  loading: boolean
}>()
</script>
