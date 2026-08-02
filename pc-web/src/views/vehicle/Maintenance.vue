<template>
  <div class="page-container">
    <div class="page-header">
      <h2>保养记录</h2>
      <div class="header-stats">
        <el-tag type="primary" effect="plain">本月保养支出 ¥{{ stats.monthMaintenanceCost || '0.00' }}</el-tag>
      </div>
    </div>

    <div class="filter-bar">
      <el-select v-model="searchForm.vehicle_id" placeholder="选择车辆" clearable filterable style="width: 200px">
        <el-option v-for="v in vehicles" :key="v.id" :label="`${v.plate_no} (${v.brand})`" :value="v.id" />
      </el-select>
      <el-select v-model="searchForm.maintenance_type" placeholder="保养类型" clearable style="width: 140px">
        <el-option v-for="(label, k) in typeMap" :key="k" :label="label" :value="k" />
      </el-select>
      <el-input v-model="searchForm.keyword" placeholder="搜索保养内容" clearable style="width: 220px" @keyup.enter="handleSearch" />
      <el-button type="primary" @click="handleSearch">搜索</el-button>
      <el-button @click="resetSearch">重置</el-button>
      <el-button type="primary" plain @click="openDialog()">新增保养</el-button>
    </div>

    <div class="content-card">
      <el-table :data="list" stripe border v-loading="loading">
        <el-table-column prop="id" label="#" width="60" />
        <el-table-column label="车辆" min-width="180">
          <template #default="{ row }">
            <span class="plate">{{ row.vehicle?.plate_no }}</span>
            <span class="vehicle-meta">{{ row.vehicle?.brand }} {{ row.vehicle?.model }}</span>
          </template>
        </el-table-column>
        <el-table-column label="保养类型" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="typeTagType(row.maintenance_type)" effect="plain">{{ typeMap[row.maintenance_type] || row.maintenance_type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="保养日期" width="120">
          <template #default="{ row }">{{ formatDate(row.maintenance_date) }}</template>
        </el-table-column>
        <el-table-column label="里程(km)" width="100" align="right">
          <template #default="{ row }">{{ row.mileage || '-' }}</template>
        </el-table-column>
        <el-table-column label="费用(元)" width="110" align="right">
          <template #default="{ row }">¥ {{ formatMoney(row.cost) }}</template>
        </el-table-column>
        <el-table-column prop="description" label="保养内容" min-width="240" show-overflow-tooltip />
        <el-table-column label="下次保养" width="220">
          <template #default="{ row }">
            <div v-if="row.next_maintenance_date || row.next_maintenance_mileage" class="next-info">
              <div v-if="row.next_maintenance_date">📅 {{ formatDate(row.next_maintenance_date) }}</div>
              <div v-if="row.next_maintenance_mileage">🔧 {{ row.next_maintenance_mileage }} km</div>
            </div>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="经办人" width="100">
          <template #default="{ row }">{{ row.handledByUser?.name || row.handled_by || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openDialog(row)">编辑</el-button>
            <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.per_page"
        :total="pagination.total"
        :page-sizes="[10, 15, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadList"
        @current-change="loadList"
        style="margin-top: 16px; justify-content: flex-end"
      />
    </div>

    <!-- 新增/编辑对话框 -->
    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑保养记录' : '新增保养记录'" width="1500px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="车辆" prop="vehicle_id">
          <el-select v-model="form.vehicle_id" placeholder="选择车辆" filterable style="width: 100%">
            <el-option v-for="v in vehicles" :key="v.id" :label="`${v.plate_no} (${v.brand} ${v.model})`" :value="v.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="保养类型" prop="maintenance_type">
          <el-radio-group v-model="form.maintenance_type">
            <el-radio value="routine">常规保养</el-radio>
            <el-radio value="repair">维修</el-radio>
            <el-radio value="inspection">年检</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="保养日期" prop="maintenance_date">
          <el-date-picker v-model="form.maintenance_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="里程(km)">
          <el-input-number v-model="form.mileage" :min="0" :step="100" style="width: 200px" placeholder="选填" />
        </el-form-item>
        <el-form-item label="费用(元)">
          <el-input-number v-model="form.cost" :min="0" :precision="2" :step="100" style="width: 200px" />
        </el-form-item>
        <el-form-item label="保养内容" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="3" placeholder="例:更换机油机滤、检查刹车系统" />
        </el-form-item>
        <el-divider content-position="left">下次保养提醒（选填）</el-divider>
        <el-form-item label="下次日期">
          <el-date-picker v-model="form.next_maintenance_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="下次里程">
          <el-input-number v-model="form.next_maintenance_mileage" :min="0" :step="1000" style="width: 200px" />
        </el-form-item>
        <el-form-item label="经办人">
          <el-input v-model="form.handled_by_name" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { FormRules } from 'element-plus'
import {
  getMaintenanceList, createMaintenance, updateMaintenance, deleteMaintenance,
  getVehicleList, getVehicleStats
} from '@/api/modules'
import { unwrapList } from '@/utils/response'
import { VehicleOption, MaintenanceRecord } from './types'

const loading = ref(false)
const submitting = ref(false)
const list = ref<MaintenanceRecord[]>([])
const vehicles = ref<VehicleOption[]>([])
const stats = reactive({ monthMaintenanceCost: '0' })
const pagination = reactive({ page: 1, per_page: 15, total: 0 })
const searchForm = reactive({ vehicle_id: null as number | null, maintenance_type: '', keyword: '' })

const typeMap: Record<string, string> = { routine: '常规保养', repair: '维修', inspection: '年检' }

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const formRef = ref()
const form = reactive({
  vehicle_id: null as number | null,
  maintenance_type: 'routine' as 'routine' | 'repair' | 'inspection',
  maintenance_date: new Date().toISOString().slice(0, 10),
  mileage: null as number | null,
  cost: 0,
  description: '',
  next_maintenance_date: null as string | null,
  next_maintenance_mileage: null as number | null,
  handled_by_name: '',
})
const rules: FormRules = {
  vehicle_id: [{ required: true, message: '请选择车辆', trigger: 'change' }],
  maintenance_type: [{ required: true, message: '请选择保养类型', trigger: 'change' }],
  maintenance_date: [{ required: true, message: '请选择保养日期', trigger: 'change' }],
  description: [{ required: true, message: '请输入保养内容', trigger: 'blur' }],
}

function formatMoney(v: number | string) {
  const n = parseFloat(String(v))
  return isNaN(n) ? '0.00' : n.toFixed(2)
}

function formatDate(d: string) {
  if (!d) return '-'
  return String(d).slice(0, 10)
}

function typeTagType(t: string) {
  if (t === 'routine') return 'success'
  if (t === 'repair') return 'warning'
  return 'info'
}

async function loadList() {
  loading.value = true
  try {
    const res = await getMaintenanceList({
      page: pagination.page,
      per_page: pagination.per_page,
      vehicle_id: searchForm.vehicle_id || undefined,
      maintenance_type: searchForm.maintenance_type || undefined,
      keyword: searchForm.keyword || undefined,
    })
    // V0.6.3: res = {code, data: paginator}
    const d = res?.data ?? {}
    list.value = Array.isArray(d?.data) ? d.data : []
    pagination.total = d?.total || 0
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

async function loadVehicles() {
  try {
    // V0.6.3: res = {code, data: <vehicles>}
    const res = await getVehicleList()
    vehicles.value = unwrapList(res)
  } catch (e) { /* 静默 */ }
}

async function loadStats() {
  try {
    const res = await getVehicleStats()
    if (res) {
      stats.monthMaintenanceCost = res.monthMaintenanceCost || '0'
    }
  } catch (e) { /* 静默 */ }
}

function handleSearch() { pagination.page = 1; loadList() }
function resetSearch() {
  searchForm.vehicle_id = null
  searchForm.maintenance_type = ''
  searchForm.keyword = ''
  pagination.page = 1
  loadList()
}

function openDialog(row?: MaintenanceRecord) {
  editingId.value = null
  form.vehicle_id = null
  form.maintenance_type = 'routine'
  form.maintenance_date = new Date().toISOString().slice(0, 10)
  form.mileage = null
  form.cost = 0
  form.description = ''
  form.next_maintenance_date = null
  form.next_maintenance_mileage = null
  form.handled_by_name = ''
  if (row) {
    editingId.value = row.id ?? null
    form.vehicle_id = row.vehicle_id || null
    form.maintenance_type = row.maintenance_type ?? 'routine'
    form.maintenance_date = row.maintenance_date || new Date().toISOString().slice(0, 10)
    form.mileage = row.mileage || null
    form.cost = Number(row.cost) || 0
    form.description = row.description || ''
    form.next_maintenance_date = row.next_maintenance_date || null
    form.next_maintenance_mileage = row.next_maintenance_mileage || null
    form.handled_by_name = row.handledByUser?.name || ''
  }
  dialogVisible.value = true
}

async function handleSubmit() {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch { return }
  submitting.value = true
  const payload: Record<string, unknown> = {
    vehicle_id: form.vehicle_id,
    maintenance_type: form.maintenance_type,
    maintenance_date: form.maintenance_date,
    mileage: form.mileage || null,
    cost: form.cost || 0,
    description: form.description,
    next_maintenance_date: form.next_maintenance_date || null,
    next_maintenance_mileage: form.next_maintenance_mileage || null,
  }
  try {
    if (editingId.value) {
      await updateMaintenance(editingId.value, payload)
      ElMessage.success('已更新')
    } else {
      await createMaintenance(payload)
      ElMessage.success('保养记录已添加')
    }
    dialogVisible.value = false
    loadList()
    loadStats()
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message || '保存失败')
  } finally {
    submitting.value = false
  }
}

async function handleDelete(row: MaintenanceRecord) {
  if (!row.id) return
  try {
    await ElMessageBox.confirm(
      `确认删除 ${row.vehicle?.plate_no || ''} ${row.maintenance_date || ''} 的保养记录?`,
      '删除确认',
      { type: 'warning' }
    )
    await deleteMaintenance(row.id)
    ElMessage.success('已删除')
    loadList()
    loadStats()
  } catch (e: unknown) {
    if (e !== 'cancel') ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message || '删除失败')
  }
}

onMounted(() => {
  loadVehicles()
  loadList()
  loadStats()
})
</script>

<style scoped>
.page-header { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.header-stats { display: flex; gap: 8px; }
.plate { font-weight: 600; color: #0c447c; margin-right: 8px; }
.vehicle-meta { color: #909399; font-size: 12px; }
.next-info { display: flex; flex-direction: column; gap: 2px; font-size: 12px; color: #67c23a; }
.muted { color: #c0c4cc; }
.filter-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
</style>
