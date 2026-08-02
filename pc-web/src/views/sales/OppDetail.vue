<template>
  <div class="opp-detail-page">
    <!-- 顶栏 -->
    <div class="page-header">
      <div class="title-area">
        <el-button :icon="ArrowLeft" text @click="$router.back()">返回</el-button>
        <span class="page-title">{{ opp?.name || '商机详情' }}</span>
        <el-tag v-if="opp?.opp_no" effect="plain" type="info">{{ opp.opp_no }}</el-tag>
        <el-tag v-if="opp?.stage" :type="stageTagType(opp.stage)" effect="dark" size="small">
          {{ stageLabel(opp.stage) }}
        </el-tag>
        <el-tag v-if="opp?.customer?.name" effect="plain">
          {{ opp.customer.name }}
        </el-tag>
      </div>
      <div class="header-actions">
        <el-button type="primary" :icon="Plus" @click="openStageDialog(null)">录入阶段数据</el-button>
      </div>
    </div>

    <!-- 商机基础信息卡 -->
    <div v-if="opp" class="info-card">
      <el-descriptions :column="4" border>
        <el-descriptions-item label="商机编号">{{ opp.opp_no }}</el-descriptions-item>
        <el-descriptions-item label="商机名称">{{ opp.name }}</el-descriptions-item>
        <el-descriptions-item label="客户">{{ opp.customer?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="类型">{{ opp.type || '-' }}</el-descriptions-item>
        <el-descriptions-item label="预估金额">¥ {{ formatMoney(opp.estimated_amount) }}</el-descriptions-item>
        <el-descriptions-item label="预计签约">{{ formatDate(opp.expected_sign_date) }}</el-descriptions-item>
        <el-descriptions-item label="销售负责人">{{ opp.sales?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="售前负责人">{{ opp.presale?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="成功概率" :span="2">
          <el-progress
            :percentage="opp.probability || 0"
            :stroke-width="10"
            :color="probabilityColor(opp.probability)"
          />
        </el-descriptions-item>
        <el-descriptions-item label="备注" :span="2">{{ opp.notes || '-' }}</el-descriptions-item>
      </el-descriptions>
    </div>

    <!-- 流程图：主轴 6 段 + 分叉到 成交/战败 平行终态 -->
    <div class="stage-timeline-card">
      <div class="card-title">
        <span>销售流程（6 主阶段 + 2 终态）</span>
        <span class="card-title-hint">前 6 段为顺序推进，成交与战败是平行终态</span>
      </div>

      <!-- 主轴 6 阶段 -->
      <div class="stage-flow">
        <div
          v-for="(s, idx) in MAIN_STAGES"
          :key="s.value"
          class="stage-node"
          :class="{
            'is-current': opp?.stage === s.value,
            'is-passed': isStagePassed(s.value),
          }"
          :style="{ '--stage-color': s.color }"
        >
          <div class="stage-circle">
            <el-icon v-if="isStagePassed(s.value)" :size="14"><Check /></el-icon>
            <span v-else>{{ idx + 1 }}</span>
          </div>
          <div class="stage-info">
            <div class="stage-label">{{ s.label }}</div>
            <div class="stage-meta">
              <template v-if="getLatestRecord(s.value)">
                <span class="meta-time">{{ formatDate(getLatestRecord(s.value)?.entered_at) }}</span>
                <el-tag v-if="getLatestRecord(s.value)?.entered_by?.name" size="small" effect="plain">
                  {{ getLatestRecord(s.value)?.entered_by?.name }}
                </el-tag>
              </template>
              <span v-else class="meta-empty">未录入</span>
            </div>
          </div>
          <el-button link type="primary" size="small" @click="openStageDialog(s.value)">
            {{ getLatestRecord(s.value) ? '编辑' : '录入' }}
          </el-button>
          <div v-if="idx < MAIN_STAGES.length - 1" class="stage-line"></div>
        </div>
      </div>

      <!-- 分叉：成交 / 战败 平行终态 -->
      <div class="fork-section">
        <div class="fork-line-from-quoted"></div>
        <div class="fork-nodes">
          <div
            class="fork-node fork-won"
            :class="{
              'is-current': opp?.stage === 'won',
              'is-passed': opp?.stage === 'won',
            }"
          >
            <div class="fork-circle"><el-icon :size="20"><Trophy /></el-icon></div>
            <div class="fork-label">成交</div>
            <div class="fork-meta">
              <template v-if="getLatestRecord('won')">
                <el-tag size="small" type="success" effect="dark">已成交</el-tag>
                <div class="meta-info">{{ getLatestRecord('won')?.note || getLatestRecord('won')?.data?.sign_party || '合同已签' }}</div>
              </template>
              <span v-else class="meta-empty">未成交</span>
            </div>
            <el-button
              v-if="getLatestRecord('won')"
              type="success"
              size="small"
              :icon="Promotion"
              @click="handleConvertToProject"
              style="margin-top: 8px;"
            >
              一键转新项目
            </el-button>
            <el-button
              v-if="getLatestRecord('won')"
              type="primary"
              size="small"
              :icon="Plus"
              :loading="creatingProject"
              @click="handleCreateProject"
              style="margin-top: 4px;"
            >
              {{ creatingProject ? '创建中...' : '创建项目' }}
            </el-button>
            <el-button link type="primary" size="small" @click="openStageDialog('won')" style="margin-top: 4px;">
              {{ getLatestRecord('won') ? '查看/编辑' : '录入成交' }}
            </el-button>
          </div>

          <div class="fork-divider"></div>

          <div
            class="fork-node fork-lost"
            :class="{
              'is-current': opp?.stage === 'lost',
              'is-passed': opp?.stage === 'lost',
            }"
          >
            <div class="fork-circle"><el-icon :size="20"><CircleClose /></el-icon></div>
            <div class="fork-label">战败</div>
            <div class="fork-meta">
              <template v-if="getLatestRecord('lost')">
                <el-tag size="small" type="danger" effect="dark">已战败</el-tag>
                <div class="meta-info">{{ getLatestRecord('lost')?.data?.lost_reason_detail || '项目终止' }}</div>
              </template>
              <span v-else class="meta-empty">未战败</span>
            </div>
            <el-button link type="primary" size="small" @click="openStageDialog('lost')" style="margin-top: 4px;">
              {{ getLatestRecord('lost') ? '查看/编辑' : '录入战败' }}
            </el-button>
          </div>
        </div>
      </div>
    </div>

    <!-- 各阶段录入列表 -->
    <div class="stages-records">
      <el-empty v-if="records.length === 0" description="暂无阶段流转记录" />
      <div v-for="s in STAGE_OPTIONS" :key="s.value" class="stage-section">
        <div v-if="getRecordsByStage(s.value).length > 0">
          <div class="stage-section-header" :style="{ borderLeftColor: s.color }">
            <span class="stage-section-title">
              <el-icon :size="14" :color="s.color"><Aim /></el-icon>
              {{ s.label }}
            </span>
            <el-tag size="small" type="info">{{ getRecordsByStage(s.value).length }} 条</el-tag>
          </div>

          <el-table :data="getRecordsByStage(s.value)" border size="small">
            <el-table-column label="录入时间" width="160">
              <template #default="{ row }">{{ formatDateTime(row.entered_at) }}</template>
            </el-table-column>
            <el-table-column label="录入人" width="100">
              <template #default="{ row }">{{ row.entered_by?.name || '-' }}</template>
            </el-table-column>
            <el-table-column label="下一流转人" width="120">
              <template #default="{ row }">
                <el-tag v-if="row.next_assignee_name" size="small" type="warning" effect="plain">
                  <el-icon style="vertical-align: -2px"><UserFilled /></el-icon>
                  @{{ row.next_assignee_name }}
                </el-tag>
                <span v-else class="muted">—</span>
              </template>
            </el-table-column>
            <el-table-column label="期望完成" width="150">
              <template #default="{ row }">{{ formatDateTime(row.next_due_at) }}</template>
            </el-table-column>
            <el-table-column label="数据摘要" min-width="240" show-overflow-tooltip>
              <template #default="{ row }">{{ summarizeData(s.value, row.data) }}</template>
            </el-table-column>
            <el-table-column label="附件" width="140">
              <template #default>
                <div v-if="stageFileTags[s.value]?.length" class="file-tags">
                  <el-tag
                    v-for="tag in stageFileTags[s.value]"
                    :key="tag.id"
                    size="small"
                    effect="plain"
                    :type="tagType(tag.mime_type)"
                    style="margin-right: 2px; margin-bottom: 2px; max-width: 100px;"
                  >
                    <el-icon style="vertical-align: -2px; margin-right: 2px;"><Paperclip /></el-icon>
                    {{ tag.short }}
                  </el-tag>
                </div>
                <span v-else class="muted">—</span>
              </template>
            </el-table-column>
            <el-table-column label="备注" min-width="160" show-overflow-tooltip>
              <template #default="{ row }">{{ row.note || '-' }}</template>
            </el-table-column>
            <el-table-column label="操作" width="160" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" size="small" @click="openStageDialog(s.value, row)">编辑</el-button>
                <el-button link type="danger" size="small" @click="handleDeleteRecord(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>
    </div>

    <!-- 阶段录入对话框 -->
    <StageRecordDialog
      v-model:visible="showStageDialog"
      :opp-id="oppId"
      :stage="activeStage"
      :record="editingRecord"
      :stage-schema="stageSchema"
      :loading="submitting"
      @save="handleSaveRecord"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, ArrowLeft, Aim, Check, UserFilled, Trophy, CircleClose, Promotion, Paperclip } from '@element-plus/icons-vue'
import { getOpp } from '@/api/sales'
import { unwrapList } from '@/utils/response'
import { get, post as postApi, put as putApi, del as delApi } from '@/utils/request'
import StageRecordDialog from './components/StageRecordDialog.vue'
import {
  STAGE_OPTIONS, stageLabel, stageTagType, probabilityColor,
  formatMoney, formatDate, isClosed,
} from './types'

interface OpportunityDetail {
  id: number | string
  opp_no?: string
  name?: string
  customer?: { id?: number; name?: string }
  stage?: string
  probability?: number
  estimated_amount?: number
  type?: string
  sales?: { id?: number; name?: string }
  presale?: { id?: number; name?: string }
  expected_sign_date?: string
  notes?: string
  [k: string]: unknown
}

interface StageRecord {
  id: number
  stage: string
  data: Record<string, unknown>
  note?: string
  entered_at: string
  entered_by?: { id: number; name?: string }
  next_assignee_id?: number | null
  next_assignee_name?: string | null
  next_due_at?: string | null
  [k: string]: unknown
}

interface StageField {
  type: 'text' | 'textarea' | 'number' | 'date' | 'select' | 'user'
  label: string
  required: boolean
  options?: { value: string; label: string }[]
}

const route = useRoute()
const router = useRouter()
const oppId = computed(() => route.params.id as string)

const opp = ref<OpportunityDetail | null>(null)
const records = ref<StageRecord[]>([])
const stageSchema = ref<Record<string, Record<string, StageField>>>({})
const submitting = ref(false)
const creatingProject = ref(false)

// 各阶段文件标签 (用于展示)
interface FileTag { id: number; short: string; mime_type: string | null }
const stageFileTags = ref<Record<string, FileTag[]>>({})
const loadStageFiles = async () => {
  const result: Record<string, FileTag[]> = {}
  await Promise.all(STAGE_OPTIONS.map(async (s) => {
    try {
      const r = await get(`/sales/opps/${oppId.value}/stage-files`, { stage: s.value })
      const resp = r as unknown as { data?: StageRecordFile[] }
      result[s.value] = (resp?.data || []).map((f) => ({
        id: f.id,
        short: f.original_name.length > 8 ? f.original_name.slice(0, 8) + '…' : f.original_name,
        mime_type: f.mime_type,
      }))
    } catch { result[s.value] = [] }
  }))
  stageFileTags.value = result
}

interface StageRecordFile { id: number; original_name: string; mime_type: string | null; [k: string]: unknown }
const tagType = (mime?: string | null): 'primary' | 'success' | 'warning' | 'danger' | 'info' => {
  if (!mime) return 'info'
  if (mime.startsWith('image/')) return 'success'
  if (mime.includes('pdf')) return 'danger'
  if (mime.includes('cad') || mime.includes('dwg') || mime.includes('dxf')) return 'warning'
  return 'info'
}

// 对话框状态
const showStageDialog = ref(false)
const activeStage = ref<string>('inquiry')
const editingRecord = ref<StageRecord | null>(null)

const STAGE_ORDER = STAGE_OPTIONS.map((s) => s.value)
// 主轴 6 段：成交和战败是平行终态，单独分叉显示，不参与顺序比较
const MAIN_STAGES = STAGE_OPTIONS.filter((s) => s.value !== 'won' && s.value !== 'lost')

const currentStageIdx = computed(() => {
  if (!opp.value?.stage) return -1
  return MAIN_STAGES.findIndex((s) => s.value === opp.value!.stage)
})

const isStagePassed = (s: string): boolean => {
  // 主轴 6 段按顺序比较
  if (s === 'won' || s === 'lost') return false
  const idx = MAIN_STAGES.findIndex((ms) => ms.value === s)
  return idx !== -1 && idx <= currentStageIdx.value
}

const getLatestRecord = (s: string) =>
  records.value.filter((r) => r.stage === s).sort((a, b) =>
    new Date(b.entered_at).getTime() - new Date(a.entered_at).getTime())[0] || null

const getRecordsByStage = (s: string) =>
  records.value
    .filter((r) => r.stage === s)
    .sort((a, b) => new Date(b.entered_at).getTime() - new Date(a.entered_at).getTime())

const summarizeData = (stage: string, data: Record<string, unknown>): string => {
  if (!data) return '-'
  const schema = stageSchema.value[stage] || {}
  return Object.entries(data)
    .filter(([, v]) => v !== null && v !== '' && v !== undefined)
    .slice(0, 3)
    .map(([k, v]) => `${schema[k]?.label || k}: ${v}`)
    .join(' | ') || '-'
}

const loadOpp = async () => {
  try {
    const r = await getOpp(oppId.value)
    opp.value = (r as unknown as { data?: OpportunityDetail })?.data || (r as unknown as OpportunityDetail)
  } catch {
    ElMessage.error('加载商机失败')
  }
}

const loadRecords = async () => {
  try {
    const r = await get(`/sales/opps/${oppId.value}/stage-records`)
    const resp = r as unknown as {
      data?: StageRecord[] | { data?: StageRecord[]; stage_schema?: Record<string, Record<string, StageField>> }
      stage_schema?: Record<string, Record<string, StageField>>
    }
    const dataField = resp.data
    if (Array.isArray(dataField)) {
      records.value = dataField
      if (resp.stage_schema) stageSchema.value = resp.stage_schema
    } else if (dataField && typeof dataField === 'object') {
      records.value = dataField.data || []
      if (dataField.stage_schema) stageSchema.value = dataField.stage_schema
    }
  } catch {
    records.value = []
  }
}

const openStageDialog = (stage: string | null, record: StageRecord | null = null) => {
  // 没传 stage 时默认用商机当前阶段
  activeStage.value = stage || opp.value?.stage || 'inquiry'
  editingRecord.value = record
  showStageDialog.value = true
}

const handleSaveRecord = async (payload: Record<string, unknown>) => {
  submitting.value = true
  try {
    if (editingRecord.value) {
      await putApi(`/sales/opps/${oppId.value}/stage-records/${editingRecord.value.id}`, payload)
      ElMessage.success('已更新')
    } else {
      await postApi(`/sales/opps/${oppId.value}/stage-records`, payload)
      ElMessage.success('录入成功')
    }
    showStageDialog.value = false
    await Promise.all([loadOpp(), loadRecords()])
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存失败'
    ElMessage.error(msg)
  } finally {
    submitting.value = false
  }
}

const handleDeleteRecord = async (row: StageRecord) => {
  try {
    await ElMessageBox.confirm('确认删除此阶段流转记录？', '删除', {
      type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消',
    })
  } catch { return }
  try {
    await delApi(`/sales/opps/${oppId.value}/stage-records/${row.id}`)
    ElMessage.success('已删除')
    await loadRecords()
  } catch {
    ElMessage.error('删除失败')
  }
}

/** 一键转新项目：跳转创建页并预填商机信息 */
const handleConvertToProject = () => {
  const params = new URLSearchParams()
  if (opp.value?.id) params.set('from_opp_id', String(opp.value.id))
  if (opp.value?.customer_id) params.set('customer_id', String(opp.value.customer_id))
  if (opp.value?.customer?.id) params.set('customer_id', String(opp.value.customer.id))
  if (opp.value?.name) params.set('opp_name', opp.value.name)
  if (opp.value?.sales_id) params.set('manager_id', String(opp.value.sales_id))
  if (opp.value?.presale_id) params.set('presale_id', String(opp.value.presale_id))
  router.push(`/project/create?${params.toString()}`)
}

/** 后端创建项目并跳转详情 */
const handleCreateProject = async () => {
  creatingProject.value = true
  try {
    const r = await postApi(`/sales/opps/${oppId.value}/convert-to-project`)
    const resp = r as unknown as { data?: { project_id?: number; name?: string; project_no?: string } }
    const projectId = resp?.data?.project_id
    if (projectId) {
      ElMessage.success(`项目「${resp.data.name || ''}」创建成功`)
      router.push(`/project/detail/${projectId}`)
    } else {
      ElMessage.error('创建项目失败')
    }
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || '创建项目失败'
    ElMessage.error(msg)
  } finally {
    creatingProject.value = false
  }
}


const formatDateTime = (s?: string) => {
  if (!s) return '-'
  return new Date(s).toLocaleString('zh-CN', { hour12: false })
}

watch(oppId, () => { loadOpp(); loadRecords(); loadStageFiles() }, { immediate: false })
onMounted(() => { loadOpp(); loadRecords(); loadStageFiles() })
</script>

<style lang="scss" scoped>
.opp-detail-page { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .title-area { display: flex; align-items: center; gap: 10px; }
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; }
}
.info-card, .stage-timeline-card, .stages-records {
  background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.card-title { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 16px; padding-left: 8px; border-left: 3px solid #0C447C; }
.stage-flow { display: flex; align-items: flex-start; overflow-x: auto; padding: 8px 4px 16px; }
.stage-node {
  position: relative; flex: 1; min-width: 140px; display: flex; flex-direction: column;
  align-items: center; gap: 6px; padding: 0 4px;
  .stage-circle {
    width: 36px; height: 36px; border-radius: 50%; background: #fff; border: 2px solid #dcdfe6;
    display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; color: #909399;
    z-index: 1; background: #fff; transition: all 0.2s;
  }
  .stage-info { text-align: center; min-height: 56px; }
  .stage-label { font-size: 13px; font-weight: 600; color: #606266; }
  .stage-meta { display: flex; flex-direction: column; align-items: center; gap: 2px; margin-top: 4px; font-size: 11px; color: #909399; }
  .meta-time { font-size: 11px; }
  .meta-empty { font-size: 11px; color: #c0c4cc; font-style: italic; }
  .stage-line {
    position: absolute; top: 18px; left: calc(50% + 22px); right: calc(-50% + 22px);
    height: 2px; background: #e4e7ed;
  }
  &.is-passed {
    .stage-circle { background: var(--stage-color); border-color: var(--stage-color); color: #fff; }
    .stage-label { color: var(--stage-color); }
    .stage-line { background: var(--stage-color); }
  }
  &.is-current {
    .stage-circle {
      background: var(--stage-color); border-color: var(--stage-color); color: #fff;
      box-shadow: 0 0 0 4px rgba(12, 68, 124, 0.15); transform: scale(1.1);
    }
    .stage-label { color: var(--stage-color); font-weight: 700; }
  }
}
.stages-records { padding: 20px; }
.stage-section { margin-bottom: 20px; }
.stage-section-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 12px; margin-bottom: 8px; border-left: 3px solid;
  background: #f5f7fa; border-radius: 4px;
}
.stage-section-title { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.muted { color: #c0c4cc; font-size: 12px; }
.file-tags { display: flex; flex-wrap: wrap; gap: 2px; }
.card-title-hint { font-size: 11px; color: #909399; font-weight: normal; margin-left: 12px; }

/* 分叉区域：从已报价分叉到成交/战败 */
.fork-section {
  position: relative; margin-top: 12px; padding-top: 20px; border-top: 1px dashed #e4e7ed;
}
.fork-line-from-quoted {
  position: absolute; top: 0; left: calc(83.33% / 6 * 5 + 10%); /* 指向已报价节点 */
  width: 2px; height: 20px; background: #dcdfe6;
}
.fork-nodes {
  display: flex; justify-content: center; align-items: stretch; gap: 48px; padding: 16px 0 8px;
}
.fork-node {
  flex: 0 0 280px; display: flex; flex-direction: column; align-items: center;
  padding: 20px 16px; border-radius: 12px; border: 2px solid #e4e7ed; background: #fafafa;
  transition: all 0.2s;
  &.fork-won {
    &.is-current { border-color: #67C23A; background: #f0f9eb; box-shadow: 0 0 0 4px rgba(103,194,58,0.12); }
    .fork-circle { background: #67C23A; }
  }
  &.fork-lost {
    &.is-current { border-color: #F56C6C; background: #fef0f0; box-shadow: 0 0 0 4px rgba(245,108,108,0.12); }
    .fork-circle { background: #F56C6C; }
  }
}
.fork-circle {
  width: 48px; height: 48px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; margin-bottom: 8px;
}
.fork-label { font-size: 16px; font-weight: 700; color: #303133; margin-bottom: 4px; }
.fork-meta { text-align: center; min-height: 40px; font-size: 12px; color: #606266; }
.fork-divider {
  width: 2px; background: #dcdfe6; align-self: stretch;
  position: relative;
  &::before { content: '或'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    background: #fff; padding: 4px 8px; font-size: 12px; color: #909399; }
}
.meta-info { margin-top: 4px; font-size: 12px; color: #606266; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>