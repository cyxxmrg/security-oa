<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">打卡记录</span>
      <div class="header-actions">
        <el-button type="success" :icon="Clock" @click="handleClockIn" :disabled="!canClockIn">上班打卡</el-button>
        <el-button type="warning" :icon="Moon" @click="handleClockOut" :disabled="!canClockOut">下班打卡</el-button>
        <el-button type="info" :icon="Location" @click="handleFieldClock">外勤打卡</el-button>
        <el-button type="primary" plain :icon="EditPen" @click="handleSupplement">补卡申请</el-button>
        <el-button :icon="Download" @click="handleExport">导出记录</el-button>
      </div>
    </div>

    <!-- 今日打卡状态 -->
    <div class="today-card" v-if="todayRecord">
      <div class="today-row">
        <div class="today-item">
          <span class="today-label">今日日期</span>
          <span class="today-value">{{ formatDate(todayRecord.date) }}</span>
        </div>
        <div class="today-item">
          <span class="today-label">上班打卡</span>
          <span class="today-value" :class="{ 'is-empty': !todayRecord.clock_in }">
            {{ todayRecord.clock_in || '未打卡' }}
          </span>
        </div>
        <div class="today-item">
          <span class="today-label">下班打卡</span>
          <span class="today-value" :class="{ 'is-empty': !todayRecord.clock_out }">
            {{ todayRecord.clock_out || '未打卡' }}
          </span>
        </div>
        <div class="today-item">
          <span class="today-label">工时</span>
          <span class="today-value">{{ todayRecord.work_hours ? todayRecord.work_hours + 'h' : '-' }}</span>
        </div>
        <div class="today-item">
          <span class="today-label">状态</span>
          <el-tag :type="todayStatusTag(todayRecord.status)" size="small">{{ todayStatusLabel(todayRecord.status) }}</el-tag>
        </div>
      </div>
    </div>

    <div class="filter-bar">
      <el-date-picker v-model="filters.dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" style="width: 260px" value-format="YYYY-MM-DD" @change="loadList" />
      <el-select v-model="filters.status" placeholder="打卡状态" clearable style="width: 140px" @change="loadList">
        <el-option label="正常" value="normal" />
        <el-option label="迟到" value="late" />
        <el-option label="早退" value="early" />
        <el-option label="缺勤" value="absent" />
        <el-option label="外勤" value="field_work" />
      </el-select>
      <el-button type="primary" @click="loadList">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
    </div>

    <div class="content-card">
      <el-table :data="list" stripe style="width: 100%" v-loading="loading">
        <el-table-column type="index" label="序号" width="60" />
        <el-table-column label="员工" min-width="100">
          <template #default="{ row }">{{ row.user?.name || row.user?.username || '-' }}</template>
        </el-table-column>
        <el-table-column label="日期" min-width="110">
          <template #default="{ row }">{{ formatDate(row.date) }}</template>
        </el-table-column>
        <el-table-column label="上班打卡" min-width="100">
          <template #default="{ row }">{{ row.clock_in || '-' }}</template>
        </el-table-column>
        <el-table-column label="下班打卡" min-width="100">
          <template #default="{ row }">{{ row.clock_out || '-' }}</template>
        </el-table-column>
        <el-table-column label="工时" min-width="80">
          <template #default="{ row }">{{ row.work_hours ? row.work_hours + 'h' : '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" min-width="100">
          <template #default="{ row }">
            <el-tag :type="todayStatusTag(row.status)" size="small">{{ todayStatusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="打卡位置" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.clock_in_location">{{ row.clock_in_location }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="140" show-overflow-tooltip />
      </el-table>
    </div>

    <div class="pagination-wrapper">
      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.pageSize"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadList"
        @current-change="loadList"
      />
    </div>

    <!-- 打卡位置对话框 -->
    <ClockLocationDialog
      v-model:visible="showLocationDialog"
      :form="clockForm"
      :submitting="submitting"
      @confirm="confirmClock"
    />

    <!-- 外勤打卡对话框 -->
    <FieldClockDialog
      v-model:visible="showFieldDialog"
      :form="fieldForm"
      :submitting="fieldSubmitting"
      @confirm="confirmFieldClock"
    />

    <!-- 补卡对话框 -->
    <SupplementClockDialog
      v-model:visible="showSupplementDialog"
      :form="supplementForm"
      :submitting="supplementSubmitting"
      :today-max-date="todayMaxDate"
      @confirm="confirmSupplement"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { Clock, Moon, Download, EditPen, Location } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapTotal } from '@/utils/response'
import { exportExcelLike, printTable } from '@/utils/exporter'
import ClockLocationDialog from './components/record/ClockLocationDialog.vue'
import FieldClockDialog from './components/record/FieldClockDialog.vue'
import SupplementClockDialog from './components/record/SupplementClockDialog.vue'
import type { AttendanceRecord, ErrorWithMessage, ErrorWithResponse } from './types'

const route = useRoute()
const loading = ref(false)
const submitting = ref(false)
const showLocationDialog = ref(false)
const showSupplementDialog = ref(false)
const showFieldDialog = ref(false)
const fieldSubmitting = ref(false)
const supplementSubmitting = ref(false)
const supplementFormRef = ref<FormInstance | null>(null)
const todayRecord = ref<AttendanceRecord | null>(null)

const filters = reactive({
  dateRange: null as [string, string] | null,
  status: ''
})
const list = ref<AttendanceRecord[]>([])

const pagination = reactive({ page: 1, pageSize: 10, total: 0 })

const clockForm = reactive({
  type: 'in' as 'in' | 'out',
  location: '',
  remark: ''
})

const todayMaxDate = new Date().toISOString().slice(0, 10)
const supplementForm = reactive({
  date: todayMaxDate,
  type: 'in' as 'in' | 'out' | 'field_in' | 'field_out',
  time: '',
  location: '',
  reason: '',
})
const fieldForm = reactive({
  type: 'in' as 'in' | 'out',
  time: '',
  location: '',
  remark: '',
})
const supplementRules = {
  date:   [{ required: true, message: '请选择补卡日期', trigger: 'change' }],
  type:   [{ required: true, message: '请选择补卡类型', trigger: 'change' }],
  time:   [{ required: true, message: '请选择补卡时间', trigger: 'change' }],
  reason: [{ required: true, message: '请填写补卡原因', trigger: 'blur' }],
}

const todayStatusMap: Record<string, { label: string; type: 'success' | 'warning' | 'danger' | 'info' }> = {
  normal: { label: '正常', type: 'success' },
  late: { label: '迟到', type: 'warning' },
  early: { label: '早退', type: 'info' },
  absent: { label: '缺勤', type: 'danger' },
  field_work: { label: '外勤', type: 'info' },
}
const todayStatusLabel = (s: string) => todayStatusMap[s]?.label || s || '-'
const todayStatusTag = (s: string): 'success' | 'warning' | 'danger' | 'info' => todayStatusMap[s]?.type || 'info'

function formatDate(d: unknown): string {
  if (!d) return '-'
  // 处理 "2026-06-15" 或 "2026-06-15T16:00:00.000000Z" 两种格式
  const s = String(d)
  if (s.includes('T')) {
    return s.replace('T', ' ').slice(0, 16)  // → "2026-07-12 16:00"
  }
  return s.length >= 10 ? s.slice(0, 10) : s
}

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: pagination.page,
      per_page: pagination.pageSize,
      status: filters.status,
    }
    if (filters.dateRange) {
      params.start_date = filters.dateRange[0]
      params.end_date = filters.dateRange[1]
    }
    const res = await get('/attendance/records', params)
    // V0.6.3: request.ts 不解包, 兼容 Laravel paginator {data:[], total}
    list.value = unwrapList(res)
    pagination.total = unwrapTotal(res) || list.value.length
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithMessage)?.message || '加载失败')
  } finally { loading.value = false }
}

