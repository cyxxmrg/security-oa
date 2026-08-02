<template>
  <div class="tab-content">
    <h3 class="section-title">工序验收</h3>

    <!-- 4 个核心指标 -->
    <el-row :gutter="16" class="kpi-row">
      <el-col :span="6">
        <div class="kpi-card">
          <div class="kpi-label">工序实例</div>
          <div class="kpi-value">{{ stats.totalInstances }}</div>
          <div class="kpi-sub">已建</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="kpi-card">
          <div class="kpi-label">已完成</div>
          <div class="kpi-value" style="color: #1D9E75">{{ stats.completedInstances }}</div>
          <div class="kpi-sub">实例</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="kpi-card">
          <div class="kpi-label">验收记录</div>
          <div class="kpi-value">{{ stats.totalInspections }}</div>
          <div class="kpi-sub">次</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="kpi-card">
          <div class="kpi-label">验收合格率</div>
          <div class="kpi-value" :style="{ color: passRateColor }">{{ passRateText }}%</div>
          <div class="kpi-sub">合格/总验收</div>
        </div>
      </el-col>
    </el-row>

    <!-- 实例列表 -->
    <div class="toolbar">
      <h4 class="sub-title">工序实例 ({{ instances.length }})</h4>
      <el-button type="primary" :icon="Plus" @click="openCreateDialog">新建工序实例</el-button>
    </div>
    <el-table v-loading="processLoading" :data="instances" border>
      <el-table-column prop="id" label="实例ID" width="80" align="center">
        <template #default="{ row }"><span class="id-text">#{{ row.id }}</span></template>
      </el-table-column>
      <el-table-column label="工序名称" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as Record<string, unknown>).name || (row as Record<string, unknown>).template?.name || row.template_name || '-' }}</template>
      </el-table-column>
      <el-table-column label="工序编号" min-width="110" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as Record<string, unknown>).template?.code || row.code || '-' }}</template>
      </el-table-column>
      <el-table-column label="计划工期" min-width="190">
        <template #default="{ row }">
          <span class="stage-text">
            {{ formatDate((row as Record<string, unknown>).planned_start_date || row.planned_start) }}
            <span class="date-sep">~</span>
            {{ formatDate((row as Record<string, unknown>).planned_end_date || row.planned_end) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="实际工期" min-width="190">
        <template #default="{ row }">
          <span class="stage-text">
            {{ formatDate((row as Record<string, unknown>).actual_start_date || row.actual_start) }}
            <span class="date-sep">~</span>
            {{ formatDate((row as Record<string, unknown>).actual_end_date || row.actual_end) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="施工位置" min-width="140" show-overflow-tooltip>
        <template #default="{ row }">{{ row.location || '-' }}</template>
      </el-table-column>
      <el-table-column label="负责人" min-width="100" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as Record<string, unknown>).foreman?.name || row.foreman_name || row.assignee_name || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="instanceStatusTagType(row.status)" size="small">
            {{ instanceStatusLabel(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="进度" width="70" align="center">
        <template #default="{ row }">{{ row.progress || 0 }}%</template>
      </el-table-column>
      <el-table-column label="操作" width="80" align="center" fixed="right">
        <template #default="{ row }">
          <el-button text type="primary" @click="$emit('open-instance', row.id)">详情</el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-empty v-if="!processLoading && instances.length === 0" description="暂无工序实例" :image-size="80" />

    <!-- 验收记录折叠 -->
    <el-collapse v-model="collapseOpen" class="process-collapse">
      <el-collapse-item name="inspections" :title="`验收记录（${inspections.length}）`">
        <el-table :data="inspections" border @row-click="onRowClick">
          <el-table-column prop="id" label="ID" width="70" align="center" />
          <el-table-column label="工序" min-width="160" show-overflow-tooltip>
            <template #default="{ row }">{{ (row as Record<string, unknown>).process_instance?.name || (row as Record<string, unknown>).processInstance?.name || row.process_name || row.process_instance_id || '-' }}</template>
          </el-table-column>
          <el-table-column label="工序编号" min-width="110" show-overflow-tooltip>
            <template #default="{ row }">{{ (row as Record<string, unknown>).process_instance?.code || (row as Record<string, unknown>).processInstance?.code || '-' }}</template>
          </el-table-column>
          <el-table-column label="验收类型" width="100" align="center">
            <template #default="{ row }">{{ inspectionTypeLabel(row.inspection_type) }}</template>
          </el-table-column>
          <el-table-column label="验收人" min-width="100" show-overflow-tooltip>
            <template #default="{ row }">{{ (row as Record<string, unknown>).inspector?.name || row.inspector_name || '-' }}</template>
          </el-table-column>
          <el-table-column label="验收时间" min-width="150">
            <template #default="{ row }">{{ formatInspectionTime((row as Record<string, unknown>).inspection_date || row.inspected_at) }}</template>
          </el-table-column>
          <el-table-column label="结果" width="90" align="center">
            <template #default="{ row }">
              <el-tag :type="inspectionResultTagType(row.result)" size="small">
                {{ inspectionResultLabel(row.result) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="评分" width="70" align="center">
            <template #default="{ row }">{{ row.score ?? '-' }}</template>
          </el-table-column>
          <el-table-column label="备注" min-width="140" show-overflow-tooltip>
            <template #default="{ row }">{{ row.remark || row.comment || '-' }}</template>
          </el-table-column>
          <el-table-column label="整改项" min-width="140" show-overflow-tooltip>
            <template #default="{ row }">
              <span v-if="formatIssues(row.issues)" style="color: #A32D2D">{{ formatIssues(row.issues) }}</span>
              <span v-else>-</span>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="inspections.length === 0" description="暂无验收记录" :image-size="60" />
      </el-collapse-item>
    </el-collapse>

    <!-- 新建工序实例 dialog -->
    <el-dialog
      v-model="createDialog.visible"
      title="新建工序实例"
      width="540px"
      destroy-on-close
      @close="resetCreateDialog"
    >
      <el-form
        :model="createDialog.form"
        label-width="110px"
        :rules="createDialog.rules"
        ref="createFormRef"
      >
        <el-form-item label="工序模板" prop="template_id">
          <el-select
            v-model="createDialog.form.template_id"
            placeholder="请选择工序模板"
            filterable
            style="width: 100%"
            :loading="createDialog.templateLoading"
            @visible-change="onTemplateDropdownToggle"
          >
            <el-option
              v-for="t in templateOptions"
              :key="t.id"
              :label="t.name || t.template_name"
              :value="t.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="计划开始日期" prop="planned_start">
          <el-date-picker
            v-model="createDialog.form.planned_start"
            type="date"
            placeholder="选择日期"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="createDialog.form.remark"
            type="textarea"
            :rows="3"
            placeholder="可选"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="createDialog.submitting" @click="submitCreate">
          确定创建
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { processApi } from '@/api/modules'
import { unwrapList } from '@/utils/response'
import {
  type ProcessInstance, type ProcessInspection, type TagType,
  formatDate,
} from '../types'

const props = defineProps<{
  instances: ProcessInstance[]
  inspections: ProcessInspection[]
  processLoading: boolean
  projectId: number
}>()

const emit = defineEmits<{
  (e: 'open-instance', id: number | string): void
  (e: 'refresh'): void
}>()

const collapseOpen = ref<string[]>([])  // 默认折叠
const templateOptions = ref<Array<{ id: number; name: string }>>([])

const createDialog = reactive({
  visible: false,
  submitting: false,
  templateLoading: false,
  form: {
    template_id: null as number | null,
    planned_start: '' as string,
    remark: '' as string,
  },
  rules: {
    template_id: [{ required: true, message: '请选择工序模板', trigger: 'change' }],
    planned_start: [{ required: true, message: '请选择计划开始日期', trigger: 'change' }],
  } as Record<string, unknown>,
})

const createFormRef = ref<{ validate: () => Promise<boolean> } | null>(null)

const stats = computed(() => {
  const insts = props.instances || []
  const insp = props.inspections || []
  return {
    totalInstances: insts.length,
    completedInstances: insts.filter((i: Record<string, unknown>) => i.status === 'completed').length,
    totalInspections: insp.length,
    passedInspections: insp.filter((i: Record<string, unknown>) => i.result === 'pass').length,
  }
})

const passRate = computed(() => {
  const t = stats.value.totalInspections
  if (!t) return 0
  return Math.round((stats.value.passedInspections / t) * 1000) / 10
})
const passRateText = computed(() => passRate.value.toFixed(1))
const passRateColor = computed(() => {
  const v = passRate.value
  if (v >= 90) return '#1D9E75'
  if (v >= 75) return '#185FA5'
  if (v >= 60) return '#BA7517'
  return '#A32D2D'
})

// helpers
function extractList<T = Record<string, unknown>>(res: unknown): T[] {
  return unwrapList(res) as T[]
}

const instanceStatusLabel = (s?: string) => {
  const map: Record<string, string> = {
    pending: '待开始', in_progress: '进行中', completed: '已完成',
    rejected: '已驳回', accepted: '已验收', overdue: '已超期',
  }
  return map[s || ''] || s || '-'
}
const instanceStatusTagType = (s?: string): TagType => {
  const map: Record<string, TagType> = {
    pending: 'info', in_progress: 'primary', completed: 'success',
    rejected: 'danger', accepted: 'success', overdue: 'warning',
  }
  return (map[s || ''] as TagType) || 'info'
}

const inspectionResultLabel = (r?: string) => {
  const map: Record<string, string> = { pass: '合格', fail: '不合格', partial: '部分合格', pending: '待验收' }
  return map[r || ''] || r || '-'
}
const inspectionResultTagType = (r?: string): TagType => {
  const map: Record<string, TagType> = { pass: 'success', fail: 'danger', partial: 'warning', pending: 'info' }
  return (map[r || ''] as TagType) || 'info'
}
const inspectionTypeLabel = (t?: string) => {
  const map: Record<string, string> = { self: '自检', mutual: '互检', supervisor: '监理', owner: '甲方' }
  return map[t || ''] || t || '-'
}
const formatIssues = (issues?: unknown): string => {
  if (!issues) return ''
  if (Array.isArray(issues)) return issues.map(i => typeof i === 'object' ? JSON.stringify(i) : String(i)).join('；')
  if (typeof issues === 'string') return issues
  return JSON.stringify(issues)
}

const progressPct = (row: Record<string, unknown>): number => {
  if (row.status === 'completed' || row.status === 'accepted') return 100
  const startStr = (row.planned_start_date || row.planned_start) as string | undefined
  const endStr = (row.planned_end_date || row.planned_end) as string | undefined
  if (!startStr || !endStr) return 0
  const start = new Date(startStr).getTime()
  const end = new Date(endStr).getTime()
  if (isNaN(start) || isNaN(end) || end <= start) return 0
  const now = Date.now()
  if (now < start) return 0
  if (now > end) return 100
  return Math.round(((now - start) / (end - start)) * 100)
}

const progressBarColor = (p: number): string => {
  if (p < 30) return '#A32D2D'
  if (p <= 70) return '#BA7517'
  return '#1D9E75'
}

const formatInspectionTime = (s?: string) => {
  if (!s) return '-'
  // V1.2.13: 直接 slice 头 16 字符避免 new Date('YYYY-MM-DD') 时区偏一天的问题
  // 同时支持 'YYYY-MM-DDTHH:mm:ss.sssZ' 长格式
  if (s.includes('T')) {
    return s.replace('T', ' ').slice(0, 16)
  }
  return s.slice(0, 16)
}

const onRowClick = (row: Record<string, unknown>) => {
  if (row && row.process_instance_id) emit('open-instance', row.process_instance_id)
}

const onTemplateDropdownToggle = (visible: boolean) => {
  if (visible && templateOptions.value.length === 0) {
    loadTemplateOptions()
  }
}

const loadTemplateOptions = async () => {
  createDialog.templateLoading = true
  try {
    const res = await processApi.templateList({ per_page: 200 })
    templateOptions.value = extractList<{ id: number; name: string }>(res)
  } catch (e) {
    console.error('[loadTemplateOptions]', e)
    templateOptions.value = []
  } finally {
    createDialog.templateLoading = false
  }
}

const openCreateDialog = () => { createDialog.visible = true }

const resetCreateDialog = () => {
  createDialog.form = { template_id: null, planned_start: '', remark: '' }
}

const submitCreate = async () => {
  // 简单校验 (UI 已有 rules, 兜底再校验)
  if (!createDialog.form.template_id) {
    ElMessage.warning('请选择工序模板')
    return
  }
  if (!createDialog.form.planned_start) {
    ElMessage.warning('请选择计划开始日期')
    return
  }
  createDialog.submitting = true
  try {
    await processApi.instanceCreate({
      project_id: props.projectId,
      template_id: createDialog.form.template_id,
      planned_start: createDialog.form.planned_start,
      remark: createDialog.form.remark,
    })
    ElMessage.success('工序实例已创建')
    createDialog.visible = false
    resetCreateDialog()
    emit('refresh')
  } catch (e: unknown) {
    ElMessage.error(e?.response?.data?.message || e?.message || '创建失败')
  } finally {
    createDialog.submitting = false
  }
}
</script>

<style scoped>
.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  margin: 0 0 16px 0;
  padding-left: 8px;
  border-left: 3px solid #0C447C;
}
.sub-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin: 0;
}
.kpi-row {
  margin-bottom: 16px;
}
.kpi-card {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 16px 20px;
  transition: all 0.2s;
}
.kpi-card:hover {
  border-color: #0C447C;
  box-shadow: 0 2px 12px rgba(12, 68, 124, 0.08);
}
.kpi-label {
  font-size: 12px;
  color: #909399;
  margin-bottom: 8px;
}
.kpi-value {
  font-size: 24px;
  font-weight: 700;
  color: #303133;
  line-height: 1.2;
}
.kpi-sub {
  font-size: 11px;
  color: #c0c4cc;
  margin-top: 4px;
}
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 16px 0 12px 0;
}
.id-text {
  color: #909399;
  font-family: monospace;
}
.stage-text {
  font-size: 12px;
  color: #606266;
}
.progress-bar-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}
.progress-label {
  font-size: 11px;
  color: #909399;
  white-space: nowrap;
}
.process-collapse {
  margin-top: 16px;
}
</style>
