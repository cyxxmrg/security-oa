<template>
  <div class="filter-bar">
    <el-form :inline="true" :model="localForm" @submit.prevent="emit('search')">
      <el-form-item label="关联项目">
        <el-select
          v-model="localForm.project_id"
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
          v-model="localForm.status"
          placeholder="全部状态"
          clearable
          style="width: 140px"
        >
          <el-option
            v-for="s in STATUS_OPTIONS"
            :key="s.value"
            :label="s.label"
            :value="s.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="优先级">
        <el-select
          v-model="localForm.priority"
          placeholder="全部优先级"
          clearable
          style="width: 140px"
        >
          <el-option
            v-for="p in PRIORITY_OPTIONS"
            :key="p.value"
            :label="p.label"
            :value="p.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="关键词">
        <el-input
          v-model="localForm.keyword"
          placeholder="需求编号 / 物资名称"
          clearable
          style="width: 200px"
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
import { reactive, watch } from 'vue'
import { Search, Refresh } from '@element-plus/icons-vue'
import type { RequirementFilters, ProjectOption } from './types'
import { STATUS_OPTIONS, PRIORITY_OPTIONS } from './types'

// v0.3.23 抽自 purchase/Requirement.vue:11-36
const props = defineProps<{
  filters: RequirementFilters
  projectOptions: ProjectOption[]
}>()

const emit = defineEmits<{
  (e: 'search'): void
  (e: 'reset'): void
}>()

const localForm = reactive({ ...props.filters })
watch(() => props.filters, (v) => Object.assign(localForm, v), { deep: true })
</script>

<style lang="scss" scoped>
.filter-bar {
  background: #fff; padding: 16px 20px; border-radius: 8px;
  margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
</style>
