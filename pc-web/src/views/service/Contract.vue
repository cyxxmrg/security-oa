<template>
  <div class="page-container">
    <div class="page-header">
      <h2>维保合同管理</h2>
      <el-button type="primary" :icon="Plus" @click="showCreate = true">新建维保合同</el-button>
    </div>
    <div class="filter-bar">
      <el-input v-model="searchForm.keyword" placeholder="搜索合同编号/客户名称" clearable style="width: 240px" @keyup.enter="loadList(1)" />
      <el-select v-model="searchForm.status" placeholder="合同状态" clearable style="width: 140px">
        <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="loadList(1)">搜索</el-button>
      <el-button @click="resetSearch">重置</el-button>
    </div>
    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width: 100%">
        <el-table-column prop="contract_no" label="合同编号" width="160" />
        <el-table-column label="客户" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.customer">{{ row.customer.name }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="合同金额(元)" width="140" align="right">
          <template #default="{ row }">
            <span style="font-weight: 600; color: #0C447C">¥{{ Number(row.amount || 0).toLocaleString() }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="start_date" label="起始日期" width="120" />
        <el-table-column prop="end_date" label="截止日期" width="120" />
        <el-table-column prop="inspect_freq" label="巡检频率" width="120" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="contractStatusType(row.status)">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleView(row)">查看</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          background
          layout="total, prev, pager, next"
          :total="pagination.total"
          :current-page="pagination.page"
          :page-size="pagination.per_page"
          @current-change="(p) => loadList(p)"
        />
      </div>
    </div>

    <!-- 合同详情对话框 -->
    <el-dialog v-model="showDetailDialog" title="合同详情" width="1500px">
      <el-descriptions v-if="detailRow" :column="2" border>
        <el-descriptions-item label="合同编号">{{ detailRow.contract_no }}</el-descriptions-item>
        <el-descriptions-item label="客户">{{ detailRow.customer?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="合同金额">
          <span style="font-weight:600;color:#0C447C">¥{{ Number(detailRow.amount || 0).toLocaleString() }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="巡检频率">{{ detailRow.inspect_freq || '-' }}</el-descriptions-item>
        <el-descriptions-item label="起始日期">{{ detailRow.start_date || '-' }}</el-descriptions-item>
        <el-descriptions-item label="截止日期">{{ detailRow.end_date || '-' }}</el-descriptions-item>
        <el-descriptions-item label="合同状态" :span="2">
          <el-tag :type="contractStatusType(detailRow.status)">{{ statusLabel(detailRow.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item v-if="detailRow.remark" label="备注" :span="2">
          <div style="white-space: pre-wrap;">{{ detailRow.remark }}</div>
        </el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>
    <CreateContractDialog v-model="showCreate" @done="loadList" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Search, Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get } from '@/utils/request'
import { unwrapPaginate } from '@/utils/response'
import CreateContractDialog from './components/CreateContractDialog.vue'

const statusOptions = [
  { value: 'active',   label: '生效中' },
  { value: 'expiring', label: '即将到期' },
  { value: 'expired',  label: '已过期' },
]

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

const statusLabel = (s: string) => statusOptions.find(o => o.value === s)?.label || s
const contractStatusType = (s: string): TagType => {
  const map: Record<string, TagType> = {
    active:   'success',
    expiring: 'warning',
    expired:  'danger',
  }
  return map[s] || 'info'
}

const searchForm = ref({ keyword: '', status: '' })
const list = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const pagination = reactive({ page: 1, per_page: 15, total: 0 })
const showCreate = ref(false)

async function loadList(page = 1) {
  pagination.page = page
  loading.value = true
  try {
    const params: Record<string, unknown> = { page, per_page: pagination.per_page }
    if (searchForm.value.status) params.status = searchForm.value.status
    const res = await get('/service/maintenance-contracts', params)
    // V0.6.3: res = {code, data: paginator}
    const pag = unwrapPaginate(res)
    list.value = pag.list
    pagination.total = pag.total
  } catch (e) {
    console.error('[loadList]', e)
    list.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

function resetSearch() {
  searchForm.value = { keyword: '', status: '' }
  loadList(1)
}

const showDetailDialog = ref(false)
const detailRow = ref<Record<string, unknown> | null>(null)
const handleView = (row: Record<string, unknown>) => {
  detailRow.value = row
  showDetailDialog.value = true
}

onMounted(() => loadList(1))
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header {
  margin-bottom: 16px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
}
.filter-bar {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 16px; padding: 16px;
  background: #fff; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  flex-wrap: wrap;
}
.content-card {
  background: #fff; border-radius: 8px; padding: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.pagination-wrap {
  display: flex; justify-content: flex-end; margin-top: 16px;
}
.muted { color: #c0c4cc; }
</style>
