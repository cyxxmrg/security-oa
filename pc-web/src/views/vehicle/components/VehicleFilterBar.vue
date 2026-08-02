<template>
  <div class="filter-bar">
    <el-input
      :model-value="keyword"
      @update:model-value="(v: string) => emit('update:keyword', v)"
      placeholder="搜索车牌号/品牌型号" clearable style="width: 220px" @keyup.enter="emit('search')"
    />
    <el-select
      :model-value="status"
      @update:model-value="(v: string) => emit('update:status', v)"
      placeholder="车辆状态" clearable style="width: 150px"
    >
      <el-option v-for="(v, k) in statusMap" :key="k" :label="v.label" :value="k" />
    </el-select>
    <el-button type="primary" @click="emit('search')">搜索</el-button>
    <el-button @click="emit('reset')">重置</el-button>
    <el-button type="primary" plain @click="emit('create')">新增车辆</el-button>
  </div>
</template>

<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  keyword: string
  status: string
  statusMap: Record<string, { label: string; type: 'success' | 'warning' | 'info' | 'danger' }>
}>()
const emit = defineEmits<{
  (e: 'update:keyword', v: string): void
  (e: 'update:status', v: string): void
  (e: 'search'): void
  (e: 'reset'): void
  (e: 'create'): void
}>()
</script>

<style lang="scss" scoped>
.filter-bar {
  background: #fff;
  padding: 16px 20px;
  border-radius: 8px;
  display: flex;
  gap: 8px;
  align-items: center;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
  margin-bottom: 16px;
}
</style>
