<template>
  <div class="filter-bar">
    <el-form :inline="true" :model="localForm" @submit.prevent="emit('search')">
      <el-form-item label="项目类型">
        <el-select v-model="localForm.industry" placeholder="全部项目类型" clearable style="width: 180px">
          <el-option
            v-for="(label, key) in INDUSTRY_MAP"
            :key="key"
            :label="label"
            :value="key"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="关键词">
        <el-input
          v-model="localForm.keyword"
          placeholder="搜索模板名称/编号"
          clearable
          style="width: 220px"
          @keyup.enter="emit('search')"
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
import type { SearchForm } from './types'
import { INDUSTRY_MAP } from './types'

// v0.3.25 抽自 process/TemplateList.vue:51-78
const props = defineProps<{
  form: SearchForm
}>()

const emit = defineEmits<{
  (e: 'search'): void
  (e: 'reset'): void
}>()

const localForm = reactive({ ...props.form })
watch(() => props.form, (v) => Object.assign(localForm, v), { deep: true })
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
