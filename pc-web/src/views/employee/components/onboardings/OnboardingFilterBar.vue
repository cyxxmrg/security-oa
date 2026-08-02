<template>
  <div class="filter-bar">
    <el-input
      v-model="localFilters.keyword"
      placeholder="搜索姓名/账号/手机号"
      clearable
      :prefix-icon="Search"
      style="width: 220px"
      @keyup.enter="emit('search')"
      @clear="emit('search')"
    />
    <el-select
      v-model="localFilters.department_id"
      placeholder="部门"
      clearable
      style="width: 160px"
      @change="emit('search')"
    >
      <el-option
        v-for="d in departmentList"
        :key="d.id"
        :label="d.name"
        :value="d.id"
      />
    </el-select>
    <el-select
      v-model="localFilters.status"
      placeholder="状态"
      clearable
      style="width: 120px"
      @change="emit('search')"
    >
      <el-option
        v-for="o in STATUS_OPTIONS"
        :key="o.value"
        :label="o.label"
        :value="o.value"
      />
    </el-select>
    <el-tooltip content="合同 30 天内到期">
      <el-switch
        v-model="localFilters.contract_expiring"
        active-text="合同到期"
        @change="emit('search')"
        style="--el-switch-on-color: #BA7517"
      />
    </el-tooltip>
    <el-tooltip content="试用期 7 天内到期">
      <el-switch
        v-model="localFilters.probation_expiring"
        active-text="试用期到期"
        @change="emit('search')"
        style="--el-switch-on-color: #534AB7"
      />
    </el-tooltip>
    <el-button type="primary" @click="emit('search')">查询</el-button>
    <el-button @click="emit('reset')">重置</el-button>
    <span class="toolbar-spacer" />
    <el-button type="primary" :icon="Plus" @click="emit('create')">+ 办理入职</el-button>
  </div>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'
import { Search, Plus } from '@element-plus/icons-vue'
import type { OnboardingFilters, Department } from './types'
import { STATUS_OPTIONS } from './types'

// v0.3.22 抽自 employee/Onboardings.vue:35-73
const props = defineProps<{
  filters: OnboardingFilters
  departmentList: Department[]
}>()

const emit = defineEmits<{
  (e: 'search'): void
  (e: 'reset'): void
  (e: 'create'): void
}>()

// 双向绑定 (Vue 3 v-model:filters 不允许, 用本地副本)
const localFilters = reactive({ ...props.filters })
watch(() => props.filters, (v) => Object.assign(localFilters, v), { deep: true })
</script>

<style lang="scss" scoped>
.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 14px 16px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  .toolbar-spacer { flex: 1; }
}
</style>
