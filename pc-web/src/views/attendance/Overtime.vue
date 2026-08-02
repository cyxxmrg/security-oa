<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">加班管理</span>
      <el-button type="primary" :icon="Plus" @click="handleAdd">加班申请</el-button>
    </div>

    <div class="filter-bar">
      <el-date-picker v-model="filters.dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" style="width: 260px" value-format="YYYY-MM-DD" @change="loadList" />
      <el-select v-model="filters.status" placeholder="审批状态" clearable style="width: 140px" @change="loadList">
        <el-option label="待审批" value="pending" />
        <el-option label="已通过" value="approved" />
        <el-option label="已拒绝" value="rejected" />
      </el-select>
      <el-button type="primary" @click="loadList">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
    </div>

    <div class="content-card">
      <el-table :data="list" stripe style="width: 100%" v-loading="loading">
        <el-table-column type="index" label="序号" width="60" />
        <el-table-column label="申请人" min-width="100">
          <template #default="{ row }">{{ row.user?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="加班日期" min-width="110">
          <template #default="{ row }">{{ row.overtime_date }}</template>
        </el-table-column>
        <el-table-column label="开始时间" min-width="100">
          <template #default="{ row }">{{ row.start_time }}</template>
        </el-table-column>
        <el-table-column label="结束时间" min-width="100">
          <template #default="{ row }">{{ row.end_time }}</template>
        </el-table-column>
        <el-table-column label="时长(小时)" min-width="100">
          <template #default="{ row }">
            <span :class="{ 'overtime-hours--long': Number(row.hours) > 4 }">{{ row.hours }}h</span>
          </template>
        </el-table-column>
        <el-table-column label="补偿方式" min-width="100">
          <template #default="{ row }">
            <el-tag :type="compTypeTag(row.compensation_type)" size="small">{{ compTypeLabel(row.compensation_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="reason" label="加班原因" min-width="180" show-overflow-tooltip />
        <el-table-column label="审批状态" min-width="100">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleView(row)">查看</el-button>
            <el-button v-if="row.status === 'pending' && row.user_id === currentUserId" type="info" link size="small" @click="handleRevoke(row)">撤销</el-button>
            <el-tag v-if="row.status === 'pending' && row.user_id !== currentUserId" size="small" type="info" effect="plain" class="ml-8">去审批中心处理</el-tag>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="dialogVisible" title="加班申请" width="1500px" destroy-on-close>
      <el-form :model="overtimeForm" :rules="formRules" ref="formRef" label-width="90px">
        <el-form-item label="加班日期" prop="overtime_date">
          <el-date-picker v-model="overtimeForm.overtime_date" type="date" placeholder="选择日期" style="width: 100%" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="起止时间" prop="timeRange">
          <el-time-picker v-model="overtimeForm.timeRange" is-range range-separator="至" start-placeholder="开始时间" end-placeholder="结束时间" style="width: 100%" format="HH:mm" value-format="HH:mm" />
        </el-form-item>
        <el-form-item label="加班时长" prop="hours">
          <el-input-number v-model="overtimeForm.hours" :min="0.5" :max="12" :step="0.5" style="width: 200px" />
          <span class="form-tip">单位：小时（手动调整或根据起止时间自动计算）</span>
        </el-form-item>
        <el-form-item label="补偿方式">
          <el-radio-group v-model="overtimeForm.compensation_type">
            <el-radio value="overtime_pay">加班费</el-radio>
            <el-radio value="time_off">调休</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="加班原因" prop="reason">
          <el-input v-model="overtimeForm.reason" type="textarea" :rows="3" placeholder="请详细说明加班原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">提交申请</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showDetailDialog" title="加班详情" width="1500px">
      <el-descriptions :column="2" border v-if="detailRow">
        <el-descriptions-item label="申请人">{{ detailRow.user?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="加班日期">{{ detailRow.overtime_date }}</el-descriptions-item>
        <el-descriptions-item label="开始时间">{{ detailRow.start_time }}</el-descriptions-item>
        <el-descriptions-item label="结束时间">{{ detailRow.end_time }}</el-descriptions-item>
        <el-descriptions-item label="时长">{{ detailRow.hours }} 小时</el-descriptions-item>
        <el-descriptions-item label="补偿方式">
          <el-tag :type="compTypeTag(detailRow.compensation_type)" size="small">{{ compTypeLabel(detailRow.compensation_type) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="审批状态">
          <el-tag :type="statusTag(detailRow.status)" size="small">{{ statusLabel(detailRow.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="审批人">{{ detailRow.approver?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="审批时间" :span="2">{{ detailRow.approved_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="加班原因" :span="2">{{ detailRow.reason }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { get, post, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import type { OvertimeRecord, ErrorWithMessage } from './types'

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const formRef = ref<FormInstance | null>(null)

const filters = reactive({
  dateRange: null as [string, string] | null,
  status: ''
})
const list = ref<OvertimeRecord[]>([])
const currentUserId = ref<number | null>(null)

const compTypeMap: Record<string, { label: string; type: 'success' | 'warning' | 'info' }> = {
  pay: { label: '加班费', type: 'warning' },
  default_pay: { label: '加班费(默认)', type: 'warning' },
  leave: { label: '调休', type: 'success' },
  overtime_pay: { label: '加班费', type: 'warning' },
  time_off: { label: '调休', type: 'success' },
}
const compTypeLabel = (s: string) => compTypeMap[s]?.label || s || '-'
const compTypeTag = (s: string): 'success' | 'warning' | 'info' => compTypeMap[s]?.type || 'info'

const statusMap: Record<string, { label: string; type: 'warning' | 'success' | 'danger' | 'info' }> = {
  pending: { label: '待审批', type: 'warning' },
  approved: { label: '已通过', type: 'success' },
  rejected: { label: '已拒绝', type: 'danger' },
}
const statusLabel = (s: string) => statusMap[s]?.label || s
const statusTag = (s: string): 'warning' | 'success' | 'danger' | 'info' => statusMap[s]?.type || 'info'

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { status: filters.status }
    if (filters.dateRange) {
      params.start_date = filters.dateRange[0]
      params.end_date = filters.dateRange[1]
    }
    const res = await get('/attendance/overtime', params)
    // V0.6.3: res = {code, data: paginator}
    list.value = unwrapList(res)
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithMessage)?.message || '加载失败')
  } finally { loading.value = false }
}

const loadCurrentUser = async () => {
  try {
    const r = await get('/auth/me')
    // V0.6.3: res = {code, data: <me>}
    const d = r?.data ?? r
    currentUserId.value = d?.id || null
  } catch (e) {}
}

onMounted(() => { loadList(); loadCurrentUser() })

const handleReset = () => { filters.dateRange = null; filters.status = ''; loadList() }

const overtimeForm = reactive({
  overtime_date: '',
  timeRange: null as [string, string] | null,
  hours: 1,
  compensation_type: 'overtime_pay' as 'overtime_pay' | 'time_off',
  reason: ''
})

const formRules: FormRules = {
  overtime_date: [{ required: true, message: '请选择日期', trigger: 'change' }],
  timeRange: [{ required: true, message: '请选择起止时间', trigger: 'change' }],
  hours: [{ required: true, message: '请填写加班时长', trigger: 'blur' }],
  reason: [{ required: true, message: '请输入加班原因', trigger: 'blur' }],
}

const handleAdd = () => {
  overtimeForm.overtime_date = ''
  overtimeForm.timeRange = null
  overtimeForm.hours = 1
  overtimeForm.compensation_type = 'overtime_pay'
  overtimeForm.reason = ''
  dialogVisible.value = true
}

// 监听起止时间自动计算时长
watch(() => overtimeForm.timeRange, (val) => {
  if (val && Array.isArray(val) && val[0] && val[1]) {
    const [start, end] = val
    const [sh, sm] = start.split(':').map(Number)
    const [eh, em] = end.split(':').map(Number)
    const minutes = (eh * 60 + em) - (sh * 60 + sm)
    if (minutes > 0) {
      overtimeForm.hours = Math.round((minutes / 60) * 10) / 10
    }
  }
})

const handleSubmit = async () => {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  if (!overtimeForm.timeRange) return
  submitting.value = true
  try {
    const [start_time, end_time] = overtimeForm.timeRange
    await post('/attendance/overtime', {
      overtime_date: overtimeForm.overtime_date,
      start_time,
      end_time,
      hours: overtimeForm.hours,
      reason: overtimeForm.reason,
      compensation_type: overtimeForm.compensation_type,
    })
    ElMessage.success('加班申请已提交，请等待审批')
    dialogVisible.value = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithMessage)?.message || '提交失败')
  } finally { submitting.value = false }
}

const showDetailDialog = ref(false)
const detailRow = ref<OvertimeRecord | null>(null)
const handleView = (row: OvertimeRecord) => { detailRow.value = row; showDetailDialog.value = true }

// V1.2.7h: 加班审批统一进审批中心, 此处不再处理
const handleRevoke = async (row: OvertimeRecord) => {
  try { await ElMessageBox.confirm('确认撤销该加班申请？', '撤销确认', { type: 'warning' }) } catch { return }
  try {
    await del(`/attendance/overtime/${row.id}`)
    ElMessage.success('已撤销')
    loadList()
  } catch (e: unknown) { ElMessage.error((e as ErrorWithMessage)?.message || '撤销失败') }
}
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; .page-title { font-size: 20px; font-weight: 600; color: #303133; } }
.filter-bar { display: flex; gap: 12px; flex-wrap: wrap; padding: 16px; background: #fff; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); align-items: center; }
.content-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); }
.overtime-hours--long { color: #A32D2D; font-weight: 600; }
.form-tip { color: #909399; font-size: 12px; margin-left: 8px; }
</style>
