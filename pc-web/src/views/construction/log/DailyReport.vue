<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <el-button :icon="ArrowLeft" plain @click="goBack">返回</el-button>
        <span class="page-title">每日上报</span>
      </div>
      <div class="header-actions">
        <el-button-group>
          <el-button :icon="ArrowLeft" @click="shiftMonth(-1)">上月</el-button>
          <el-button @click="resetMonth">本月</el-button>
          <el-button :icon="ArrowRight" @click="shiftMonth(1)">下月</el-button>
        </el-button-group>
        <el-button :icon="Refresh" @click="loadAll">刷新</el-button>
      </div>
    </div>

    <!-- KPI 月度统计 -->
    <div class="kpi-row">
      <el-card v-for="kpi in kpis" :key="kpi.label" shadow="hover" :body-style="{ padding: '14px 18px' }" class="kpi-card">
        <div class="kpi-label">{{ kpi.label }}</div>
        <div class="kpi-value" :style="{ color: kpi.color }">{{ kpi.value }}</div>
      </el-card>
    </div>

    <!-- 漏报警告 -->
    <OverdueAlert
      v-if="overdueList.length"
      :overdue-list="overdueList"
      class="overdue-block"
      @fill="handleFillOverdue"
    />

    <!-- 日历 + 今日上报 -->
    <div class="main-grid">
      <!-- 日历 -->
      <el-card shadow="never" class="calendar-card">
        <template #header>
          <div class="card-header">
            <span class="card-title">{{ calendarTitle }}</span>
            <div class="legend">
              <span class="legend-item"><span class="dot dot-done"></span>已报</span>
              <span class="legend-item"><span class="dot dot-miss"></span>未报</span>
              <span class="legend-item"><span class="dot dot-today"></span>今天</span>
            </div>
          </div>
        </template>
        <el-calendar v-model="calendarDate">
          <template #date-cell="{ data }">
            <div
              class="cal-cell"
              :class="{
                'is-today': data.isSelected && isToday(data.day),
                'is-done': isReported(data.day),
                'is-miss': isMissed(data.day),
                'not-in-month': data.type !== 'current-month',
              }"
              @click="selectDate(data.day)"
            >
              <div class="cal-day">{{ data.day.split('-').slice(2).join('') }}</div>
              <div v-if="isReported(data.day)" class="cal-badge done-badge">已报</div>
              <div v-else-if="isMissed(data.day)" class="cal-badge miss-badge">未报</div>
            </div>
          </template>
        </el-calendar>
      </el-card>

      <!-- 右侧今日上报表单 -->
      <el-card shadow="never" class="form-card">
        <template #header>
          <div class="card-header">
            <span class="card-title">上报 — {{ selectedDate }}</span>
            <el-tag v-if="selectedLog" :type="statusTagType(selectedLog.status)" effect="plain" size="small">
              {{ statusLabel(selectedLog.status) }}
            </el-tag>
            <el-tag v-else type="info" effect="plain" size="small">未上报</el-tag>
          </div>
        </template>

        <el-form ref="formRef" :model="formData" :rules="formRules" label-width="90px" size="default">
          <el-form-item label="日期" prop="date">
            <el-date-picker
              v-model="formData.date"
              type="date"
              value-format="YYYY-MM-DD"
              :disabled="true"
              style="width: 100%"
            />
          </el-form-item>
          <el-form-item label="天气" prop="weather">
            <el-select v-model="formData.weather" placeholder="请选择" style="width: 100%">
              <el-option v-for="w in weatherOptions" :key="w" :label="w" :value="w" />
            </el-select>
          </el-form-item>
          <el-form-item label="项目" prop="project_id">
            <el-select
              v-model="formData.project_id"
              placeholder="请选择项目"
              filterable
              clearable
              :disabled="!!selectedLog"
              style="width: 100%"
            >
              <el-option
                v-for="p in projectOptions"
                :key="p.id"
                :label="`${p.name}${p.code ? ' (' + p.code + ')' : ''}`"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="施工团队" prop="team_id">
            <el-select
              v-model="formData.team_id"
              placeholder="请选择施工团队"
              filterable
              clearable
              style="width: 100%"
            >
              <el-option
                v-for="t in teamOptions"
                :key="t.id"
                :label="`${t.team_name || t.name}`"
                :value="t.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="工序" prop="process_id">
            <el-select
              v-model="formData.process_id"
              placeholder="请选择工序"
              filterable
              clearable
              style="width: 100%"
            >
              <el-option
                v-for="p in processOptions"
                :key="p.id"
                :label="`${p.name}${p.code ? ' (' + p.code + ')' : ''}`"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="工人数量" prop="worker_count">
            <el-input-number v-model="formData.worker_count" :min="1" :step="1" style="width: 100%" />
          </el-form-item>
          <el-form-item label="工时" prop="work_hours">
            <el-input-number v-model="formData.work_hours" :min="0" :step="1" style="width: 100%" />
          </el-form-item>
          <el-form-item label="进度" prop="progress">
            <el-input v-model="formData.progress" placeholder="如: 50% / 主体完成 / 已验收" maxlength="200" />
          </el-form-item>
          <el-form-item label="问题与风险">
            <el-input v-model="formData.issues" type="textarea" :rows="3" maxlength="1000" show-word-limit />
          </el-form-item>
          <el-form-item label="照片">
            <div class="upload-area">
              <el-button type="primary" :icon="Plus" @click="triggerUpload">增加图片</el-button>
              <input ref="fileInputRef" type="file" multiple accept="image/*" style="display:none" @change="handleFileChange" />
              <div v-if="photoList.length" class="photo-preview">
                <div v-for="(img, idx) in photoList" :key="idx" class="photo-item">
                  <el-image :src="img.url" fit="cover" style="width:100px;height:100px;border-radius:6px" />
                  <el-button link type="danger" size="small" @click="removePhoto(idx)" style="position:absolute;top:-6px;right:-6px">
                    <el-icon><Close /></el-icon>
                  </el-button>
                </div>
              </div>
              <div style="font-size:12px;color:#94a3b8;margin-top:6px">支持 jpg/png，手机端后续支持拍照+水印+定位</div>
            </div>
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="formData.remark" type="textarea" :rows="2" maxlength="500" show-word-limit />
          </el-form-item>
        </el-form>

        <div class="form-actions">
          <el-button v-if="selectedLog" :icon="Upload" type="success" :loading="submitting" @click="handleSubmit">
            提交日志
          </el-button>
          <el-button v-if="!selectedLog" type="primary" :loading="saving" @click="handleSave('draft')">
            保存草稿
          </el-button>
          <el-button v-if="!selectedLog" type="success" :loading="saving" @click="handleSave('submit')">
            保存并提交
          </el-button>
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, ArrowRight, Refresh, Upload, Plus, Close } from '@element-plus/icons-vue'
import { logApi, workProcessApi } from '@/api/construction'
import { get } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import OverdueAlert from './components/OverdueAlert.vue'
import type { LogRow, OverdueItem, CommencementOption, ProcessOption } from '../types'

