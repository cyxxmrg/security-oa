<template>
  <div class="filter-bar">
    <el-form :inline="true" :model="localForm" @submit.prevent="emit('search')">
      <el-form-item label="所属项目">
        <el-select
          v-model="localForm.project_id"
          placeholder="全部项目"
          clearable
          filterable
          style="width: 200px"
          @change="emit('search')"
        >
          <el-option
            v-for="p in projectOptions"
            :key="p.id"
            :label="p.name"
            :value="p.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="工序">
        <el-select
          v-model="localForm.process_instance_id"
          placeholder="全部工序（可空）"
          clearable
          filterable
          style="width: 220px"
        >
          <el-option
            v-for="i in processInstanceOptions"
            :key="i.id"
            :label="i.label"
            :value="i.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="验收结果">
        <el-select
          v-model="localForm.result"
          placeholder="全部结果"
          clearable
          style="width: 140px"
        >
          <el-option
            v-for="o in RESULT_OPTIONS"
            :key="o.value"
            :label="o.label"
            :value="o.value"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="验收时间">
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          style="width: 260px"
          unlink-panels
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
import { reactive, ref, watch } from 'vue'
import { Search, Refresh } from '@element-plus/icons-vue'
import type { InspectionFilters, ProjectOption, ProcessInstanceOption } from './types'
import { RESULT_OPTIONS } from './types'

// v0.3.25 抽自 process/InspectionList.vue:48-116
const props = defineProps<{
  form: InspectionFilters
  projectOptions: ProjectOption[]
  processInstanceOptions: ProcessInstanceOption[]
}>()

const emit = defineEmits<{
  (e: 'search'): void
  (e: 'reset'): void
}>()

const localForm = reactive({ ...props.form })
watch(() => props.form, (v) => Object.assign(localForm, v), { deep: true })

const dateRange = ref<[string, string] | null>(null)
defineExpose({ dateRange })
</script>

<style lang="scss" scoped>
.filter-bar {
  background: #fafbfc;
  padding: 14px 16px;
  border-radius: 6px;
  margin-bottom: 16px;
  border: 1px solid #f0f2f5;
}
</style>
