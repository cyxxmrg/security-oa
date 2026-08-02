<template>
  <div class="page-container">
    <div class="page-header">
      <h2>车辆档案</h2>
      <div class="header-actions">
        <el-button type="primary" @click="handleCreate">新建车辆档案</el-button>
      </div>
    </div>
    <VehicleFilterBar
      v-model:keyword="searchForm.keyword"
      v-model:status="searchForm.status"
      :status-map="statusMap"
      @search="handleSearch"
      @reset="resetSearch"
      @create="handleCreate"
    />
    <div class="content-card">
      <el-table :data="filteredData" stripe border style="width: 100%" v-loading="loading">
        <el-table-column prop="plate_no" label="车牌号" width="130" />
        <el-table-column label="品牌型号" width="200">
          <template #default="{ row }">{{ row.brand }} {{ row.model }}</template>
        </el-table-column>
        <el-table-column label="使用部门" width="120">
          <template #default="{ row }">{{ row.department?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="责任人" width="100">
          <template #default="{ row }">{{ row.responsibleUser?.name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="dark">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleView(row)">查看</el-button>
            <el-button link type="primary" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
        <el-table-column label="保险" width="140" align="center">
          <template #default="{ row }">
            <div v-if="row.insurance_end_date" class="status-cell">
              <div :class="getInsuranceClass(row.insurance_end_date)">
                📅 {{ formatDate(row.insurance_end_date) }}
              </div>
              <div class="status-sub">{{ getInsuranceTip(row.insurance_end_date) }}</div>
            </div>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="保养" width="140" align="center">
          <template #default="{ row }">
            <div v-if="row.next_maintenance_mileage || row.next_maintenance_date" class="status-cell">
              <div v-if="row.next_maintenance_mileage" class="status-main">
                🔧 {{ row.next_maintenance_mileage }} km
              </div>
              <div v-if="row.next_maintenance_date" class="status-sub">
                📅 {{ formatDate(row.next_maintenance_date) }}
              </div>
            </div>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="调度" width="90" align="center">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="goDispatch(row)">详情</el-button>
          </template>
        </el-table-column>
        <el-table-column label="油卡" width="180" align="center">
          <template #default="{ row }">
            <div v-if="row.last_recharge" class="status-cell">
              <div class="status-main">
                💰 ¥ {{ formatMoney(row.last_recharge.amount) }}
              </div>
              <div class="status-sub">
                📅 {{ formatDate(row.last_recharge.recharge_date) }}
              </div>
            </div>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="createDialogVisible" title="新增车辆" width="1500px">
      <el-form ref="vehicleFormRef" :model="vehicleForm" :rules="vehicleRules" label-width="100px">
        <el-form-item label="车牌号" prop="plate_no">
          <el-input v-model="vehicleForm.plate_no" placeholder="请输入车牌号" />
        </el-form-item>
        <el-form-item label="品牌" prop="brand">
          <el-input v-model="vehicleForm.brand" placeholder="请输入品牌" />
        </el-form-item>
        <el-form-item label="型号" prop="model">
          <el-input v-model="vehicleForm.model" placeholder="请输入型号" />
        </el-form-item>
        <el-form-item label="使用部门" prop="department_id">
          <el-select v-model="vehicleForm.department_id" placeholder="请选择部门" style="width: 100%" filterable>
            <el-option v-for="d in departments" :key="d.id" :label="d.name" :value="d.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="责任人" prop="responsible_user_id">
          <el-select v-model="vehicleForm.responsible_user_id" placeholder="请选择责任人" style="width: 100%" filterable>
            <el-option v-for="u in users" :key="u.id" :label="u.name" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-select v-model="vehicleForm.status" placeholder="请选择状态" style="width: 100%">
            <el-option v-for="(v, k) in statusMap" :key="k" :label="v.label" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注" prop="notes">
          <el-input v-model="vehicleForm.notes" type="textarea" :rows="2" placeholder="备注信息" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleVehicleSubmit">确认</el-button>
      </template>
    </el-dialog>

    <VehicleDetailDialog
      v-model:visible="showDetailDialog"
      :title="`车辆详情 — ${detailRow?.plate_no || ''}`"
      :row="detailRow"
      :insurances="insurances"
      :maintenances="maintenances"
      :fuel-cards="fuelCards"
      :card-recharges="cardRecharges"
      :status-label="statusLabel"
      :status-tag-type="statusTagType"
      :fuel-type-label="fuelTypeLabel"
      :format-money="formatMoney"
      :format-date="formatDate"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Right } from '@element-plus/icons-vue'
