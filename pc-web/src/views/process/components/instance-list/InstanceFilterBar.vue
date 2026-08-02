<template>
  <div class="filter-bar">
    <el-form :inline="true" :model="form" @submit.prevent="emit('search')">
      <el-form-item label="所属项目">
        <el-select
          v-model="form.project_id"
          placeholder="全部项目"
          clearable
          filterable
          style="width: 200px"
        >
          <el-option
            v-for="p in projectOptions"
            :key="p.id"
            :label="p.name"
            :value="p.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="状态">
        <el-select
          v-model="form.status"
          placeholder="全部状态"
          clearable
          style="width: 140px"
        >
          <el-option
            v-for="s in statusOptions"
            :key="s.value"
            :label="s.label"
            :value="s.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="超期">
        <el-checkbox
          v-model="form.is_overdue"
          @change="emit('search')"
        >仅看超期</el-checkbox>
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
import type { SearchForm, ProjectOption, OptionItem } from './types'

// v0.3.19 抽自 process/InstanceList.vue:28-48
const props = defineProps<{
  form: SearchForm
  projectOptions: ProjectOption[]
  statusOptions: OptionItem[]
}>()

const emit = defineEmits<{
  (e: 'search'): void
  (e: 'reset'): void
}>()
</script>

<style lang="scss" scoped>
.filter-bar {
  background: #fafbfc; padding: 14px 16px;
  border-radius: 6px; margin-bottom: 16px;
  border: 1px solid #f0f2f5;
}
</style>
