<template>
  <div class="page-container">
    <div class="page-header">
      <div class="title-area">
        <span class="page-title">项目池</span>
        <el-tag effect="light" type="info">{{ list.length }} 个待转项目</el-tag>
        <el-tag effect="plain" type="success">合计 ¥ {{ formatMoney(totalAmount) }}</el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="Files" plain @click="$router.push('/project/list')">施工管理</el-button>
      </div>
    </div>

    <div class="alert-row">
      <el-alert type="info" :closable="false" show-icon>
        <template #title>
          项目池是商机签约后的临时存放区。点击「转为施工项目」可将项目分配给项目经理并自动建好施工档案（预算/工期/团队）。
        </template>
      </el-alert>
    </div>

    <div class="content-card">
      <el-table
        :data="pagedList"
        stripe
        border
        v-loading="loading"
        :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
      >
        <el-table-column prop="pool_no" label="池编号" width="160" fixed>
          <template #default="{ row }">
            <span class="pool-no">{{ row.pool_no }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="项目名称" min-width="240" show-overflow-tooltip />
        <el-table-column label="客户" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="contract_amount" label="合同金额" width="140" align="right">
          <template #default="{ row }">
            <span style="color: #1D9E75; font-weight: 600">¥ {{ formatMoney(row.contract_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="签约日期" width="120" align="center">
          <template #default="{ row }">{{ formatDate(row.signed_at) }}</template>
        </el-table-column>
        <el-table-column label="来源商机" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">{{ row.opportunity?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="dark" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="关联施工项目" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <el-link
              v-if="row.project"
              type="primary"
              :underline="false"
              @click="$router.push(`/project/detail/${row.project.id}`)"
            >
              {{ row.project.name }}
            </el-link>
            <span v-else class="text-muted">未分配</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="row.status === 'pending'"
              link
              type="success"
              @click="handleConvert(row)"
            >
              转为施工项目
            </el-button>
            <el-button
              v-if="row.status === 'pending'"
              link
              type="warning"
              @click="handleStockOut(row)"
            >
              转为出库单
            </el-button>
            <el-button
              v-if="row.status === 'active'"
              link
              type="warning"
              @click="handleViewProject(row)"
            >
              查看施工档案
            </el-button>
            <el-button
              v-if="row.status === 'active'"
              link
              type="primary"
              @click="handleStockOut(row)"
            >
              出库
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </div>

    <ConvertDialog
      v-model:visible="showConvertDialog"
      :submitting="submitting"
      :target="convertTarget"
      :manager-options="managerOptions"
      :member-options="memberOptions"
      @confirm="confirmConvert"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Files } from '@element-plus/icons-vue'
import { getProjectPool, convertPoolToProject } from '@/api/sales'
import { getEmployeeList } from '@/api/employee'
import { PoolItem, EmployeeOption } from './types'
import ConvertDialog, { type ConvertFormData } from './components/ConvertDialog.vue'

const router = useRouter()
const loading = ref(false)
const submitting = ref(false)
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const list = ref<PoolItem[]>([])

const pagedList = computed(() => list.value)
const totalAmount = computed(() =>
  list.value.reduce((s, l) => s + Number(l.contract_amount || 0), 0),
)

const loadList = async () => {
  loading.value = true
  try {
    const r = await getProjectPool({ page: page.value, per_page: pageSize.value })
    // V0.6.3: res = {code, data: paginator}
    const d = r?.data ?? {}
    list.value = Array.isArray(d?.data) ? d.data : []
    total.value = d?.total || 0
  } catch (e) {
    /* toast 由拦截器统一处理 */
  } finally {
    loading.value = false
  }
}

const managerOptions = ref<EmployeeOption[]>([])
const memberOptions = ref<EmployeeOption[]>([])
const loadEmployees = async () => {
  try {
    const r = await getEmployeeList({ per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    const d = r?.data ?? {}
    const all = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : [])
    managerOptions.value = all
    memberOptions.value = all
  } catch (e) {
    /* fallback */
  }
}

const formatMoney = (n?: number | string) =>
  Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const formatDate = (d?: string) => (d ? d.slice(0, 10) : '-')

const STATUS_LABEL: Record<string, string> = {
  pending: '待转项目',
  active: '已转项目',
  archived: '已归档',
}
const STATUS_TAG: Record<string, 'warning' | 'success' | 'info'> = {
  pending: 'warning',
  active: 'success',
  archived: 'info',
}
const statusLabel = (s: string) => STATUS_LABEL[s] || s
const statusTagType = (s: string): 'warning' | 'success' | 'info' => STATUS_TAG[s] || 'info'

const handleViewProject = (row: PoolItem) => {
  if (row.project) router.push(`/project/detail/${row.project.id}`)
}

/** 转出库单：直接进入新增出库单页面，客户名称自动同步 */
const handleStockOut = (row: PoolItem) => {
  const query: Record<string, string> = { action: 'create' }
  // 客户
  if (row.customer?.id) query.customer_id = String(row.customer.id)
  if (row.customer?.name) query.customer_name = row.customer.name
  // 项目
  if (row.project?.id) query.project_id = String(row.project.id)
  if (row.name) query.project_name = row.name
  router.push({ path: '/inventory/outbound-order', query })
}

const showConvertDialog = ref(false)
const convertTarget = ref<PoolItem | null>(null)

const handleConvert = (row: PoolItem) => {
  convertTarget.value = row
  showConvertDialog.value = true
}

const confirmConvert = async (payload: ConvertFormData) => {
  if (!convertTarget.value) return
  submitting.value = true
  try {
    const r = await convertPoolToProject(convertTarget.value.id as number, payload)
    const project = r?.data || r
    ElMessage.success(`已转为施工项目，关联项目 #${project?.id || '?'}`)
    showConvertDialog.value = false
    convertTarget.value = null
    await loadList()
  } catch (e) {
    /* toast */
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadEmployees()
  loadList()
})
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 16px 20px;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .title-area { display: flex; align-items: center; gap: 10px; }
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.alert-row { margin-bottom: 12px; }
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.pool-no { color: #0C447C; font-weight: 500; }
.text-muted { color: #909399; font-style: italic; }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