const router = useRouter()

const weatherOptions = ['晴', '多云', '阴', '小雨', '中雨', '大雨', '雪', '雾', '大风']

const statusLabel = (s: string) => ({
  draft: '草稿', submitted: '已提交', approved: '已审核',
} as Record<string, string>)[s] || s || '-'
const statusTagType = (s: string): string => ({
  draft: 'info', submitted: 'warning', approved: 'success',
} as Record<string, string>)[s] || 'info'

const today = new Date()
const todayStr = today.toISOString().slice(0, 10)

const calendarDate = ref(today)
const selectedDate = ref(todayStr)
const calendarTitle = computed(() => {
  const d = calendarDate.value
  return `${d.getFullYear()} 年 ${d.getMonth() + 1} 月`
})

const monthLogs = ref<LogRow[]>([])      // 当月所有日志
const overdueList = ref<OverdueItem[]>([])    // 漏报
const projectOptions = ref<Record<string, unknown>[]>([])
const processOptions = ref<ProcessOption[]>([])

// 照片上传
const photoList = ref<{ name: string; url: string }[]>([])
const fileInputRef = ref<HTMLInputElement | null>(null)

const teamOptions = ref<Record<string, unknown>[]>([])

const formRef = ref()
const saving = ref(false)
const submitting = ref(false)

const formData = reactive({
  date: todayStr,
  weather: '晴',
  project_id: null as number | null,
  process_id: null as number | null,
  team_id: null as number | null,
  worker_count: 1,
  work_hours: 0,
  progress: '',
  issues: '',
  photos: [] as string[],
  remark: '',
  content: '',
})

const formRules = {
  date:        [{ required: true, message: '请选择日期', trigger: 'change' }],
  weather:     [{ required: true, message: '请选择天气', trigger: 'change' }],
  project_id:  [{ required: true, message: '请选择项目', trigger: 'change' }],
  worker_count:[{ required: true, message: '请填写工人数量', trigger: 'blur' }],
  work_hours:  [{ required: true, message: '请填写工时', trigger: 'blur' }],
  progress:    [{ required: true, message: '请填写进度', trigger: 'blur' }],
}

const logDate = (l: Record<string, unknown>) => {
  const raw = (l.work_date as string) || (l.date as string) || ''
  return raw.slice(0, 10)
}

