<template>
  <div class="page-container">
    <div class="page-header">
      <h2>调度管理</h2>
      <div class="header-actions">
        <el-button type="primary" @click="showApply = true">用车申请</el-button>
      </div>
    </div>

    <VehicleApplyDialog v-model:visible="showApply" @success="loadList" />

    <div class="stats-row">
      <el-card class="stat-card" shadow="hover">
        <div class="stat-label">待审批</div>
        <div class="stat-value warning">{{ stats.pending || 0 }}</div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-label">已批准</div>
        <div class="stat-value success">{{ stats.approved || 0 }}</div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-label">使用中</div>
        <div class="stat-value primary">{{ stats.using || 0 }}</div>
      </el-card>
      <el-card class="stat-card" shadow="hover">
        <div class="stat-label">近 30 天申请</div>
        <div class="stat-value">{{ stats.monthRequests || 0 }}</div>
      </el-card>
    </div>

    <div class="filter-bar">
      <el-input v-model="searchForm.keyword" placeholder="搜索申请人/车牌号/目的地" clearable style="width: 260px" @keyup.enter="loadList" />
      <el-select v-model="searchForm.status" placeholder="状态" clearable style="width: 150px" @change="loadList">
        <el-option label="待审批" value="pending" />
        <el-option label="已批准" value="approved" />
        <el-option label="已驳回" value="rejected" />
        <el-option label="使用中" value="using" />
        <el-option label="已归还" value="returned" />
        <el-option label="已取消" value="cancelled" />
      </el-select>
      <el-button type="primary" @click="loadList">搜索</el-button>
      <el-button @click="resetSearch">重置</el-button>
    </div>

    <div class="content-card">
      <el-table :data="filteredData" stripe border style="width: 100%" v-loading="loading">
        <el-table-column label="申请人" width="100">
          <template #default="{ row }">{{ row.applicant?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="车牌号" width="130">
          <template #default="{ row }">{{ row.vehicle?.plate_no || '-' }}</template>
        </el-table-column>
        <el-table-column prop="usage_date" label="日期" width="120" />
        <el-table-column label="时间段" width="140">
          <template #default="{ row }">{{ row.start_time }} - {{ row.end_time }}</template>
        </el-table-column>
        <el-table-column prop="destination" label="目的地" width="160" show-overflow-tooltip />
        <el-table-column prop="purpose" label="事由" min-width="160" show-overflow-tooltip />
        <el-table-column label="人数" width="70" align="center">
          <template #default="{ row }">{{ row.passengers || 1 }}</template>
        </el-table-column>
        <el-table-column label="自驾" width="70" align="center">
          <template #default="{ row }">
            <el-tag :type="row.self_drive ? 'warning' : 'info'" size="small">{{ row.self_drive ? '自驾' : '代驾' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="dispatchStatusType(row.status)">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status === 'pending'">
              <el-tag size="small" type="info" effect="plain">去审批中心</el-tag>
            </template>
            <template v-else-if="row.status === 'approved'">
              <el-button link type="primary" size="small" @click="openDispatch(row)">派车</el-button>
            </template>
            <template v-else-if="row.status === 'using'">
              <el-button link type="warning" size="small" @click="openReturn(row)">归还</el-button>
            </template>
            <el-button link type="primary" size="small" @click="handleViewDetail(row)">查看</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          background
          layout="total, sizes, prev, pager, next"
          :total="total"
          :page-sizes="[10, 20, 50]"
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </div>

    <!-- 派车对话框 -->
    <el-dialog v-model="dispatchDialogVisible" title="派车确认" width="1500px">
      <el-form :model="dispatchForm" label-width="100px" v-if="dispatchRow">
        <el-descriptions :column="1" border size="small" class="mb">
          <el-descriptions-item label="申请人">{{ dispatchRow.applicant?.name }}</el-descriptions-item>
          <el-descriptions-item label="日期">{{ dispatchRow.usage_date }} {{ dispatchRow.start_time }}-{{ dispatchRow.end_time }}</el-descriptions-item>
          <el-descriptions-item label="目的地">{{ dispatchRow.destination }}</el-descriptions-item>
        </el-descriptions>
        <el-form-item label="分配车辆">
          <el-select v-model="dispatchForm.vehicle_id" placeholder="请选择车辆" filterable style="width: 100%">
            <el-option v-for="v in availableVehicles" :key="v.id" :label="`${v.plate_no} / ${v.brand} ${v.model}`" :value="v.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dispatchDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="dispatching" @click="confirmDispatch">确认派车</el-button>
      </template>
    </el-dialog>

    <!-- 归还对话框 -->
    <el-dialog v-model="returnDialogVisible" title="车辆归还登记" width="1500px">
      <el-form :model="returnForm" label-width="100px">
        <el-form-item label="出车里程">
          <el-input-number v-model="returnForm.start_mileage" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="还车里程">
          <el-input-number v-model="returnForm.end_mileage" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="实际油耗">
          <el-input-number v-model="returnForm.actual_fuel" :min="0" :precision="1" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="returnDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="returning" @click="confirmReturn">确认归还</el-button>
      </template>
    </el-dialog>

    <!-- 详情对话框 -->
    <el-dialog v-model="showViewDialog" title="用车详情" width="1500px">
      <el-descriptions :column="2" border v-if="viewRow">
        <el-descriptions-item label="申请人">{{ viewRow.applicant?.name }}</el-descriptions-item>
        <el-descriptions-item label="审批人">{{ viewRow.approver?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="车牌号">{{ viewRow.vehicle?.plate_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="日期">{{ viewRow.usage_date }}</el-descriptions-item>
        <el-descriptions-item label="时间段">{{ viewRow.start_time }} - {{ viewRow.end_time }}</el-descriptions-item>
        <el-descriptions-item label="人数">{{ viewRow.passengers || 1 }}</el-descriptions-item>
        <el-descriptions-item label="目的地" :span="2">{{ viewRow.destination }}</el-descriptions-item>
        <el-descriptions-item label="用车事由" :span="2">{{ viewRow.purpose }}</el-descriptions-item>
        <el-descriptions-item label="自驾">{{ viewRow.self_drive ? '是' : '否' }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="dispatchStatusType(viewRow.status)">{{ statusLabel(viewRow.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="出车里程">{{ viewRow.start_mileage || '-' }}</el-descriptions-item>
        <el-descriptions-item label="还车里程">{{ viewRow.end_mileage || '-' }}</el-descriptions-item>
        <el-descriptions-item label="实际油耗">{{ viewRow.actual_fuel || '-' }}</el-descriptions-item>
        <el-descriptions-item label="审批时间">{{ viewRow.approved_at || '-' }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="showViewDialog = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { get, post, put, del } from '@/utils/request'
import VehicleApplyDialog from './Apply.vue'

const showApply = ref(false)

interface VehicleApplicant { name?: string; [key: string]: unknown }
interface VehicleBrief { id?: number; plate_no?: string; brand?: string; model?: string; [key: string]: unknown }
interface VehicleUsage {
  id?: number
  applicant?: VehicleApplicant | null
  vehicle?: VehicleBrief | null
  approver?: VehicleApplicant | null
  usage_date?: string
  start_time?: string
  end_time?: string
  destination?: string
  purpose?: string
  passengers?: number
  self_drive?: boolean
  status?: string
  vehicle_id?: number | null
  start_mileage?: number
  end_mileage?: number
  actual_fuel?: number
  approved_at?: string
  [key: string]: unknown
}
interface AvailableVehicle {
  id?: number
  plate_no?: string
  brand?: string
  model?: string
  [key: string]: unknown
}

const searchForm = ref({ keyword: '', status: '' })
const list = ref<VehicleUsage[]>([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)

const stats = ref<Record<string, number>>({})

const availableVehicles = ref<AvailableVehicle[]>([])

const dispatchStatusMap: Record<string, { label: string; type: 'success' | 'warning' | 'info' | 'danger' | 'primary' }> = {
  pending: { label: '待审批', type: 'warning' },
  approved: { label: '已批准', type: 'success' },
  rejected: { label: '已驳回', type: 'danger' },
  using: { label: '使用中', type: 'primary' },
  returned: { label: '已归还', type: 'info' },
  cancelled: { label: '已取消', type: 'info' },
}
const statusLabel = (s?: string) => dispatchStatusMap[s ?? '']?.label || s || '-'
const dispatchStatusType = (s?: string): 'success' | 'warning' | 'info' | 'danger' | 'primary' => dispatchStatusMap[s ?? '']?.type || 'info'

const loadList = async () => {
  loading.value = true
  try {
    const res = await get('/vehicles/usage', {
      page: page.value,
      pageSize: pageSize.value,
    })
    const data = res.data || res
    list.value = data?.data || data?.items || data || []
    total.value = data?.total || list.value.length
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '加载用车申请失败')
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  try {
    const res = await get('/vehicles/stats')
    const data = res.data || res
    stats.value = {
      pending: data?.pending || 0,
      approved: data?.approved || 0,
      using: 0,
      monthRequests: data?.monthRequests || 0,
    }
  } catch (e) { /* ignore */ }
}

const loadAvailableVehicles = async () => {
  try {
    const res = await get('/vehicles', { status: 'available' })
    availableVehicles.value = (res.data || res) || []
  } catch (e) { /* ignore */ }
}

onMounted(() => {
  loadList()
  loadStats()
  loadAvailableVehicles()
})

const filteredData = computed(() => {
  return list.value.filter((item: VehicleUsage) => {
    const k = searchForm.value.keyword
    const matchKeyword = !k
      || (item.applicant?.name || '').includes(k)
      || (item.vehicle?.plate_no || '').includes(k)
      || (item.destination || '').includes(k)
    const matchStatus = !searchForm.value.status || item.status === searchForm.value.status
    return matchKeyword && matchStatus
  })
})

const resetSearch = () => { searchForm.value = { keyword: '', status: '' } }

// V1.2.7h: 用车审批统一进审批中心, 此处不再处理

// ===== 派车对话框 =====
const dispatchDialogVisible = ref(false)
const dispatching = ref(false)
const dispatchRow = ref<VehicleUsage | null>(null)
const dispatchForm = reactive({ vehicle_id: null as number | null })

const openDispatch = (row: VehicleUsage) => {
  dispatchRow.value = row
  dispatchForm.vehicle_id = row.vehicle_id || null
  dispatchDialogVisible.value = true
  loadAvailableVehicles()
}

const confirmDispatch = async () => {
  if (!dispatchRow.value || !dispatchForm.vehicle_id) {
    ElMessage.warning('请选择车辆')
    return
  }
  dispatching.value = true
  try {
    await post(`/vehicles/usage/${dispatchRow.value.id}/dispatch`, {
      vehicle_id: dispatchForm.vehicle_id,
      action: 'using',
    })
    ElMessage.success('已派车出发')
    dispatchDialogVisible.value = false
    loadList()
    loadStats()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '操作失败')
  } finally {
    dispatching.value = false
  }
}

// ===== 归还 =====
const returnDialogVisible = ref(false)
const returning = ref(false)
const returnRow = ref<VehicleUsage | null>(null)
const returnForm = reactive({ start_mileage: 0, end_mileage: 0, actual_fuel: 0 })

const openReturn = (row: VehicleUsage) => {
  returnRow.value = row
  returnForm.start_mileage = row.start_mileage || 0
  returnForm.end_mileage = row.end_mileage || 0
  returnForm.actual_fuel = row.actual_fuel || 0
  returnDialogVisible.value = true
}

const confirmReturn = async () => {
  if (!returnRow.value) return
  returning.value = true
  try {
    await post(`/vehicles/usage/${returnRow.value.id}/dispatch`, {
      action: 'returned',
    })
    // 同时更新里程油耗
    await put(`/vehicles/usage/${returnRow.value.id}`, {
      start_mileage: returnForm.start_mileage,
      end_mileage: returnForm.end_mileage,
      actual_fuel: returnForm.actual_fuel,
    })
    ElMessage.success('车辆已归还登记完成')
    returnDialogVisible.value = false
    loadList()
    loadStats()
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '操作失败')
  } finally {
    returning.value = false
  }
}

// ===== 详情 =====
const showViewDialog = ref(false)
const viewRow = ref<VehicleUsage | null>(null)

const handleViewDetail = async (row: VehicleUsage) => {
  try {
    // V0.6.3: res = {code, data: <all usages>}
    const res = await get(`/vehicles/usage`)
    const d = res?.data ?? []
    const all = Array.isArray(d) ? d : []
    viewRow.value = all.find((r: VehicleUsage) => r.id === row.id) || row
  } catch {
    viewRow.value = row
  }
  showViewDialog.value = true
}
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header { margin-bottom: 16px; h2 { font-size: 20px; color: #0C447C; margin: 0; } }
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-label { color: #909399; font-size: 13px; margin-bottom: 6px; }
.stat-value { font-size: 26px; font-weight: 600; color: #303133; }
.stat-value.warning { color: #E6A23C; }
.stat-value.success { color: #67C23A; }
.stat-value.primary { color: #409EFF; }
.filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding: 16px; background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.content-card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.mb { margin-bottom: 16px; }
</style>
