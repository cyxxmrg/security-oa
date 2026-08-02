<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">采购需求管理</span>
      <div class="header-actions">
        <el-button :icon="Download" plain @click="handleExport">导出</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建需求</el-button>
      </div>
    </div>

    <RequirementFilterBar
      :filters="searchForm"
      :project-options="projectOptions"
      @search="handleSearch"
      @reset="handleReset"
    />

    <div class="content-card">
      <RequirementStatCards :stats="stats" :list="list" />

      <RequirementTable
        :list="pagedList"
        :loading="loading"
        @view="handleView"
        @view-project="handleViewProject"
        @edit="handleEdit"
        @delete="handleDelete"
        @convert-to-plan="handleConvertToPlan"
      />

      <el-empty v-if="!loading && pagedList.length === 0" description="暂无采购需求" :image-size="80" />

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="filteredList.length"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handlePageChange"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <RequirementFormDialog
      v-model="showFormDialog"
      :mode="formMode"
      :form="formData"
      :loading="submitting"
      :project-options="projectOptions"
      :employee-options="employeeOptions"
      @submit="handleSave"
      @add-material="addMaterial"
      @remove-material="removeMaterial"
    />

    <RequirementDetailDrawer
      v-model="showDetailDrawer"
      :item="currentItem"
      @view-project="handleViewProject"
      @edit="handleEditFromDetail"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Download } from '@element-plus/icons-vue'
import { purchase } from '@/api/modules'
import { unwrapList, unwrapStats } from '@/utils/response'
import { purchaseFlow } from '@/api/purchase-flow'
import { getProjectList } from '@/api/modules'
import { get } from '@/utils/request'
import { exportExcelLike } from '@/utils/exporter'

import RequirementFilterBar from './components/requirement/RequirementFilterBar.vue'
import RequirementStatCards from './components/requirement/RequirementStatCards.vue'
import RequirementTable from './components/requirement/RequirementTable.vue'
import RequirementFormDialog from './components/requirement/RequirementFormDialog.vue'
import RequirementDetailDrawer from './components/requirement/RequirementDetailDrawer.vue'

import type {
  Requirement, RequirementFilters, RequirementForm, ProjectOption, FormMode,
} from './components/requirement/types'
import {
  statusLabel, priorityLabel, formatDate, emptyForm,
} from './components/requirement/types'

// v0.3.23 拆 Requirement.vue 727→260 (-64%)
// 子组件: FilterBar / StatCards / Table / FormDialog / DetailDrawer

const loading = ref(false)
const submitting = ref(false)
const list = ref<Requirement[]>([])
const stats = reactive({ pending: 0, approved: 0, rejected: 0, cancelled: 0, total: 0 })
const projectOptions = ref<ProjectOption[]>([])
const employeeOptions = ref<{ id: number; name: string }[]>([])

const page = ref(1)
const pageSize = ref(10)

const searchForm = reactive<RequirementFilters>({
  project_id: null,
  status: '',
  priority: '',
  keyword: '',
})

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.project_id) arr = arr.filter((r) => r.project_id === searchForm.project_id)
  if (searchForm.status) arr = arr.filter((r) => r.status === searchForm.status)
  if (searchForm.priority) arr = arr.filter((r) => r.priority === searchForm.priority)
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter((r) =>
      (r.code || '').toLowerCase().includes(kw) ||
      (r.material || '').toLowerCase().includes(kw)
    )
  }
  return arr.sort((a, b) => {
    if (a.priority !== b.priority) return a.priority === 'urgent' ? -1 : 1
    return (a.need_date || '').localeCompare(b.need_date || '')
  })
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

// ====================== 数据加载 ======================
const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: 1, per_page: 200 }
    if (searchForm.project_id) params.project_id = searchForm.project_id
    if (searchForm.status) params.status = searchForm.status
    if (searchForm.priority) params.priority = searchForm.priority
    if (searchForm.keyword) params.keyword = searchForm.keyword
    const res = await purchase.getRequirements(params)
    // V0.6.3 不再解包, 兼容两种形态
    list.value = unwrapList(res)
  } catch (e) {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  try {
    const res = await purchase.getRequirementStats()
    // V0.6.3 不再解包, 兼容两种形态
    Object.assign(stats, unwrapStats(res))
  } catch { /* 静默 */ }
}