// === 当前选中的日志（如果已存在）===
const selectedLog = computed(() => {
  return monthLogs.value.find(l => logDate(l) === selectedDate.value) || null
})

// === 标记日历单元格 ===
const reportedDates = computed(() => new Set(monthLogs.value.map(l => logDate(l))))
const isReported = (day: string) => reportedDates.value.has(day)
const isToday = (day: string) => day === todayStr
const isMissed = (day: string) => {
  if (day >= todayStr) return false   // 未来/今天不标记为漏报
  if (isReported(day)) return false
  return true
}

// === KPI ===
const kpis = computed(() => {
  const reported = monthLogs.value.length
  const submitted = monthLogs.value.filter(l => l.status === 'submitted' || l.status === 'approved').length
  const totalHours = monthLogs.value.reduce((s, l) => s + Number(l.work_hours || 0), 0)
  const avgProgress = reported
    ? Math.round(monthLogs.value.reduce((s, l) => s + Number(l.progress || 0), 0) / reported)
    : 0
  return [
    { label: '本月已报', value: reported, color: '#0C447C' },
    { label: '已提交/审核', value: submitted, color: '#67c23a' },
    { label: '累计工时', value: `${totalHours} h`, color: '#1D9E75' },
    { label: '平均进度', value: `${avgProgress}%`, color: '#E6A23C' },
  ]
})

// === 加载 ===
const monthRange = computed(() => {
  const d = calendarDate.value
  const y = d.getFullYear()
  const m = d.getMonth()
  const start = new Date(y, m, 1)
  const end = new Date(y, m + 1, 0)
  const fmt = (x: Date) => x.toISOString().slice(0, 10)
  return { from: fmt(start), to: fmt(end) }
})

const loadMonthLogs = async () => {
  try {
    const res = await logApi.list({
      per_page: 500,
      date_from: monthRange.value.from,
      date_to: monthRange.value.to,
    })
    const arr = unwrapList(res)
    monthLogs.value = arr
  } catch {
    monthLogs.value = []
  }
}

const loadOverdue = async () => {
  try {
    const res = await logApi.overdue({ per_page: 20 })
    const arr = unwrapList(res)
    overdueList.value = arr
  } catch {
    overdueList.value = []
  }
}

const loadTeamOptions = async () => {
  try {
    const res = await get('/construction/teams', { per_page: 500, status: 'active' })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    teamOptions.value = d as Record<string, unknown>[]
  } catch { teamOptions.value = [] }
}

const loadProjectOptions = async () => {
  try {
    const res = await get('/projects', { per_page: 500 })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    projectOptions.value = (d as Record<string, unknown>[]) || []
  } catch {
    projectOptions.value = []
  }
}

const loadProcessOptions = async () => {
  try {
    const res = await workProcessApi.list({ per_page: 500 })
    const arr = unwrapList(res)
    processOptions.value = arr
  } catch {
    processOptions.value = []
  }
}

const loadAll = async () => {
  await Promise.all([loadMonthLogs(), loadOverdue()])
  syncFormFromSelected()
}

const syncFormFromSelected = () => {
  const log = selectedLog.value
  if (log) {
    const r = log as Record<string, unknown>
    formData.date = logDate(r) || selectedDate.value
    formData.weather = r.weather as string || '晴'
    formData.project_id = r.project_id as number ?? null
    formData.process_id = r.process_id as number ?? null
    formData.team_id = r.team_id as number ?? null
    formData.worker_count = Number(r.worker_count || 1)
    formData.work_hours = Number(r.work_hours || 0)
    formData.progress = String(r.progress || r.progress_percentage || '')
    formData.issues = r.issues as string || r.problems as string || ''
    const ps = r.photos
    formData.photos = Array.isArray(ps) ? ps : []
    formData.remark = r.remark as string || ''
    photoList.value = (formData.photos as string[]).map((u: string) => ({ name: u.split('/').pop() || u, url: u }))
  } else {
    formData.date = selectedDate.value
    formData.weather = '晴'
    formData.project_id = null
    formData.process_id = null
    formData.team_id = null
    formData.worker_count = 1
    formData.work_hours = 0
    formData.progress = ''
    formData.issues = ''
    formData.photos = []
    formData.remark = ''
    photoList.value = []
  }
}

// === 操作 ===
const shiftMonth = (n: number) => {
  const d = new Date(calendarDate.value)
  d.setMonth(d.getMonth() + n)
  calendarDate.value = d
  loadMonthLogs()
}
const resetMonth = () => {
  calendarDate.value = new Date()
  selectedDate.value = todayStr
  loadMonthLogs()
  syncFormFromSelected()
}

const selectDate = (day: string) => {
  selectedDate.value = day
  syncFormFromSelected()
}

const goBack = () => router.push('/construction/log')

