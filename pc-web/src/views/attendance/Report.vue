<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">考勤报表</span>
      <div class="header-actions">
        <el-button type="primary" :icon="Download" @click="handleExport">导出报表</el-button>
        <el-button :icon="Printer" @click="handlePrint">打印</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-select v-model="filters.userId" placeholder="选择员工" clearable filterable style="width: 200px" @change="loadReport">
        <el-option v-for="e in employeeOptions" :key="e.value" :label="e.label" :value="e.value" />
      </el-select>
      <el-date-picker v-model="filters.month" type="month" placeholder="选择月份" format="YYYY-MM" value-format="YYYY-MM" style="width: 160px" @change="loadReport" />
      <el-button type="primary" @click="loadReport">查询</el-button>
      <el-button @click="handleReset">重置</el-button>
    </div>

    <div class="summary-cards">
      <div class="summary-card">
        <span class="summary-card__label">总人数</span>
        <span class="summary-card__value">{{ summary.totalEmployees }}</span>
      </div>
      <div class="summary-card summary-card--success">
        <span class="summary-card__label">出勤天数</span>
        <span class="summary-card__value">{{ summary.totalActual }}</span>
      </div>
      <div class="summary-card summary-card--warning">
        <span class="summary-card__label">迟到人次</span>
        <span class="summary-card__value">{{ summary.totalLate }}</span>
      </div>
      <div class="summary-card summary-card--danger">
        <span class="summary-card__label">缺勤人次</span>
        <span class="summary-card__value">{{ summary.totalAbsent }}</span>
      </div>
      <div class="summary-card summary-card--info">
        <span class="summary-card__label">加班总时</span>
        <span class="summary-card__value">{{ summary.totalOvertime }}h</span>
      </div>
    </div>

    <div class="content-card">
      <el-table :data="tableData" stripe style="width: 100%" border v-loading="loading" show-summary :summary-method="getSummaries">
        <el-table-column type="index" label="序号" width="55" />
        <el-table-column label="员工" min-width="100">
          <template #default="{ row }">{{ row.user?.name || row.user?.username || '-' }}</template>
        </el-table-column>
        <el-table-column label="部门" min-width="100">
          <template #default="{ row }">{{ row.user?.department?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="实际出勤(天)" min-width="110">
          <template #default="{ row }">{{ row.total_days }}</template>
        </el-table-column>
        <el-table-column label="迟到(次)" min-width="80">
          <template #default="{ row }">
            <span v-if="row.late_count > 0" class="value--warning">{{ row.late_count }}</span>
            <span v-else>0</span>
          </template>
        </el-table-column>
        <el-table-column label="缺勤(次)" min-width="80">
          <template #default="{ row }">
            <span v-if="row.absent_count > 0" class="value--danger">{{ row.absent_count }}</span>
            <span v-else>0</span>
          </template>
        </el-table-column>
        <el-table-column label="加班(时)" min-width="80">
          <template #default="{ row }">{{ row.overtime_hours }}</template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Download, Printer } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import { exportExcelLike, printTable } from '@/utils/exporter'

interface AttendanceUser {
  name?: string
  username?: string
  department?: { name?: string }
  [key: string]: unknown
}
interface AttendanceReportRow {
  id?: number
  user?: AttendanceUser | null
  name?: string
  user_name?: string
  total_days?: number | string
  late_count?: number | string
  absent_count?: number | string
  overtime_hours?: number | string
  expected_days?: number | string
  actual_days?: number | string
  early_count?: number | string
  leave_days?: number | string
  miss_count?: number | string
  [key: string]: unknown
}
interface RawEmployee {
  id: number
  name?: string
  username?: string
  employee_no?: string
  [key: string]: unknown
}

const loading = ref(false)
const filters = reactive({
  userId: '',
  month: new Date().toISOString().slice(0, 7) // 当前月份 YYYY-MM
})
const employeeOptions = ref<{ label: string; value: number }[]>([])
const tableData = ref<AttendanceReportRow[]>([])
const summary = reactive({ totalEmployees: 0, totalActual: 0, totalLate: 0, totalAbsent: 0, totalOvertime: 0 })

