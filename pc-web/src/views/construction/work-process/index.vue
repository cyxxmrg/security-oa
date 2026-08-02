<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">工序字典</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新增工序</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="工序名" clearable style="width: 220px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 140px">
            <el-option label="启用" value="active" />
            <el-option label="停用" value="disabled" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="content-card">
      <el-table
        :data="pagedList"
        v-loading="loading"
        stripe
        border
        :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
      >
        <el-table-column prop="id" label="编号" width="80" align="center" />
        <el-table-column prop="name" label="工序名称" min-width="180" show-overflow-tooltip />
        <el-table-column label="说明" min-width="280" show-overflow-tooltip>
          <template #default="{ row }">{{ row.description || '—' }}</template>
        </el-table-column>
        <el-table-column label="预估工时" width="110" align="right">
          <template #default="{ row }">{{ formatHours(row.estimated_hours) }}</template>
        </el-table-column>
        <el-table-column prop="sequence" label="排序" width="80" align="center" />
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" effect="plain" size="small">
              {{ row.status === 'active' ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="{ row }">
            <el-button link type="warning" :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="filteredList.length"
          layout="total, sizes, prev, pager, next, jumper"
          background
        />
      </div>
    </div>

    <!-- 工序表单 dialog -->
    <el-dialog
      v-model="showDialog"
      :title="isEdit ? '编辑工序' : '新增工序'"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-form-item label="工序名称" prop="name">
          <el-input v-model="formData.name" maxlength="50" placeholder="如：线管敷设 / 摄像头安装" />
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="formData.description" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="工序要做什么、注意事项等" />
        </el-form-item>
        <el-form-item label="预估工时">
          <el-input-number v-model="formData.estimated_hours" :min="0" :step="0.5" :precision="1" style="width: 100%" />
          <span class="form-tip">单位:小时</span>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="formData.sequence" :min="0" :step="1" style="width: 100%" />
          <span class="form-tip">数字越小越靠前</span>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="formData.status">
            <el-radio value="active">启用</el-radio>
            <el-radio value="disabled">停用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, Edit, Delete } from '@element-plus/icons-vue'
import { workProcessApi } from '@/api/construction'
import { unwrapList } from '@/utils/response'
import type { WorkProcess } from '../types'

const loading = ref(false)
const saving = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<WorkProcess[]>([])

const searchForm = reactive<{ keyword: string; status: string }>({
  keyword: '',
  status: '',
})

const showDialog = ref(false)
const isEdit = ref(false)
const editingId = ref<number | null>(null)
const formRef = ref()

const formData = reactive({
  name: '',
  description: '',
  estimated_hours: 0,
  sequence: 0,
  status: 'active',
})

const formRules = {
  name: [{ required: true, message: '请输入工序名称', trigger: 'blur' }],
}

const formatHours = (h: unknown): string => {
  const n = Number(h)
  return Number.isFinite(n) ? `${n} h` : '0 h'
}

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter(r => (r.name || '').toLowerCase().includes(kw))
  }
  return arr.sort((a, b) => {
    const sa = Number(a.sequence ?? 9999)
    const sb = Number(b.sequence ?? 9999)
    if (sa !== sb) return sa - sb
    return Number(a.id ?? 0) - Number(b.id ?? 0)
  })
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const resetForm = () => {
  formData.name = ''
  formData.description = ''
  formData.estimated_hours = 0
  formData.sequence = 0
  formData.status = 'active'
}

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 500, page: 1 }
    const res = await workProcessApi.list(params)
    list.value = unwrapList(res) as WorkProcess[]
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const handleSearch = () => { page.value = 1 }
const handleReset = () => {
  searchForm.keyword = ''
  searchForm.status = ''
  page.value = 1
  loadList()
}

const handleAdd = () => {
  resetForm()
  isEdit.value = false
  editingId.value = null
  showDialog.value = true
}

const handleEdit = (row: Record<string, unknown>) => {
  formData.name = (row.name as string) || ''
  formData.description = (row.description as string) || ''
  formData.estimated_hours = Number(row.estimated_hours ?? 0)
  formData.sequence = Number(row.sequence ?? 0)
  formData.status = (row.status as string) || 'active'
  isEdit.value = true
  editingId.value = row.id as number
  showDialog.value = true
}

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const payload = {
      name: formData.name,
      description: formData.description || null,
      estimated_hours: Number(formData.estimated_hours || 0),
      sequence: Number(formData.sequence || 0),
      status: formData.status,
    }
    if (isEdit.value && editingId.value) {
      await workProcessApi.update(editingId.value, payload)
      ElMessage.success('已更新')
    } else {
      await workProcessApi.create(payload)
      ElMessage.success('已创建')
    }
    showDialog.value = false
    await loadList()
  } catch { /* 拦截器已提示 */ }
  finally { saving.value = false }
}

const handleDelete = async (row: Record<string, unknown>) => {
  try {
    await ElMessageBox.confirm(
      `确认删除工序「${row.name}」？该操作不可恢复。`,
      '删除确认',
      { type: 'warning', confirmButtonText: '确认删除', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    await workProcessApi.remove(Number(row.id))
    ElMessage.success('已删除')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

onMounted(() => loadList())
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title {
    font-size: 18px; font-weight: 600; color: #0C447C;
    border-left: 4px solid #0C447C; padding-left: 10px;
  }
  .header-actions { display: flex; gap: 8px; }
}
.filter-bar {
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.content-card {
  background: #fff; border-radius: 8px; padding: 16px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.pagination-wrapper { margin-top: 12px; display: flex; justify-content: flex-end; }
.form-tip { margin-left: 8px; color: #909399; font-size: 12px; }
</style>