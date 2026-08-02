<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">质保期管理</span>
      <div class="header-actions">
        <ScopeToggle @change="loadList" />
        <el-button :icon="Refresh" plain @click="handleReset">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建质保期</el-button>
      </div>
    </div>

    <!-- KPI 卡片 -->
    <div class="kpi-row">
      <el-card v-for="kpi in kpis" :key="kpi.label" shadow="hover" :body-style="{ padding: '14px 18px' }" class="kpi-card">
        <div class="kpi-label">{{ kpi.label }}</div>
        <div class="kpi-value" :style="{ color: kpi.color }">{{ kpi.value }}</div>
      </el-card>
    </div>

    <!-- 筛选区 -->
    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 140px">
            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="searchForm.warranty_type" placeholder="全部类型" clearable style="width: 140px">
            <el-option v-for="t in typeOptions" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="项目">
          <el-input v-model="searchForm.project_id" placeholder="项目ID" clearable style="width: 120px" />
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="质保编号/名称" clearable style="width: 220px" />
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
        <el-table-column prop="id" label="编号" width="60" align="center" />
        <el-table-column prop="warranty_no" label="质保编号" min-width="160" fixed show-overflow-tooltip>
          <template #default="{ row }">
            <el-link type="primary" :underline="false" @click="goDetail(row)">{{ row.warranty_no }}</el-link>
          </template>
        </el-table-column>
        <el-table-column label="质保名称" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.warranty_name || row.terms || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="项目" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.project?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="客户" min-width="120" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.customer?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ typeLabel(row.warranty_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="起止日期" width="200" align="center">
          <template #default="{ row }">
            <div style="font-size: 12px">{{ row.start_date }} ~ {{ row.end_date }}</div>
          </template>
        </el-table-column>
        <el-table-column label="剩余天数" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="daysLeftTag(row).type" effect="plain" size="small">
              {{ daysLeftText(row) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="plain" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right" align="center">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="goDetail(row)">详情</el-button>
            <el-button v-if="row.status === 'active'" link type="success" :icon="Refresh" @click="handleRenew(row)">续期</el-button>
            <el-button v-if="['active', 'expiring'].includes(row.status)" link type="danger" :icon="CircleClose" @click="handleTerminate(row)">终止</el-button>
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

    <!-- 续期 / 终止弹窗 -->
    <el-dialog v-model="actionDialog.visible" :title="actionDialog.title" width="500px" :close-on-click-modal="false">
      <el-form :model="actionDialog.form" label-width="100px">
        <el-form-item v-if="actionDialog.type === 'renew'" label="续期月数">
          <el-input-number v-model="actionDialog.form.renew_months" :min="1" :max="600" />
        </el-form-item>
        <el-form-item label="原因/备注" required>
          <el-input v-model="actionDialog.form.reason" type="textarea" :rows="3" placeholder="请输入原因/备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="actionDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="submitAction">确定</el-button>
      </template>
    </el-dialog>

    <!-- 新建质保期弹窗 -->
    <el-dialog v-model="createDialog.visible" title="新建质保期" width="700px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="120px" v-loading="createLoading">
        <el-form-item label="项目" prop="project_id">
          <el-select v-model="createForm.project_id" placeholder="请选择项目" filterable style="width:100%" @change="onProjectChange">
            <el-option v-for="p in projectOptions" :key="p.id" :label="`${p.name} (${p.code || '#'+p.id})`" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="客户" prop="customer_id">
          <el-select v-model="createForm.customer_id" placeholder="选择项目后自动填充" disabled style="width:100%">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="设备" prop="device_id">
          <el-input-number v-model="createForm.device_id" :min="1" style="width:100%" />
        </el-form-item>
        <el-form-item label="质保类型" prop="warranty_type">
          <el-select v-model="createForm.warranty_type" placeholder="请选择" style="width:100%">
            <el-option label="基础质保" value="basic" />
            <el-option label="延保" value="extended" />
          </el-select>
        </el-form-item>
        <el-form-item label="开始日期" prop="start_date">
          <el-date-picker v-model="createForm.start_date" type="date" value-format="YYYY-MM-DD" style="width:100%" />
        </el-form-item>
        <el-form-item label="质保月数" prop="period_months">
          <el-input-number v-model="createForm.period_months" :min="1" :max="600" />
        </el-form-item>
        <el-form-item label="质保金额">
          <el-input-number v-model="createForm.deposit_amount" :min="0" :step="1000" :precision="2" style="width:100%" placeholder="自动同步到质保金" />
          <div style="font-size:12px;color:#94a3b8;margin-top:4px">填写后自动创建质保金记录</div>
        </el-form-item>
        <el-form-item label="覆盖范围">
          <el-input v-model="createForm.coverage_scope" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="条款说明">
          <el-input v-model="createForm.terms" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="createForm.remarks" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="createLoading" @click="handleCreateSubmit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { Plus, Search, Refresh, View, Delete, CircleClose, Money } from '@element-plus/icons-vue'
import { warrantyApi, warrantyDepositApi } from '@/api/warranty'
import { get } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import ScopeToggle from '@/components/ScopeToggle.vue'
import type { Warranty, TagType, ActionDialogState } from './types'

const router = useRouter()

const statusOptions = [
  { value: 'active',     label: '在保' },
  { value: 'expiring',   label: '即将到期' },
  { value: 'expired',    label: '已过期' },
  { value: 'renewed',    label: '已续约' },
  { value: 'terminated', label: '已终止' },
]

const typeOptions = [
  { value: 'construction', label: '施工质保' },
  { value: 'equipment',    label: '设备质保' },
  { value: 'product',      label: '产品质保' },
  { value: 'service',      label: '服务质保' },
]

const statusLabel = (s: string) => statusOptions.find(x => x.value === s)?.label || s || '-'
const typeLabel   = (t: string) => typeOptions.find(x => x.value === t)?.label || t || '-'
const statusTagType = (s: string): TagType => {
  if (s === 'active')     return 'success'
  if (s === 'expiring')   return 'warning'
  if (s === 'expired')    return 'danger'
  if (s === 'terminated') return 'info'
  return 'info'
}

const daysLeftText = (row: Warranty) => {
  if (row.status === 'expired' || row.status === 'terminated' || row.status === 'renewed') return '-'
  if (!row.end_date) return '-'
  const end = new Date(row.end_date).getTime()
  const now = Date.now()
  const days = Math.ceil((end - now) / (1000 * 60 * 60 * 24))
  if (days < 0) return `已过期 ${Math.abs(days)} 天`
  if (days === 0) return '今日到期'
  return `${days} 天`
}

const daysLeftTag = (row: Warranty): { type: TagType } => {
  const text = daysLeftText(row)
  if (text.includes('已过期')) return { type: 'danger' }
  if (text === '今日到期') return { type: 'warning' }
  if (text.includes('天')) {
    const n = parseInt(text)
    if (n <= 30) return { type: 'warning' }
    if (n <= 90) return { type: 'info' }
  }
  return { type: 'success' }
}

const loading = ref(false)
const list = ref<Warranty[]>([])
const page = ref(1)
const pageSize = ref(10)

const searchForm = reactive({
  status: '',
  warranty_type: '',
  project_id: '',
  keyword: '',
})

const kpis = computed(() => {
  const total    = list.value.length
  const active   = list.value.filter(x => x.status === 'active').length
  const expiring = list.value.filter(x => x.status === 'expiring').length
  const expired  = list.value.filter(x => x.status === 'expired').length
  return [
    { label: '质保总数', value: total,    color: '#409EFF' },
    { label: '在保',     value: active,   color: '#67C23A' },
    { label: '即将到期', value: expiring, color: '#E6A23C' },
    { label: '已过期',   value: expired,  color: '#F56C6C' },
  ]
})

const filteredList = computed(() => {
  return list.value.filter(x => {
    if (searchForm.status && x.status !== searchForm.status) return false
    if (searchForm.warranty_type && x.warranty_type !== searchForm.warranty_type) return false
    if (searchForm.project_id && String(x.project_id) !== String(searchForm.project_id)) return false
    if (searchForm.keyword) {
      const kw = searchForm.keyword.toLowerCase()
      if (!String(x.warranty_no || '').toLowerCase().includes(kw) &&
          !String(x.warranty_name || '').toLowerCase().includes(kw)) return false
    }
    return true
  })
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const actionDialog = reactive<ActionDialogState>({
  visible: false,
  title: '',
  type: '',
  target: null,
  form: { renew_months: 12, reason: '' },
})

// ========== 新建质保期弹窗 ==========
const createDialog = reactive({ visible: false })
const createLoading = ref(false)
const createFormRef = ref<FormInstance | null>(null)
const projectOptions = ref<{ id: number; name: string; code?: string; customer?: { id: number; name: string } | null }[]>([])
const customerOptions = ref<{ id: number; name: string }[]>([])
const createForm = reactive({
  project_id: null as number | null,
  customer_id: null as number | null,
  device_id: undefined as number | undefined,
  start_date: new Date().toISOString().slice(0, 10),
  warranty_type: 'basic',
  period_months: 12,
  deposit_amount: 0,
  end_date: '',
  coverage_scope: '',
  terms: '',
  remarks: '',
})
const createRules: FormRules = {
  project_id: [{ required: true, message: '请选择项目', trigger: 'change' }],
  customer_id: [{ required: true, message: '选择项目后自动填充', trigger: 'change' }],
  start_date: [{ required: true, message: '请选择开始日期', trigger: 'change' }],
  period_months: [{ required: true, message: '请填写月数', trigger: 'blur' }],
}
async function handleCreateSubmit() {
  try {
    await createFormRef.value?.validate()
  } catch { return }
  createLoading.value = true
  try {
    const res = await warrantyApi.create(createForm)
    ElMessage.success('质保期已创建')
    // 如果填了质保金额，自动创建质保金
    const amount = Number(createForm.deposit_amount || 0)
    if (amount > 0) {
      const warrantyData = (res as Record<string, unknown>)?.data || (res as Record<string, unknown>)
      const warrantyId = warrantyData?.id
      if (warrantyId) {
        try {
          await warrantyDepositApi.create({
            project_id: createForm.project_id,
            customer_id: createForm.customer_id,
            contract_amount: amount,
            deposit_amount: amount,
            hold_date: createForm.start_date || new Date().toISOString().slice(0, 10),
            warranty_id: warrantyId,
          })
          ElMessage.success('质保金已自动创建')
        } catch { /* 拦截器已提示 */ }
      }
    }
    createDialog.visible = false
    loadList()
  } catch { /* 拦截器已提示 */ }
  finally {
    createLoading.value = false
  }
}

// 加载项目列表（供新建质保期选择）
async function loadProjects() {
  try {
    const res = await get('/projects', { per_page: 200 })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    projectOptions.value = (d as { id: number; name: string; code?: string; customer?: { id: number; name: string } | null }[]) || []
  } catch { projectOptions.value = [] }
}
function onProjectChange(projectId: number) {
  const p = projectOptions.value.find(x => x.id === projectId)
  if (p?.customer) {
    createForm.customer_id = p.customer.id
    customerOptions.value = [p.customer]
  } else {
    createForm.customer_id = null
  }
}

async function loadList() {
  loading.value = true
  try {
    const res = await warrantyApi.list({ per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    list.value = unwrapList(res)
  } catch (e: unknown) {
    ElMessage.error('加载质保期失败: ' + ((e as { message?: string })?.message || 'unknown'))
    list.value = []
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  page.value = 1
}

function handleReset() {
  searchForm.status = ''
  searchForm.warranty_type = ''
  searchForm.project_id = ''
  searchForm.keyword = ''
  page.value = 1
  loadList()
}

function handleAdd() {
  createForm.project_id = null
  createForm.customer_id = null
  createForm.device_id = undefined as unknown as number
  createForm.start_date = new Date().toISOString().slice(0, 10)
  createForm.warranty_type = 'basic'
  createForm.period_months = 12
  createForm.deposit_amount = 0
  createForm.end_date = ''
  createForm.coverage_scope = ''
  createForm.terms = ''
  createForm.remarks = ''
  // 加载项目列表供选择
  loadProjects()
  createDialog.visible = true
}

function goDetail(row: Warranty) {
  router.push(`/project/warranty/detail/${row.id}`)
}

function handleRenew(row: Warranty) {
  actionDialog.type = 'renew'
  actionDialog.title = `续期 - ${row.warranty_no}`
  actionDialog.target = row
  actionDialog.form = { renew_months: 12, reason: '' }
  actionDialog.visible = true
}

function handleTerminate(row: Warranty) {
  actionDialog.type = 'terminate'
  actionDialog.title = `终止质保期 - ${row.warranty_no}`
  actionDialog.target = row
  actionDialog.form = { renew_months: 12, reason: '' }
  actionDialog.visible = true
}

async function submitAction() {
  if (!actionDialog.form.reason) {
    ElMessage.warning('请填写原因/备注')
    return
  }
  const target = actionDialog.target
  if (!target) return
  try {
    if (actionDialog.type === 'renew') {
      await warrantyApi.renew(target.id!, {
        renew_months: actionDialog.form.renew_months,
        remark: actionDialog.form.reason,
      })
      ElMessage.success('质保期已续期')
    } else {
      await warrantyApi.terminate(target.id!, {
        terminate_reason: actionDialog.form.reason,
        remark: actionDialog.form.reason,
      })
      ElMessage.success('质保期已终止')
    }
    actionDialog.visible = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error('操作失败: ' + ((e as { message?: string })?.message || 'unknown'))
  }
}

async function handleDelete(row: Warranty) {
  try {
    await ElMessageBox.confirm(`确认删除质保期 ${row.warranty_no}?`, '删除确认', {
      type: 'warning',
    })
    await warrantyApi.remove(row.id!)
    ElMessage.success('已删除')
    loadList()
  } catch (e: unknown) {
    if (e !== 'cancel') {
      ElMessage.error('删除失败: ' + ((e as { message?: string })?.message || 'unknown'))
    }
  }
}

onMounted(loadList)
</script>
