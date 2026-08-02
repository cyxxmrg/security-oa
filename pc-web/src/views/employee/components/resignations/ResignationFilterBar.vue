<template>
  <div class="filter-bar">
    <el-tabs :model-value="activeStatus" @update:model-value="(v: string) => emit('update:activeStatus', v)" @tab-change="emit('tabChange')" class="status-tabs">
      <el-tab-pane label="草稿"   name="draft" />
      <el-tab-pane label="待审批" name="pending" />
      <el-tab-pane label="已审批" name="approved" />
      <el-tab-pane label="已办结" name="completed" />
      <el-tab-pane label="已取消" name="cancelled" />
    </el-tabs>
    <div class="filter-right">
      <el-input
        :model-value="keyword"
        @update:model-value="(v: string) => emit('update:keyword', v)"
        placeholder="搜索员工姓名"
        clearable
        :prefix-icon="Search"
        style="width: 200px"
        @keyup.enter="emit('search')"
        @clear="emit('search')"
      />
      <el-button type="primary" :icon="Plus" @click="emit('create')">+ 发起离职</el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Plus, Search } from '@element-plus/icons-vue'

defineProps<{
  activeStatus: string
  keyword: string
}>()
const emit = defineEmits<{
  (e: 'update:activeStatus', v: string): void
  (e: 'update:keyword', v: string): void
  (e: 'tabChange'): void
  (e: 'search'): void
  (e: 'create'): void
}>()
</script>

<style lang="scss" scoped>
.filter-bar {
  background: #fff;
  border-radius: 8px;
  padding: 8px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
}
.status-tabs { flex: 1; }
.filter-right { display: flex; gap: 8px; align-items: center; }
:deep(.status-tabs .el-tabs__header) { margin: 0; border: none; }
:deep(.status-tabs .el-tabs__nav-wrap::after) { display: none; }
</style>
