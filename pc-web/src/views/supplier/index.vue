<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">供应商管理</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="handleReset">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新增供应商</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="名称/编号/联系人/电话" clearable style="width: 240px" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="searchForm.type" placeholder="全部" clearable style="width: 120px">
            <el-option label="材料" value="material" />
            <el-option label="人工" value="labor" />
            <el-option label="外包" value="outsource" />
            <el-option label="服务" value="service" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="正常" value="active" />
            <el-option label="暂停" value="paused" />
            <el-option label="黑名单" value="blacklist" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="content-card">
      <SupplierTable
        :data="list"
        :loading="loading"
        @view="handleView"
        @edit="handleEdit"
        @evaluate="handleEvaluate"
        @delete="handleDelete"
      />
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          background
        />
      </div>
    </div>

    <SupplierFormDialog
      v-model:visible="showFormDialog"
      :editing="editingSupplier"
      @save="handleSave"
    />

    <SupplierDetailDialog
      v-model:visible="showDetailDialog"
      :detail="detail"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh } from '@element-plus/icons-vue'
import { supplier } from '@/api/supplier'
import { unwrapPaginate } from '@/utils/response'
import type { Supplier, SupplierDetail } from '@/api/supplier'
import SupplierTable from './components/SupplierTable.vue'
import SupplierFormDialog from './components/SupplierFormDialog.vue'
import SupplierDetailDialog from './components/SupplierDetailDialog.vue'

const loading = ref(false)
const list = ref<Supplier[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)

const searchForm = reactive({
  keyword: '',
  type: '',
  status: '',
})

const showFormDialog = ref(false)
const showDetailDialog = ref(false)
const editingSupplier = ref<Supplier | null>(null)
const detail = ref<SupplierDetail | null>(null)

const loadList = async () => {
  loading.value = true
  try {
    const res = await supplier.list({
      keyword: searchForm.keyword || undefined,
      type: searchForm.type || undefined,
      status: searchForm.status || undefined,
      page: page.value,
      per_page: pageSize.value,
    })
    // 不解包: res = {code, message, data: {items, total}}
    const pag = unwrapPaginate(res)
    list.value = pag.list
    total.value = pag.total
  } catch {
    list.value = []; total.value = 0
  } finally {
    loading.value = false
  }
}

const handleSearch = () => { page.value = 1; loadList() }
const handleReset = () => {
  searchForm.keyword = ''; searchForm.type = ''; searchForm.status = ''
  page.value = 1; loadList()
}

const handleAdd = () => {
  editingSupplier.value = null
  showFormDialog.value = true
}

const handleView = async (row: Supplier) => {
  const res = await supplier.get(row.id)
  detail.value = res.data
  showDetailDialog.value = true
}

const handleEdit = (row: Supplier) => {
  editingSupplier.value = row
  showFormDialog.value = true
}

const handleEvaluate = (row: Supplier) => {
  ElMessage.info(`评价供应商「${row.name}」功能在详情页中`)
  // 简化：暂未做独立评价页
}

const handleDelete = async (row: Supplier) => {
  try {
    await ElMessageBox.confirm(`确认删除供应商「${row.name}」？该操作不可恢复。`, '删除确认', {
      type: 'warning', confirmButtonText: '确认删除', cancelButtonText: '取消',
    })
  } catch { return }
  try {
    await supplier.remove(row.id)
    ElMessage.success('已删除')
    loadList()
  } catch { /* 拦截器已提示 */ }
}

const handleSave = async (payload: Record<string, unknown>) => {
  try {
    if (editingSupplier.value?.id) {
      await supplier.update(editingSupplier.value.id, payload)
      ElMessage.success('已更新')
    } else {
      await supplier.create(payload)
      ElMessage.success('已创建')
    }
    showFormDialog.value = false
    editingSupplier.value = null
    page.value = 1
    loadList()
  } catch { /* 拦截器已提示 */ }
}

onMounted(loadList)
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
  background: #fff; padding: 16px 20px; border-radius: 8px;
  margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.content-card {
  background: #fff; padding: 20px; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