import { useRouter } from 'vue-router'
import { get, post, put, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import VehicleDetailDialog from './components/vehicle-list/VehicleDetailDialog.vue'

// ===== 类型定义 =====
interface NamedEntity { id: number; name?: string; [k: string]: unknown }
interface DepartmentItem extends NamedEntity {}
interface UserItem extends NamedEntity {}
interface VehicleRecharge { recharge_date?: string; amount?: number; [k: string]: unknown }
interface VehicleItem {
  id: number | string
  plate_no?: string
  plateNo?: string
  brand?: string
  model?: string
  department_id?: number | null
  responsible_user_id?: number | null
  status?: string
  notes?: string
  insurance_end_date?: string
  next_maintenance_mileage?: number
  next_maintenance_date?: string
  last_recharge?: VehicleRecharge | null
  department?: NamedEntity
  responsibleUser?: NamedEntity
  [k: string]: unknown
}
interface VehicleInsurance { id?: number; [k: string]: unknown }
interface VehicleMaintenance { id?: number; [k: string]: unknown }
interface FuelCard { id: number | string; [k: string]: unknown }
interface CardRecharge { card_id?: number | string; recharge_date?: string; amount?: number; [k: string]: unknown }
interface ApiResponse<T = unknown> { data?: T; [k: string]: unknown }
interface ApiError { message?: string; [k: string]: unknown }

const searchForm = ref({ keyword: '', status: '' })
const createDialogVisible = ref(false)
const vehicleFormRef = ref()
const router = useRouter()

const list = ref<VehicleItem[]>([])
const loading = ref(false)
const editingId = ref<number | null>(null)
const departments = ref<DepartmentItem[]>([])
const users = ref<UserItem[]>([])

const loadDepartments = async () => {
  try {
    // V0.6.3: res = {code, data: <departments>}
    const res: ApiResponse = await get('/departments')
    departments.value = unwrapList(res)
  } catch (e) { /* ignore */ }
}

const loadUsers = async () => {
  try {
    // V0.6.3: res = {code, data: paginator}
    const res: ApiResponse<{ items?: UserItem[]; data?: UserItem[] }> = await get('/employees', { page: 1, pageSize: 500 })
    const d = res?.data ?? {}
    users.value = Array.isArray(d?.items) ? d.items : (Array.isArray(d?.data) ? d.data : [])
  } catch (e) { /* ignore */ }
}

const statusMap: Record<string, { label: string; type: 'success' | 'warning' | 'info' | 'danger' }> = {
  available: { label: '可用', type: 'success' },
  in_use: { label: '使用中', type: 'warning' },
  maintenance: { label: '维修中', type: 'info' },
  retired: { label: '已停用', type: 'danger' },
}
const statusLabel = (s: string) => statusMap[s]?.label || s
const statusTagType = (s: string): 'success' | 'warning' | 'info' | 'danger' => statusMap[s]?.type || 'info'

const loadList = async () => {
  loading.value = true
  try {
    const res: ApiResponse = await get('/vehicles')
    let vehicles = (res.data || res) || []
    // 模拟每辆车的保险/保养/油卡状态（实际项目从后端聚合）
    vehicles = vehicles.map((v: VehicleItem) => ({
      ...v,
      // 保险到期日（mock：90 天内随机）
      insurance_end_date: v.insurance_end_date || mockDate(30),
      // 下次保养（mock：1 万公里内随机 + 30 天内日期）
      next_maintenance_mileage: v.next_maintenance_mileage || mockMileage(),
      next_maintenance_date: v.next_maintenance_date || mockDate(45),
      // 最近油卡充值
      last_recharge: v.last_recharge || mockRecharge()
    }))
    list.value = vehicles
  } catch (e: ApiError) {
    ElMessage.error(e?.message || '加载车辆列表失败')
  } finally {
    loading.value = false
  }
}

// 模拟数据（实际项目由后端聚合返回）
const mockDate = (daysFromNow: number) => {
  const d = new Date()
  d.setDate(d.getDate() + Math.floor(Math.random() * daysFromNow) - 15)
  return d.toISOString().slice(0, 10)
}
const mockMileage = () => Math.floor(Math.random() * 10000) + 50000
const mockRecharge = () => {
  if (Math.random() < 0.2) return null  // 20% 没充过
  const d = new Date()
  d.setDate(d.getDate() - Math.floor(Math.random() * 60))
  return {
    recharge_date: d.toISOString().slice(0, 10),
    amount: (Math.floor(Math.random() * 30) + 5) * 100
  }
}

// 保险到期日样式（30天内红色，60天内橙色）
const getInsuranceClass = (endDate: string) => {
  const days = Math.ceil((new Date(endDate).getTime() - Date.now()) / (1000 * 60 * 60 * 24))
  if (days < 0) return 'status-danger'
  if (days <= 30) return 'status-danger'
  if (days <= 60) return 'status-warning'
  return 'status-normal'
}
const getInsuranceTip = (endDate: string) => {
  const days = Math.ceil((new Date(endDate).getTime() - Date.now()) / (1000 * 60 * 60 * 24))
  if (days < 0) return `已过期 ${-days} 天`
  if (days === 0) return '今天到期'
  if (days <= 30) return `${days} 天内到期`
  return `${days} 天后到期`
}

onMounted(() => {
  loadList()
  loadDepartments()
  loadUsers()
})

const filteredData = computed(() => {
  return list.value.filter((item: VehicleItem) => {
    const plate = item.plate_no || item.plateNo || ''
    const model = (item.brand || '') + ' ' + (item.model || '')
    const matchKeyword = !searchForm.value.keyword || plate.includes(searchForm.value.keyword) || model.includes(searchForm.value.keyword)
    const matchStatus = !searchForm.value.status || item.status === searchForm.value.status
    return matchKeyword && matchStatus
  })
})

const vehicleForm = reactive({
  plate_no: '',
  brand: '',
  model: '',
  department_id: null as number | null,
  responsible_user_id: null as number | null,
  status: 'available',
  notes: '',
})

const vehicleRules = {
  plate_no: [{ required: true, message: '请输入车牌号', trigger: 'blur' }],
  brand: [{ required: true, message: '请输入品牌', trigger: 'blur' }],
  model: [{ required: true, message: '请输入型号', trigger: 'blur' }],
}

// ===== 查看详情 (整合 保险+保养+油卡) =====
const showDetailDialog = ref(false)
const detailRow = ref<VehicleItem | null>(null)
const detailTab = ref('insurance')
const insurances = ref<VehicleInsurance[]>([])
const maintenances = ref<VehicleMaintenance[]>([])
const fuelCards = ref<FuelCard[]>([])
const cardRecharges = ref<CardRecharge[]>([])

const fuelTypeLabel = (t: string) => ({ gas: '汽油', diesel: '柴油', electric: '电', hybrid: '混动' }[t] || t || '-')
const formatDate = (d: string) => d ? String(d).slice(0, 10) : '-'
const formatMoney = (v: unknown) => {
  const n = parseFloat(v)
  return isNaN(n) ? '0.00' : n.toFixed(2)
}

const goDetail = (row: VehicleItem, tab: 'insurance' | 'maintenance' | 'fuelcard') => {
  detailRow.value = row
  showDetailDialog.value = true
  detailTab.value = tab
  Promise.all([loadInsurances(row.id), loadMaintenances(row.id), loadFuelCards(row.id)])
}

const goDispatch = (row: VehicleItem) => {
  router.push({ path: '/vehicle/dispatch', query: { vehicle_id: row.id } })
}

// 解包分页结构: 后端返回 {code, message, data: {current_page, data: array, total}}
// 拦截器不解包, res = {code, data: <分页对象>}; <分页对象>.data 是数组
// V1.2.10: 改用 @/utils/response 的 unwrapList (覆盖更全)

const loadInsurances = async (vehicleId: number) => {
  try {
    const res: ApiResponse = await get('/vehicles/insurances', { vehicle_id: vehicleId, per_page: 50 })
    insurances.value = unwrapList(res)
  } catch { insurances.value = [] }
}

const loadMaintenances = async (vehicleId: number) => {
  try {
    const res: ApiResponse = await get('/vehicles/maintenances', { vehicle_id: vehicleId, per_page: 50 })
    maintenances.value = unwrapList(res)
  } catch { maintenances.value = [] }
}

const loadFuelCards = async (vehicleId: number) => {
  try {
    const res: ApiResponse = await get('/fuel-cards', { vehicle_id: vehicleId, per_page: 50 })
    fuelCards.value = unwrapList(res)
    if (fuelCards.value.length > 0) {
      const cardIds = fuelCards.value.map((c: FuelCard) => c.id)
      const r2: ApiResponse = await get('/fuel-cards/recharges', { per_page: 50 })
      const allRecharges = unwrapList(r2)
      cardRecharges.value = allRecharges.filter((r: CardRecharge) => cardIds.includes(r.card_id))
    } else {
      cardRecharges.value = []
    }
  } catch { fuelCards.value = []; cardRecharges.value = [] }
}

const handleEdit = (row: VehicleItem) => {
  editingId.value = row.id
  Object.assign(vehicleForm, {
    plate_no: row.plate_no || row.plateNo,
    brand: row.brand,
    model: row.model,
    department_id: row.department_id,
    responsible_user_id: row.responsible_user_id,
    status: row.status || 'available',
    notes: row.notes || '',
  })
  createDialogVisible.value = true
}

const handleView = (row: VehicleItem) => router.push(`/vehicle/detail/${row.id}`)
const handleDelete = async (row: VehicleItem) => {
  try {
    await ElMessageBox.confirm(`确定要删除车辆「${row.plate_no}」吗？`, '删除确认', { type: 'warning' })
  } catch { return }
  try {
    await del(`/vehicles/${row.id}`)
    ElMessage.success('已删除')
    loadList()
  } catch (e: ApiError) {
    ElMessage.error(e?.message || '删除失败')
  }
}

const handleSearch = () => {}
const resetSearch = () => { searchForm.value = { keyword: '', status: '' } }
const handleCreate = () => {
  editingId.value = null
  // V1.2.16: 清理旧字段, 避免残留属性干扰提交
  Object.assign(vehicleForm, { plate_no: '', brand: '', model: '', department_id: null, responsible_user_id: null, status: 'available', notes: '' })
  delete (vehicleForm as Record<string, unknown>).plateNo
  delete (vehicleForm as Record<string, unknown>).brandModel
  createDialogVisible.value = true
}

const handleVehicleSubmit = async () => {
  if (!vehicleFormRef.value) return
  try { await vehicleFormRef.value.validate() } catch { return }
  // V1.2.16: 用普通对象提交, 避免 reactive proxy 序列化问题
  const payload = {
    plate_no: vehicleForm.plate_no,
    brand: vehicleForm.brand,
    model: vehicleForm.model,
    department_id: vehicleForm.department_id,
    responsible_user_id: vehicleForm.responsible_user_id,
    status: vehicleForm.status,
    notes: vehicleForm.notes,
  }
  try {
    if (editingId.value) {
      await put(`/vehicles/${editingId.value}`, payload)
      ElMessage.success('已更新')
    } else {
      await post('/vehicles', payload)
      ElMessage.success('车辆添加成功')
    }
    createDialogVisible.value = false
    loadList()
  } catch (e: ApiError) {
    ElMessage.error(e?.message || '保存失败')
  }
}
</script>

<style lang="scss" scoped>
.page-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100vh;
}
.page-header {
  margin-bottom: 16px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
}
.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding: 16px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.content-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
.detail-tabs { margin-top: 16px; }
.empty-tip { text-align: center; color: #909399; padding: 32px 0; }
.date-range { display: flex; align-items: center; gap: 6px; color: #606266; }
.next-info { display: flex; flex-direction: column; gap: 2px; font-size: 12px; color: #67c23a; }
.sub-title { margin: 16px 0 8px; font-size: 14px; color: #303133; }
.balance { color: #e6a23c; font-weight: 600; font-size: 16px; }
.amount-add { color: #67c23a; font-weight: 600; }
.urgent-date {
  color: #A32D2D;
  font-weight: 600;
}

// 状态单元格
.status-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  line-height: 1.3;
}
.status-main {
  font-size: 13px;
  font-weight: 600;
  color: #0C447C;
}
.status-sub {
  font-size: 11px;
  color: #909399;
}
.status-danger {
  color: #A32D2D;
  font-weight: 600;
}
.status-warning {
  color: #BA7517;
  font-weight: 600;
}
.status-normal {
  color: #1D9E75;
  font-weight: 500;
}
.text-muted {
  color: #c0c4cc;
}
</style>