const loadOverview = async () => {
  try {
    // 优先用 /attendance/today 拿今日记录 (含已打卡的)
    const r = await get('/attendance/today')
    const d = r?.data || r
    if (d && d.id) {
      todayRecord.value = d
    } else {
      // 兜底用 /attendance/overview (无 user 上下文拿不到个人记录)
      todayRecord.value = null
    }
  } catch (e) {
    todayRecord.value = null
  }
}

onMounted(() => {
  const dateParam = route.query.date as string
  if (dateParam) {
    filters.dateRange = [dateParam, dateParam]
  }
  loadList()
  loadOverview()
})

const canClockIn = computed(() => !todayRecord.value?.clock_in)
const canClockOut = computed(() => !!todayRecord.value?.clock_in && !todayRecord.value?.clock_out)

const handleClockIn = () => {
  clockForm.type = 'in'
  clockForm.location = ''
  clockForm.remark = ''
  showLocationDialog.value = true
}

const handleClockOut = () => {
  clockForm.type = 'out'
  clockForm.location = ''
  clockForm.remark = ''
  showLocationDialog.value = true
}

const confirmClock = async () => {
  submitting.value = true
  try {
    const url = clockForm.type === 'in' ? '/attendance/clock-in' : '/attendance/clock-out'
    await post(url, { location: clockForm.location, remark: clockForm.remark })
    ElMessage.success(clockForm.type === 'in' ? '签到成功' : '签退成功')
    showLocationDialog.value = false
    loadOverview()
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithMessage)?.message || '打卡失败')
  } finally { submitting.value = false }
}

