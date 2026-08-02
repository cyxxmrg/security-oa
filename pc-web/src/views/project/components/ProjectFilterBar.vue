<template>
  <div class="filter-bar">
    <el-form :inline="true" @submit.prevent="emit('search')">
      <el-form-item label="项目名称">
        <el-input
          :model-value="form.name"
          @update:model-value="(v: string) => emit('update:form', { ...form, name: v })"
          placeholder="请输入项目名称" clearable style="width: 180px"
        />
      </el-form-item>
      <el-form-item label="所属客户">
        <el-select
          :model-value="form.customer_id"
          @update:model-value="(v: Record<string, unknown>) => emit('update:form', { ...form, customer_id: v })"
          placeholder="全部客户" clearable style="width: 200px" filterable
        >
          <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="项目阶段">
        <el-select
          :model-value="form.stage"
          @update:model-value="(v: string) => emit('update:form', { ...form, stage: v })"
          placeholder="全部阶段" clearable style="width: 140px"
        >
          <el-option v-for="s in stageOptions" :key="s.value" :label="s.label" :value="s.value" />
        </el-select>
      </el-form-item>
      <el-form-item label="项目状态">
        <el-select
          :model-value="form.status"
          @update:model-value="(v: string) => emit('update:form', { ...form, status: v })"
          placeholder="全部状态" clearable style="width: 140px"
        >
          <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
        </el-select>
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

defineProps<{ form: Record<string, unknown>; customerOptions: Array<{ id: number; name: string }>; stageOptions: Array<{ value: string; label: string }>; statusOptions: Array<{ value: string; label: string }> }>()
const emit = defineEmits<{
  (e: 'update:form', v: Record<string, unknown>): void
  (e: 'search'): void
  (e: 'reset'): void
}>()
</script>

<style lang="scss" scoped>
.filter-bar { background: #fff; border-radius: 8px; padding: 12px 16px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03); }
</style>