const loadProjects = async () => {
  try {
    const res = await getProjectList({ per_page: 200 })
    // V0.6.3 不再解包, 兼容两种形态
    projectOptions.value = unwrapList(res).map((p: Record<string, unknown>) => ({ id: p.id, name: p.name || p.code }))
  } catch {
    projectOptions.value = []
  }
}

const loadEmployees = async () => {
  try {
    const res = await get('/employees', { per_page: 500 })
    const list = unwrapList(res) as Record<string, unknown>[]
    employeeOptions.value = list.map(e => ({ id: Number(e.id || e.user_id), name: String(e.name || e.realname || '') })).filter(e => e.name)
  } catch { employeeOptions.value = [] }
}

// ====================== 表单 Dialog ======================
const showFormDialog = ref(false)
const formMode = ref<FormMode>('create')
const formData = reactive<RequirementForm>(emptyForm())

const handleAdd = () => {
  formMode.value = 'create'
  Object.assign(formData, emptyForm())
  // V1.2.13: 发起人默认当前登录用户
  try {
    const stored = localStorage.getItem('oa_user_info')
    if (stored) {
      const info = JSON.parse(stored)
      formData.creator = info.name || info.username || ''
    }
  } catch {/* ignore */}
  showFormDialog.value = true
}

const handleEdit = (row: Requirement) => {
  if (row.status === 'approved') {
    ElMessage.warning('已通过的需求不可编辑')
    return
  }
  if (row.status === 'rejected') {
    ElMessage.warning('已驳回的需求不可编辑')
    return
  }
  formMode.value = 'edit'
  Object.assign(formData, {
    id: row.id,
    project_id: row.project_id,
    need_date: row.need_date ? String(row.need_date).slice(0, 10) : '',
    priority: row.priority || 'medium',
    creator: row.creator || '',
    materials: [{
      inventory_item_id: (row as Record<string, unknown>).inventory_item_id || null,
      name: row.material,
      spec: row.spec || '',
      quantity: Number(row.quantity || 1),
      unit: row.unit || '件',
    }],
    remark: row.remark || '',
  })
  showFormDialog.value = true
}

const handleEditFromDetail = () => {
  if (currentItem.value) {
    handleEdit(currentItem.value)
    showDetailDrawer.value = false
  }
}

const addMaterial = () => {
  formData.materials.push({ inventory_item_id: null, name: '', spec: '', quantity: 1, unit: '件' })
}

const removeMaterial = (idx: number) => {
  if (formData.materials.length === 1) {
    ElMessage.warning('至少保留一条物资')
    return
  }
  formData.materials.splice(idx, 1)
}

const handleSave = async () => {
  for (let i = 0; i < formData.materials.length; i++) {
    const m = formData.materials[i]
    if (!m.name || !m.quantity) {
      ElMessage.warning(`第 ${i + 1} 行物资不完整`)
      return
    }
  }
  submitting.value = true
  try {
    const main = formData.materials[0]
    const payload: Record<string, unknown> = {
      project_id: formData.project_id || null,
      inventory_item_id: main.inventory_item_id || null,
      material: main.name,
      spec: main.spec || null,
      quantity: Number(main.quantity) || 0,
      unit: main.unit || '件',
      need_date: formData.need_date || null,
      priority: formData.priority || 'medium',
      creator: formData.creator || null,
      remark: formData.remark || null,
    }
    if (formMode.value === 'create') {
      await purchase.createRequirement(payload)
      ElMessage.success('采购需求创建成功')
    } else {
      await purchase.updateRequirement(formData.id, payload)
      ElMessage.success('采购需求已更新')
    }
    showFormDialog.value = false
    page.value = 1
    await loadList()
    await loadStats()
  } catch (e) {
    /* request 拦截器已 ElMessage */
  } finally {
    submitting.value = false
  }
}

// ====================== 详情 Drawer ======================
const showDetailDrawer = ref(false)
const currentItem = ref<Requirement | null>(null)