const loadEmployees = async () => {
  try {
    const r = await get('/employees', { per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    const d = r?.data ?? {}
    const items = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : [])
    employeeOptions.value = items.map((e: RawEmployee) => ({ label: `${e.name || e.username} - ${e.employee_no || ''}`, value: e.id }))
  } catch (e) {
    employeeOptions.value = []
  }
}

const loadReport = async () => {
  if (!filters.month) {
    ElMessage.warning('请选择月份')
    return
  }
  loading.value = true
  try {
    const params: Record<string, unknown> = { month: filters.month }
    if (filters.userId) params.user_id = filters.userId
    const res = await get('/attendance/report', params)
    // V0.6.3: request.ts 不解包
    tableData.value = unwrapList(res)
    // 汇总
    summary.totalEmployees = tableData.value.length
    summary.totalActual = tableData.value.reduce((s: number, r: AttendanceReportRow) => s + Number(r.total_days || 0), 0)
    summary.totalLate = tableData.value.reduce((s: number, r: AttendanceReportRow) => s + Number(r.late_count || 0), 0)
    summary.totalAbsent = tableData.value.reduce((s: number, r: AttendanceReportRow) => s + Number(r.absent_count || 0), 0)
    summary.totalOvertime = tableData.value.reduce((s: number, r: AttendanceReportRow) => s + Number(r.overtime_hours || 0), 0)
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '加载失败')
  } finally { loading.value = false }
}

onMounted(() => { loadEmployees(); loadReport() })

const handleReset = () => { filters.userId = ''; filters.month = new Date().toISOString().slice(0, 7); loadReport() }
const handleExport = () => {
  if (!tableData.value?.length) {
    ElMessage.warning('暂无考勤数据可导出')
    return
  }
  const headers = ['员工', '应出勤(天)', '实际出勤(天)', '迟到', '早退', '请假(天)', '加班(小时)', '缺卡', '出勤率']
  const rows: (string | number)[][] = tableData.value.map((u: AttendanceReportRow) => [
    u.name || u.user_name || '-',
    Number(u.expected_days || u.total_days || 0).toFixed(1),
    Number(u.actual_days || u.total_days || 0).toFixed(1),
    u.late_count || 0,
    u.early_count || 0,
    Number(u.leave_days || 0).toFixed(1),
    Number(u.overtime_hours || 0).toFixed(1),
    u.miss_count || u.absent_count || 0,
    u.expected_days ? ((Number(u.actual_days || u.total_days || 0) / Number(u.expected_days)) * 100).toFixed(1) + '%' : '-',
  ])
  exportExcelLike(headers, rows, '考勤月报', { title: `考勤月报 - ${filters.month}` })
}
const handlePrint = () => {
  if (!tableData.value?.length) {
    ElMessage.warning('暂无考勤数据可打印')
    return
  }
  const headers = ['员工', '应出勤', '实际出勤', '迟到', '早退', '请假', '加班', '缺卡', '出勤率']
  const rows: (string | number)[][] = tableData.value.map((u: AttendanceReportRow) => [
    u.name || '-', u.expected_days || 0, u.actual_days || u.total_days || 0,
    u.late_count || 0, u.early_count || 0, u.leave_days || 0,
    u.overtime_hours || 0, u.miss_count || u.absent_count || 0,
    u.expected_days ? ((Number(u.actual_days || u.total_days || 0) / Number(u.expected_days)) * 100).toFixed(1) + '%' : '-',
  ])
  printTable(`考勤月报 - ${filters.month}`, headers, rows, { subtitle: '全员考勤汇总' })
}

const getSummaries = (param: { columns: { property?: string }[]; data: AttendanceReportRow[] }) => {
  const { columns, data } = param
  const sums: string[] = []
  columns.forEach((col, index: number) => {
    if (index === 0) { sums[index] = '合计'; return }
    if (index === 1 || index === 2) { sums[index] = ''; return }
    const prop = col.property
    if (!prop) { sums[index] = ''; return }
    const values = data.map((item: AttendanceReportRow) => Number(item[prop]) || 0)
    sums[index] = values.reduce((p: number, c: number) => p + c, 0).toString()
  })
  return sums
}
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; .page-title { font-size: 20px; font-weight: 600; color: #303133; } .header-actions { display: flex; gap: 8px; } }
.filter-bar { display: flex; gap: 12px; flex-wrap: wrap; padding: 16px; background: #fff; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); align-items: center; }
.summary-cards { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 16px; }
.summary-card { background: #fff; border-radius: 8px; padding: 16px; text-align: center; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); &__label { display: block; font-size: 13px; color: #909399; margin-bottom: 6px; } &__value { display: block; font-size: 24px; font-weight: 700; color: #0C447C; } &--success &__value { color: #1D9E75; } &--warning &__value { color: #BA7517; } &--danger &__value { color: #A32D2D; } &--info &__value { color: #534AB7; } }
.content-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); }
.value--warning { color: #BA7517; font-weight: 600; }
.value--danger { color: #A32D2D; font-weight: 600; }
</style>
