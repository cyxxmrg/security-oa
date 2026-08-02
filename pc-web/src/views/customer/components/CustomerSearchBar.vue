<script setup lang="ts">
/**
 * CustomerSearchBar — 客户筛选条 (v0.3.14 C2)
 * Props: industries (string[]), categories (string[])
 * Emit: search / reset
 */
import { reactive } from 'vue'
import { Search, Refresh, Plus, Upload, Download } from '@element-plus/icons-vue'

const props = defineProps<{
  industries: string[]
  categories: string[]
}>()

const emit = defineEmits<{
  (e: 'search', form: { keyword: string; industry: string; category: string }): void
  (e: 'reset'): void
  (e: 'add'): void
  (e: 'import'): void
  (e: 'export'): void
}>()

const form = reactive({ keyword: '', industry: '', category: '' })

const onSearch = () => emit('search', { ...form })
const onReset = () => {
  form.keyword = ''; form.industry = ''; form.category = ''
  emit('reset')
}
</script>

<template>
  <el-form :inline="true" :model="form" @submit.prevent="onSearch" class="cust-search">
    <el-form-item label="客户名称">
      <el-input v-model="form.keyword" placeholder="客户名 / 联系人 / 电话" clearable style="width: 220px" />
    </el-form-item>
    <el-form-item label="所属行业">
      <el-select v-model="form.industry" placeholder="全部行业" clearable style="width: 160px">
        <el-option v-for="i in industries" :key="i" :label="i" :value="i" />
      </el-select>
    </el-form-item>
    <el-form-item label="客户分类">
      <el-select v-model="form.category" placeholder="全部分类" clearable style="width: 140px">
        <el-option v-for="c in categories" :key="c" :label="c" :value="c" />
      </el-select>
    </el-form-item>
    <el-form-item>
      <el-button type="primary" :icon="Search" @click="onSearch">查询</el-button>
      <el-button :icon="Refresh" @click="onReset">重置</el-button>
    </el-form-item>
    <el-form-item style="float: right">
      <el-button :icon="Upload" @click="emit('import')" plain>批量导入</el-button>
      <el-button :icon="Download" @click="emit('export')" plain>导出</el-button>
      <el-button type="primary" :icon="Plus" @click="emit('add')">新增客户</el-button>
    </el-form-item>
  </el-form>
</template>

<style scoped>
.cust-search { margin-bottom: 12px; }
</style>