const handleFillOverdue = (item: OverdueItem) => {
  if (item.date) {
    selectedDate.value = item.date
    formData.date = item.date
    if ((item as Record<string, unknown>).project_id) formData.project_id = (item as Record<string, unknown>).project_id as number
    syncFormFromSelected()
  }
}

// 照片上传 — base64 data URL
const triggerUpload = () => fileInputRef.value?.click()
const handleFileChange = (e: Event) => {
  const files = (e.target as HTMLInputElement).files
  if (!files) return
  for (let i = 0; i < files.length; i++) {
    const file = files[i]
    const reader = new FileReader()
    reader.onload = (ev) => {
      const url = ev.target?.result as string
      if (url) {
        photoList.value.push({ name: file.name, url })
        formData.photos.push(url)
      }
    }
    reader.readAsDataURL(file)
  }
  ;(e.target as HTMLInputElement).value = ''
}
const removePhoto = (idx: number) => {
  photoList.value.splice(idx, 1)
  formData.photos.splice(idx, 1)
}

const handleSave = async (action: 'draft' | 'submit') => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      work_date: formData.date,
      project_id: formData.project_id,
      team_id: formData.team_id,
      weather: formData.weather,
      process_id: formData.process_id,
      worker_count: Number(formData.worker_count || 1),
      work_hours: Number(formData.work_hours || 0),
      content: [formData.progress, formData.content || formData.issues].filter(Boolean).join(' / ') || '-',
      problems: formData.issues,
      photos: formData.photos,
      remark: formData.remark,
    }
    if (formData.weather) payload.weather = formData.weather
    const res = await logApi.create(payload)
    const data = (res as Record<string, unknown>)?.data as Record<string, unknown> || res as Record<string, unknown>
    const id = data?.id as number
    if (action === 'submit' && id) {
      await logApi.submit(id)
      ElMessage.success('已上报并提交')
    } else {
      ElMessage.success('草稿已保存')
    }
    await loadAll()
  } catch { /* 拦截器已提示 */ }
  finally { saving.value = false }
}

const handleSubmit = async () => {
  if (!selectedLog.value?.id) return
  submitting.value = true
  try {
    await logApi.submit(selectedLog.value.id)
    ElMessage.success('已提交')
    await loadAll()
  } catch { /* 拦截器已提示 */ }
  finally { submitting.value = false }
}

watch(calendarDate, () => loadMonthLogs())
watch(selectedDate, () => syncFormFromSelected())

onMounted(() => {
  loadTeamOptions()
  loadProjectOptions()
  loadProcessOptions()
  loadAll()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .header-left { display: flex; align-items: center; gap: 12px; }
  .page-title {
    font-size: 18px; font-weight: 600; color: #0C447C;
    border-left: 4px solid #0C447C; padding-left: 10px;
  }
  .header-actions { display: flex; gap: 8px; }
}
.kpi-row {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 12px;
}
.kpi-card {
  .kpi-label { color: #909399; font-size: 13px; }
  .kpi-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
}
.overdue-block { margin-bottom: 12px; }

.main-grid {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 12px;
}
.calendar-card :deep(.el-calendar) { padding: 0; }
.calendar-card :deep(.el-calendar__header) { padding: 8px 16px; }
.calendar-card :deep(.el-calendar-day) { padding: 4px; height: 64px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-title { font-weight: 600; color: #303133; }

.cal-cell {
  height: 56px; display: flex; flex-direction: column; align-items: flex-start; justify-content: space-between;
  padding: 4px 6px; border-radius: 4px; cursor: pointer; transition: background 0.2s;
}
.cal-cell:hover { background: #f5f7fa; }
.cal-cell.is-today { background: #ecf5ff; }
.cal-cell.is-done { background: #f0f9eb; }
.cal-cell.is-miss { background: #fef0f0; }
.cal-cell.not-in-month { opacity: 0.4; }
.cal-day { font-size: 14px; font-weight: 500; }
.cal-badge {
  font-size: 11px; padding: 1px 6px; border-radius: 8px; line-height: 1.4;
}
.done-badge { background: #67c23a; color: #fff; }
.miss-badge { background: #f56c6c; color: #fff; }

.legend { display: flex; gap: 12px; font-size: 12px; color: #606266; }
.legend-item { display: flex; align-items: center; gap: 4px; }
.dot { width: 8px; height: 8px; border-radius: 50%; }
.dot-done { background: #67c23a; }
.dot-miss { background: #f56c6c; }
.dot-today { background: #409eff; }

.upload-area { width: 100%; }
.photo-preview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.photo-item { position: relative; display: inline-block; }

.form-card {
  height: fit-content;
  .form-actions {
    display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px;
    padding-top: 12px; border-top: 1px solid #ebeef5;
  }
}
</style>
