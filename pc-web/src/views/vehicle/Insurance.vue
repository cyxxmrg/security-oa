<template>
  <div class="page-container">
    <div class="page-header">
      <h2>保险记录</h2>
      <div class="header-stats">
        <el-tag type="success" effect="plain">在保 {{ stats.activeInsurance }} 条</el-tag>
        <el-tag type="warning" effect="plain">30天内到期 {{ stats.expiringSoon }} 条</el-tag>
        <el-tag type="info" effect="plain">已过期 {{ stats.expiredInsurance }} 条</el-tag>
      </div>
    </div>

    <div class="filter-bar">
      <el-select v-model="searchForm.vehicle_id" placeholder="选择车辆" clearable filterable style="width: 200px">
        <el-option v-for="v in vehicles" :key="v.id" :label="`${v.plate_no} (${v.brand})`" :value="v.id" />
      </el-select>
      <el-select v-model="searchForm.status" placeholder="保险状态" clearable style="width: 140px">
        <el-option label="在保" value="active" />
        <el-option label="已过期" value="expired" />
      </el-select>
      <el-input v-model="searchForm.keyword" placeholder="搜索保单号/保险公司" clearable style="width: 240px" @keyup.enter="handleSearch" />
      <el-button type="primary" @click="handleSearch">搜索</el-button>
      <el-button @click="resetSearch">重置</el-button>
      <el-button type="primary" plain @click="openDialog()">新增保险</el-button>
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
        <el-table-column label="险种" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.type === 'compulsory' ? 'danger' : 'primary'" effect="plain">
              {{ typeMap[row.type] || row.type }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="insurance_company" label="保险公司" width="160" />
        <el-table-column prop="policy_no" label="保单号" width="180" />
        <el-table-column prop="premium" label="保费(元)" width="110" align="right">
          <template #default="{ row }">¥ {{ formatMoney(row.premium) }}</template>
        </el-table-column>
        <el-table-column label="保险期限" width="220">
          <template #default="{ row }">
            <div class="date-range">
              <span>{{ formatDate(row.start_date) }}</span>
              <el-icon><Right /></el-icon>
              <span :class="{ 'date-expire': isExpiringSoon(row.end_date) }">{{ formatDate(row.end_date) }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTag(row)" effect="dark">{{ statusLabel(row) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="notes" label="备注" min-width="120" show-overflow-tooltip />
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
    <el-dialog v-model="dialogVisible" :title="editingId ? '编辑保险记录' : '新增保险记录'" width="1500px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="车辆" prop="vehicle_id">
          <el-select v-model="form.vehicle_id" placeholder="选择车辆" filterable style="width: 100%">
            <el-option v-for="v in vehicles" :key="v.id" :label="`${v.plate_no} (${v.brand} ${v.model})`" :value="v.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="保险公司" prop="insurance_company">
          <el-input v-model="form.insurance_company" placeholder="例如 平安保险" />
        </el-form-item>
        <el-form-item label="保单号" prop="policy_no">
          <el-input v-model="form.policy_no" placeholder="请输入保单号" />
        </el-form-item>
        <el-form-item label="险种" prop="type">
          <el-radio-group v-model="form.type">
            <el-radio value="compulsory">交强险</el-radio>
            <el-radio value="commercial">商业险</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="保费(元)" prop="premium">
          <el-input-number v-model="form.premium" :min="0" :precision="2" :step="100" style="width: 200px" />
        </el-form-item>
        <el-form-item label="保险期限" prop="dateRange">
          <el-date-picker
            v-model="form.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="起始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.notes" type="textarea" :rows="2" placeholder="选填" />
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
import { Right } from '@element-plus/icons-vue'
import {
  getInsuranceList, createInsurance, updateInsurance, deleteInsurance,
  getVehicleList, getVehicleStats
} from '@/api/modules'
import { unwrapList } from '@/utils/response'
import { VehicleOption, InsuranceRecord } from './types'

const loading = ref(false)
const submitting = ref(false)
const list = ref<InsuranceRecord[]>([])
const vehicles = ref<VehicleOption[]>([])
const stats = reactive({ activeInsurance: 0, expiringSoon: 0, expiredInsurance: 0 })
const pagination = reactive({ page: 1, per_page: 15, total: 0 })
const searchForm = reactive({ vehicle_id: null as number | null, status: '', keyword: '' })

const typeMap: Record<string, string> = { compulsory: '交强险', commercial: '商业险' }

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const formRef = ref()
const form = reactive({
  vehicle_id: null as number | null,
  insurance_company: '',
  policy_no: '',
  type: 'commercial' as 'compulsory' | 'commercial',
  premium: 0,
  dateRange: [] as string[],
  notes: '',
})
const rules: FormRules = {
  vehicle_id: [{ required: true, message: '请选择车辆', trigger: 'change' }],
  insurance_company: [{ required: true, message: '请输入保险公司', trigger: 'blur' }],
  policy_no: [{ required: true, message: '请输入保单号', trigger: 'blur' }],
  type: [{ required: true, message: '请选择险种', trigger: 'change' }],
  premium: [{ required: true, message: '请输入保费', trigger: 'blur' }],
  dateRange: [{ required: true, message: '请选择保险期限', trigger: 'change' }],
}

function formatMoney(v: number | string) {
  const n = parseFloat(String(v))
  return isNaN(n) ? '0.00' : n.toFixed(2)
}

function isExpiringSoon(endDate: string) {
  if (!endDate) return false
  const end = new Date(endDate).getTime()
  const now = Date.now()
  return end >= now && end - now < 30 * 24 * 60 * 60 * 1000
}

function formatDate(d: string) {
  if (!d) return '-'
  return String(d).slice(0, 10)
}

function statusTag(row: InsuranceRecord) {
  if (row.status === 'expired') return 'info'
  if (isExpiringSoon(row.end_date ?? '')) return 'warning'
  return 'success'
}

function statusLabel(row: InsuranceRecord) {
  if (row.status === 'expired') return '已过期'
  if (isExpiringSoon(row.end_date ?? '')) return '即将到期'
  return '在保'
}

async function loadList() {
  loading.value = true
  try {
    const res = await getInsuranceList({
      page: pagination.page,
      per_page: pagination.per_page,
      vehicle_id: searchForm.vehicle_id || undefined,
      status: searchForm.status || undefined,
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
      stats.activeInsurance = res.activeInsurance || 0
      stats.expiringSoon = res.expiringSoon || 0
      stats.expiredInsurance = res.expiredInsurance || 0
    }
  } catch (e) { /* 静默 */ }
}

function handleSearch() { pagination.page = 1; loadList() }
function resetSearch() {
  searchForm.vehicle_id = null
  searchForm.status = ''
  searchForm.keyword = ''
  pagination.page = 1
  loadList()
}

function openDialog(row?: InsuranceRecord) {
  editingId.value = null
  form.vehicle_id = null
  form.insurance_company = ''
  form.policy_no = ''
  form.type = 'commercial'
  form.premium = 0
  form.dateRange = []
  form.notes = ''
  if (row) {
    editingId.value = row.id ?? null
    form.vehicle_id = row.vehicle_id || null
    form.insurance_company = row.insurance_company || ''
    form.policy_no = row.policy_no || ''
    form.type = row.type ?? 'commercial'
    form.premium = Number(row.premium) || 0
    form.dateRange = [row.start_date ?? '', row.end_date ?? '']
    form.notes = row.notes || ''
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
    insurance_company: form.insurance_company,
    policy_no: form.policy_no,
    type: form.type,
    premium: form.premium,
    start_date: form.dateRange[0],
    end_date: form.dateRange[1],
    notes: form.notes || null,
  }
  try {
    if (editingId.value) {
      await updateInsurance(editingId.value, payload)
      ElMessage.success('已更新')
    } else {
      await createInsurance(payload)
      ElMessage.success('保险记录已添加')
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

async function handleDelete(row: InsuranceRecord) {
  if (!row.id) return
  try {
    await ElMessageBox.confirm(`确认删除 ${row.insurance_company || ''} 的保单 ${row.policy_no || ''} ?`, '删除确认', { type: 'warning' })
    await deleteInsurance(row.id)
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
.date-range { display: flex; align-items: center; gap: 6px; color: #606266; }
.date-expire { color: #e6a23c; font-weight: 600; }
.filter-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
</style>
