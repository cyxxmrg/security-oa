<template>
  <div class="filter-bar">
    <el-form :inline="true" @submit.prevent="$emit('search')">
      <el-form-item label="关键词">
        <el-input :model-value="searchForm.keyword" @update:model-value="(v: string) => emit('update:searchForm', { ...searchForm, keyword: v })" placeholder="合同编号 / 名称" clearable style="width: 220px" />
      </el-form-item>
      <el-form-item label="状态">
        <el-select :model-value="searchForm.status" @update:model-value="(v: string) => emit('update:searchForm', { ...searchForm, status: v })" placeholder="全部状态" clearable style="width: 140px">
          <el-option v-for="s in STATUS_OPTIONS" :key="s.value" :label="s.label" :value="s.value" />
        </el-select>
      </el-form-item>
      <el-form-item label="签订日期">
        <el-date-picker
          :model-value="searchForm.date_range"
          @update:model-value="(v: string[]) => emit('update:searchForm', { ...searchForm, date_range: v || [] })"
          type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width: 240px"
        />
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
import { STATUS_OPTIONS } from './types'

export interface SearchForm { keyword: string; status: string; date_range: string[] }

defineProps<{ searchForm: SearchForm }>()
const emit = defineEmits<{
  (e: 'update:searchForm', v: SearchForm): void
  (e: 'search'): void
  (e: 'reset'): void
}>()
</script>

<style lang="scss" scoped>
.filter-bar { background: #fff; padding: 16px 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03); }
</style>
