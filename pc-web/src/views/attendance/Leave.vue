<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">请假管理</span>
      <el-button type="primary" :icon="Plus" @click="handleAdd">新增请假</el-button>
    </div>

    <div class="filter-bar">
      <el-select v-model="filters.leaveType" placeholder="请假类型" clearable style="width: 140px" @change="loadList">
        <el-option label="年假" value="annual" />
        <el-option label="事假" value="personal" />
        <el-option label="病假" value="sick" />
        <el-option label="婚假" value="marriage" />
        <el-option label="产假" value="maternity" />
        <el-option label="丧假" value="funeral" />
      </el-select>
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
        <el-table-column label="请假类型" min-width="100">
          <template #default="{ row }">
            <el-tag :type="leaveTypeTag(row.type)" size="small">{{ leaveTypeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="start_date" label="开始日期" min-width="110" />
        <el-table-column prop="end_date" label="结束日期" min-width="110" />
        <el-table-column prop="days" label="天数" width="80" />
        <el-table-column prop="reason" label="请假事由" min-width="160" show-overflow-tooltip />
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

    <el-dialog v-model="dialogVisible" title="新增请假申请" width="1500px" destroy-on-close>
      <el-form :model="leaveForm" :rules="formRules" ref="formRef" label-width="90px">
        <el-form-item label="请假类型" prop="type">
          <el-select v-model="leaveForm.type" placeholder="请选择" style="width: 100%">
            <el-option label="年假" value="annual" />
            <el-option label="事假" value="personal" />
            <el-option label="病假" value="sick" />
            <el-option label="婚假" value="marriage" />
            <el-option label="产假" value="maternity" />
            <el-option label="丧假" value="funeral" />
          </el-select>
        </el-form-item>
        <el-form-item label="起止日期" prop="dateRange">
          <el-date-picker v-model="leaveForm.dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" style="width: 100%" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="请假事由" prop="reason">
          <el-input v-model="leaveForm.reason" type="textarea" :rows="3" placeholder="请输入请假事由" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">提交申请</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showDetailDialog" title="请假详情" width="1500px">
      <el-descriptions :column="2" border v-if="detailRow">
        <el-descriptions-item label="申请人">{{ detailRow.user?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="请假类型">
          <el-tag :type="leaveTypeTag(detailRow.type)" size="small">{{ leaveTypeLabel(detailRow.type) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="天数">{{ detailRow.days }} 天</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusTag(detailRow.status)" size="small">{{ statusLabel(detailRow.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="开始日期">{{ detailRow.start_date }}</el-descriptions-item>
        <el-descriptions-item label="结束日期">{{ detailRow.end_date }}</el-descriptions-item>
        <el-descriptions-item label="审批人">{{ detailRow.approver?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="审批时间">{{ detailRow.approved_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="请假事由" :span="2">{{ detailRow.reason }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { get, post, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import type { LeaveRecord, ErrorWithMessage } from './types'

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const formRef = ref<FormInstance | null>(null)

const filters = reactive({ leaveType: '', status: '' })
const list = ref<LeaveRecord[]>([])
const currentUserId = ref<number | null>(null)

const leaveTypeMap: Record<string, { label: string; type: 'success' | 'warning' | 'danger' | 'info' | '' }> = {
  annual: { label: '年假', type: '' },
  personal: { label: '事假', type: 'warning' },
  sick: { label: '病假', type: 'danger' },
  marriage: { label: '婚假', type: 'success' },
  maternity: { label: '产假', type: 'success' },
  funeral: { label: '丧假', type: 'info' },
  compassionate: { label: '丧假', type: 'info' },
  other: { label: '其他', type: 'info' },
}
const leaveTypeLabel = (s: string) => leaveTypeMap[s]?.label || s
const leaveTypeTag = (s: string): 'success' | 'warning' | 'danger' | 'info' | '' => leaveTypeMap[s]?.type || 'info'

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
    const res = await get('/attendance/leave', { type: filters.leaveType, status: filters.status })
    // V0.6.3: request.ts 不解包, 兼容 Laravel paginator {data:[], total}
    list.value = unwrapList(res)
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithMessage)?.message || '加载失败')
  } finally { loading.value = false }
}

const loadCurrentUser = async () => {
  try {
    const r = await get('/auth/me')
    // V0.6.3: request.ts 不解包
    const me = r?.data ?? r
    currentUserId.value = me?.id || me?.user?.id || null
  } catch (e) {}
}

onMounted(() => { loadList(); loadCurrentUser() })

const handleReset = () => { filters.leaveType = ''; filters.status = ''; loadList() }

const leaveForm = reactive({ type: '', dateRange: null as [string, string] | null, reason: '' })
const formRules: FormRules = {
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
  dateRange: [{ required: true, message: '请选择起止日期', trigger: 'change' }],
  reason: [{ required: true, message: '请输入事由', trigger: 'blur' }],
}

const handleAdd = () => {
  leaveForm.type = ''
  leaveForm.dateRange = null
  leaveForm.reason = ''
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  if (!leaveForm.dateRange) return
  submitting.value = true
  try {
    const [start, end] = leaveForm.dateRange
    const days = Math.max(1, Math.round((new Date(end).getTime() - new Date(start).getTime()) / 86400000) + 1)
    await post('/attendance/leave', { type: leaveForm.type, start_date: start, end_date: end, days, reason: leaveForm.reason })
    ElMessage.success('请假申请已提交，请等待审批')
    dialogVisible.value = false
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithMessage)?.message || '提交失败')
  } finally { submitting.value = false }
}

const showDetailDialog = ref(false)
const detailRow = ref<LeaveRecord | null>(null)
const handleView = (row: LeaveRecord) => { detailRow.value = row; showDetailDialog.value = true }

const handleRevoke = async (row: LeaveRecord) => {
  try { await ElMessageBox.confirm('确认撤销该请假申请？', '撤销确认', { type: 'warning' }) } catch { return }
  try {
    await del(`/attendance/leave/${row.id}`)
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
</style>