const handleView = (row: Requirement) => {
  currentItem.value = row
  showDetailDrawer.value = true
}

const handleViewProject = (row: Requirement) => {
  ElMessage.info(`查看项目详情：${row.project_name || row.project_id}（占位）`)
}

// ====================== 删除 ======================
const handleDelete = async (row: Requirement) => {
  if (row.status === 'approved') {
    ElMessage.warning('已通过的需求不可删除')
    return
  }
  try {
    await ElMessageBox.confirm(
      `确定要删除采购需求「${row.code}」吗？此操作不可恢复。`,
      '删除确认',
      { type: 'warning', confirmButtonText: '确定删除', cancelButtonText: '取消' }
    )
  } catch {
    return
  }
  try {
    await purchase.deleteRequirement(row.id)
    ElMessage.success('已删除')
    await loadList()
    await loadStats()
    if (pagedList.value.length === 0 && page.value > 1) page.value -= 1
  } catch (e) { /* request 拦截器已 ElMessage */ }
}

// V1.2.7h: 采购需求审批统一进审批中心, 此处不再处理

// ====================== V0.6.2.2 转采购计划 (走 flow API) ======================
const handleConvertToPlan = async (row: Requirement) => {
  if (row.status !== 'approved') {
    ElMessage.warning('仅「已审批」状态的需求可转计划')
    return
  }
  let form: Record<string, unknown> | null = null
  try {
    const res = await ElMessageBox.prompt(
      `将需求「${row.code}」转为采购计划，请输入计划信息：\n格式：标题|金额|日期|优先级(低/中/高/紧急)|备注\n示例：V0.6.2 摄像头采购|1500|2026-07-01|高|批量采购`,
      '转采购计划',
      {
        confirmButtonText: '确认转计划',
        cancelButtonText: '取消',
        inputType: 'textarea',
        inputPlaceholder: '标题|金额|日期|优先级|备注',
        inputValidator: (v: string) => (v && v.trim() ? true : '请填写计划信息'),
      }
    )
    const parts = String(res.value || '').split('|').map(s => s.trim())
    if (parts.length < 2) {
      ElMessage.warning('格式错误：至少要 标题|金额')
      return
    }
    form = {
      title: parts[0] || row.material,
      total_amount: Number(parts[1]) || row.budget || 0,
      plan_date: parts[2] || '',
      priority: ['低', '中', '高', '紧急'].includes(parts[3]) ? ({ '低': 'low', '中': 'medium', '高': 'high', '紧急': 'urgent' } as Record<string, unknown>)[parts[3]] : 'medium',
      remark: parts[4] || '',
    }
  } catch {
    return
  }
  try {
    const r = await purchaseFlow.createPlan({
      ...form,
      project_id: row.project_id,
      requirement_ids: [row.id],
    })
    ElMessage.success(`已生成计划：${r.data?.code || '成功'} (ID #${r.data?.id})`)
    await loadList()
    await loadStats()
  } catch (e) { /* request 拦截器已 ElMessage */ }
}

// ====================== 搜索 / 重置 / 导出 ======================
const handleSearch = () => { page.value = 1; loadList() }
const handleReset = () => {
  searchForm.project_id = null
  searchForm.status = ''
  searchForm.priority = ''
  searchForm.keyword = ''
  page.value = 1
  loadList()
}
const handlePageChange = () => { /* computed 自动重算 */ }

const handleExport = () => {
  if (filteredList.value.length === 0) {
    ElMessage.warning('当前列表无数据可导出')
    return
  }
  const headers = ['需求编号', '关联项目', '需求物资', '规格', '数量', '单位', '需求日期', '优先级', '发起人', '状态', '发起时间']
  const rows = filteredList.value.map((r: Record<string, unknown>) => [
    r.code, r.project_name, r.material, r.spec || '',
    r.quantity, r.unit || '件', r.need_date,
    priorityLabel(r.priority), r.creator, statusLabel(r.status), r.created_at,
  ])
  exportExcelLike(headers, rows, '采购需求', { title: '采购需求清单' })
}

onMounted(() => {
  loadList()
  loadStats()
  loadProjects()
  loadEmployees()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
