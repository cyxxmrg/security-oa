<template>
  <div class="page-container">
    <div class="page-header">
      <div class="title-area">
        <span class="page-title">工序模板管理</span>
        <el-tag effect="light" type="info">{{ total }} 个模板</el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="RefreshRight" plain @click="loadAll">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleCreate">新建模板</el-button>
      </div>
    </div>

    <TemplateStatCards :stats="stats" />

    <TemplateFilterBar
      :form="searchForm"
      @search="handleSearch"
      @reset="handleReset"
    />

    <div class="content-card">
      <TemplateTable
        :list="list"
        :loading="loading"
        @edit="handleEdit"
        @delete="handleDelete"
        @status-change="handleStatusChange"
      />

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          background
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </div>

    <TemplateFormDialog
      v-model="dialogVisible"
      :mode="dialogMode"
      :form="form"
      :loading="dialogLoading"
      @submit="handleDialogSubmit"
      @closed="handleDialogClosed"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, RefreshRight } from '@element-plus/icons-vue'
import { processApi } from '@/api/modules'

import TemplateStatCards from './components/template-list/TemplateStatCards.vue'
import TemplateFilterBar from './components/template-list/TemplateFilterBar.vue'
import TemplateTable from './components/template-list/TemplateTable.vue'
import TemplateFormDialog from './components/template-list/TemplateFormDialog.vue'

import type { ProcessTemplate, SearchForm, TemplateStats, TemplateForm } from './components/template-list/types'
import { emptyStats, defaultTemplateForm } from './components/template-list/types'

// v1.2.12p 字段对齐后端: is_active / standard_duration_days / standard_man_hours / required_qualifications / acceptance_criteria

const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const list = ref<ProcessTemplate[]>([])

const stats = reactive<TemplateStats>(emptyStats())
const searchForm = reactive<SearchForm>({ industry: '', keyword: '' })

const todayLabel = (): string => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function loadList() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: page.value,
      page_size: pageSize.value,
    }
    if (searchForm.industry) params.industry = searchForm.industry
    if (searchForm.keyword) params.keyword = searchForm.keyword

    const r = await processApi.templateList(params)
    const data = r?.data ?? r
    const rows = data?.data ?? data?.list ?? []
    if (Array.isArray(rows)) {
      list.value = rows.map((it: Record<string, unknown>) => {
        const r: ProcessTemplate = {
          ...(it as unknown as ProcessTemplate),
          is_active: it.is_active === true || it.is_active === 1,
          _statusLoading: false,
        }
        return r
      })
    } else {
      list.value = []
    }
    total.value = data?.total ?? list.value.length

    computeStatsFromList()
  } catch (e: unknown) {
    list.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function computeStatsFromList() {
  stats.total = total.value
  const activeInPage = list.value.filter((t) => t.is_active === true).length
  if (list.value.length > 0) {
    stats.active = Math.round((activeInPage / list.value.length) * total.value)
  } else {
    stats.active = 0
  }
  const industrySet = new Set(list.value.map((t) => t.industry).filter(Boolean))
  stats.industryCount = industrySet.size
  const today = todayLabel()
  stats.todayNew = list.value.filter((t) => t.created_at && String(t.created_at).slice(0, 10) === today).length
}

function loadAll() {
  loadList()
}

const handleSearch = () => { page.value = 1; loadList() }
const handleReset = () => {
  searchForm.industry = ''
  searchForm.keyword = ''
  page.value = 1
  loadList()
}

async function handleStatusChange(row: ProcessTemplate, val: unknown) {
  row._statusLoading = true
  try {
    await processApi.templateUpdate(row.id, { is_active: Boolean(val) })
    ElMessage.success(Boolean(val) ? '已启用' : '已停用')
    computeStatsFromList()
  } catch (e: unknown) {
    row.is_active = !Boolean(val)
    ElMessage.error((e as { message?: string })?.message || '状态更新失败')
  } finally {
    row._statusLoading = false
  }
}

const dialogVisible = ref(false)
const dialogLoading = ref(false)
const dialogMode = ref<'create' | 'edit'>('create')
const form = reactive<TemplateForm>(defaultTemplateForm())

function handleCreate() {
  dialogMode.value = 'create'
  Object.assign(form, defaultTemplateForm())
  dialogVisible.value = true
}

function handleEdit(row: ProcessTemplate) {
  dialogMode.value = 'edit'
  Object.assign(form, {
    id: row.id,
    industry: row.industry || '',
    category: row.category || '',
    code: row.code || '',
    name: row.name || '',
    description: row.description || '',
    standard_duration_days: row.standard_duration_days ?? 0,
    standard_man_hours: row.standard_man_hours ?? 0,
    required_qualifications: Array.isArray(row.required_qualifications) ? row.required_qualifications : [],
    safety_requirements: row.safety_requirements || '',
    acceptance_criteria: Array.isArray(row.acceptance_criteria) ? row.acceptance_criteria : [],
    sort_order: row.sort_order ?? 0,
    is_active: row.is_active ?? true,
  })
  dialogVisible.value = true
}

async function handleDialogSubmit() {
  const formRef = (document.querySelector('.el-dialog__wrapper .el-form') as unknown as Record<string, unknown>)
  if (!formRef) return
  try {
    await (formRef as { validate: () => Promise<void> }).validate()
  } catch {
    return
  }
  dialogLoading.value = true
  try {
    const payload = {
      industry: form.industry,
      category: form.category,
      code: form.code,
      name: form.name,
      description: form.description || null,
      standard_duration_days: Number(form.standard_duration_days || 0),
      standard_man_hours: Number(form.standard_man_hours || 0),
      required_qualifications: form.required_qualifications,
      safety_requirements: form.safety_requirements || null,
      acceptance_criteria: form.acceptance_criteria,
      sort_order: Number(form.sort_order || 0),
      is_active: form.is_active,
    }
    if (dialogMode.value === 'create') {
      await processApi.templateCreate(payload)
      ElMessage.success('创建成功')
    } else {
      await processApi.templateUpdate(form.id, payload)
      ElMessage.success('保存成功')
    }
    dialogVisible.value = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || (dialogMode.value === 'create' ? '创建失败' : '保存失败'))
  } finally {
    dialogLoading.value = false
  }
}

function handleDialogClosed() {
  Object.assign(form, defaultTemplateForm())
}

async function handleDelete(row: ProcessTemplate) {
  try {
    await processApi.templateDelete(row.id)
    ElMessage.success('删除成功')
    if (list.value.length === 1 && page.value > 1) {
      page.value -= 1
    }
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '删除失败')
  }
}

onMounted(() => {
  loadAll()
})
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
}
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .title-area { display: flex; align-items: center; gap: 10px; }
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.content-card {
  background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>