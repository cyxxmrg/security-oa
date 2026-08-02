<template>
  <div class="employee-toolbar">
    <el-input
      v-model="listFilters.keyword"
      placeholder="搜索姓名/手机/工号"
      clearable
      :prefix-icon="Search"
      style="width: 240px"
      @keyup.enter="$emit('search')"
      @clear="$emit('search')"
    />
    <el-select v-model="listFilters.department_id" placeholder="部门" clearable style="width: 160px" @change="$emit('search')">
      <el-option v-for="d in deptList" :key="d.id" :label="d.name" :value="d.id" />
    </el-select>
    <el-select v-model="listFilters.status" placeholder="状态" clearable style="width: 120px" @change="$emit('search')">
      <el-option label="在职" value="active" />
      <el-option label="离职" value="inactive" />
    </el-select>
    <el-button type="primary" @click="$emit('search')">查询</el-button>
    <el-button @click="$emit('reset')">重置</el-button>
    <span class="toolbar-spacer" />
    <el-button @click="$emit('go-onboardings')">📋 入职档案</el-button>
    <el-button @click="$emit('go-resignations')">🚪 离职办理</el-button>
    <el-button type="primary" :icon="Plus" @click="$emit('create')">+ 新建员工</el-button>
  </div>
</template>

<script setup lang="ts">
import { Search, Plus } from '@element-plus/icons-vue'

defineProps<{
  listFilters: { keyword: string; department_id: number | null; status: string }
  deptList: Record<string, unknown>[]
}>()

defineEmits<{
  'search': []
  'reset': []
  'go-onboardings': []
  'go-resignations': []
  'create': []
}>()
</script>

<style scoped>
.employee-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
  background: #fff;
  border-radius: 8px;
  padding: 12px 16px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  .toolbar-spacer { flex: 1; }
}
</style>