const handleReset = () => { filters.dateRange = null; filters.status = ''; pagination.page = 1; loadList() }

const handleExport = () => {
  const headers = ['员工', '日期', '上班时间', '下班时间', '工时(小时)', '状态', '打卡地点', '备注']
  const rows = list.value.map((r: AttendanceRecord) => [
    r.employee?.name || r.employee_name || '-',
    r.date || '-',
    r.clock_in || '-',
    r.clock_out || '-',
    Number(r.work_hours || 0).toFixed(1),
    ({ normal: '正常', late: '迟到', early: '早退', absent: '缺卡', leave: '请假', overtime: '加班' }[r.status as string] || r.status || '-'),
    r.location || '-',
    r.remark || '-',
  ])
  exportExcelLike(headers, rows, '打卡记录', { title: '员工打卡记录' })
}

const handleSupplement = () => {
  supplementForm.date = todayMaxDate
  supplementForm.type = 'in'
  supplementForm.time = ''
  supplementForm.location = ''
  supplementForm.reason = ''
  showSupplementDialog.value = true
  nextTick(() => supplementFormRef.value?.clearValidate())
}

const handleFieldClock = () => {
  // 智能默认: 还没签到就默认签到, 已签到未签退就默认签退
  fieldForm.type = !todayRecord.value?.clock_in ? 'in' : (!todayRecord.value?.clock_out ? 'out' : 'out')
  fieldForm.time = ''
  fieldForm.location = ''
  fieldForm.remark = ''
  showFieldDialog.value = true
}

const confirmFieldClock = async () => {
  fieldSubmitting.value = true
  try {
    const r = await post('/attendance/field-clock', {
      type: fieldForm.type,
      time: fieldForm.time || null,
      location: fieldForm.location || null,
      remark: fieldForm.remark || null,
    })
    ElMessage.success(r?.message || '外勤打卡成功')
    showFieldDialog.value = false
    loadOverview()
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithResponse)?.response?.data?.message || (e as ErrorWithMessage)?.message || '外勤打卡失败')
  } finally {
    fieldSubmitting.value = false
  }
}

const confirmSupplement = async () => {
  if (!supplementFormRef.value) return
  try { await supplementFormRef.value.validate() } catch { return }
  supplementSubmitting.value = true
  try {
    const r = await post('/attendance/supplement', {
      date: supplementForm.date,
      type: supplementForm.type,
      time: supplementForm.time,
      location: supplementForm.location || null,
      reason: supplementForm.reason,
    })
    ElMessage.success(r?.message || '补卡成功')
    showSupplementDialog.value = false
    loadOverview()
    loadList()
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithResponse)?.response?.data?.message || (e as ErrorWithMessage)?.message || '补卡失败')
  } finally {
    supplementSubmitting.value = false
  }
}
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; .page-title { font-size: 20px; font-weight: 600; color: #303133; } .header-actions { display: flex; gap: 8px; } }
.filter-bar { display: flex; gap: 12px; flex-wrap: wrap; padding: 16px; background: #fff; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); align-items: center; }
.content-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); }
.pagination-wrapper { display: flex; justify-content: flex-end; margin-top: 16px; }
.text-muted { color: #c0c4cc; }

.today-card { background: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); }
.today-row { display: flex; gap: 32px; flex-wrap: wrap; align-items: center; }
.today-item { display: flex; flex-direction: column; gap: 4px; }
.today-label { font-size: 12px; color: #909399; }
.today-value { font-size: 16px; font-weight: 600; color: #303133; }
.today-value.is-empty { color: #c0c4cc; font-weight: 400; }
</style>
