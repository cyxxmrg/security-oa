<template>
  <div class="filter-bar">
    <el-form :inline="true" @submit.prevent="$emit('search')">
      <el-form-item label="关键词">
        <el-input :model-value="keyword" @update:model-value="(v: string) => emit('update:keyword', v)" placeholder="搜索 采购计划" clearable style="width: 220px" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" :icon="Search" @click="emit('search')">查询</el-button>
        <el-button :icon="Refresh" @click="emit('reset')">重置</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup lang="ts">
import { Search, Refresh } from '@element-plus/icons-vue'
defineProps<{ keyword: string }>()
const emit = defineEmits<{
  (e: 'update:keyword', v: string): void
  (e: 'search'): void
  (e: 'reset'): void
}>()
</script>

<style lang="scss" scoped>
.filter-bar { background: #fff; padding: 16px 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03); }
</style>
