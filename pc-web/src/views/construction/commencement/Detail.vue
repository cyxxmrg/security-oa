<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <el-button :icon="ArrowLeft" plain @click="goBack">返回</el-button>
        <span class="page-title">开工单详情</span>
      </div>
      <div class="header-actions">
        <el-button v-if="order" :icon="Printer" @click="handlePrint">打印</el-button>
      </div>
    </div>

    <div v-loading="loading">
      <template v-if="order">
        <!-- 状态条 -->
        <el-card shadow="never" class="status-card">
          <div class="status-bar">
            <div class="status-info">
              <span class="status-label">状态：</span>
              <el-tag :type="statusTagType(order.status)" effect="plain" size="large">
                {{ statusLabel(order.status) }}
              </el-tag>
            </div>
            <el-steps :active="stepIndex(order.status)" finish-status="success" align-center class="status-steps">
              <el-step title="草稿" />
              <el-step title="已审批" />
              <el-step title="施工中" />
              <el-step title="已完工" />
            </el-steps>
          </div>
        </el-card>

        <!-- 基础信息 -->
        <el-card shadow="never" class="info-card">
          <template #header><span class="card-title">基础信息</span></template>
          <el-descriptions :column="3" border size="default">
            <el-descriptions-item label="开工编号">{{ order.code || '-' }}</el-descriptions-item>
            <el-descriptions-item label="项目">
              {{ order.project?.name || order.project?.code || order.project_id || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="团队">
              {{ order.team?.name || order.team_id || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="计划开工">{{ order.planned_start || '-' }}</el-descriptions-item>
            <el-descriptions-item label="计划完工">{{ order.planned_end || '-' }}</el-descriptions-item>
            <el-descriptions-item label="工期">
              {{ durationDays }} 天
            </el-descriptions-item>
            <el-descriptions-item label="工人数量">{{ order.worker_count || 0 }} 人</el-descriptions-item>
            <el-descriptions-item label="工时预估">{{ order.estimated_hours || 0 }} 人时</el-descriptions-item>
            <el-descriptions-item label="实际工时">{{ order.actual_hours || 0 }} 人时</el-descriptions-item>
            <el-descriptions-item label="施工内容" :span="3">
              <div style="white-space: pre-wrap">{{ order.work_scope || '-' }}</div>
            </el-descriptions-item>
            <el-descriptions-item v-if="order.remark" label="备注" :span="3">{{ order.remark }}</el-descriptions-item>
            <el-descriptions-item label="创建人">{{ order.creator?.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ order.created_at || '-' }}</el-descriptions-item>
            <el-descriptions-item label="更新时间">{{ order.updated_at || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>

        <!-- 关联日志 -->
        <el-card shadow="never" class="info-card">
          <template #header><span class="card-title">关联日志（{{ logs.length }}）</span></template>
          <el-table :data="logs" v-loading="logsLoading" border size="small" stripe>
            <el-table-column prop="date" label="日期" width="120" align="center" />
            <el-table-column prop="weather" label="天气" width="80" align="center" />
            <el-table-column label="工序" min-width="160" show-overflow-tooltip>
              <template #default="{ row }">
                {{ row.process_name || row.process || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="worker_count" label="工人" width="80" align="center" />
            <el-table-column label="进度" width="120" align="center">
              <template #default="{ row }">
                <el-progress :percentage="row.progress || 0" :stroke-width="10" style="width: 100px" />
              </template>
            </el-table-column>
            <el-table-column prop="remark" label="备注" min-width="200" show-overflow-tooltip />
          </el-table>
          <el-empty v-if="!logsLoading && logs.length === 0" description="暂无关联日志" :image-size="80" />
        </el-card>
      </template>
      <el-empty v-else-if="!loading" description="未找到开工单数据" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, Printer } from '@element-plus/icons-vue'
import { commencementApi, logApi } from '@/api/construction'
import { unwrapList, unwrapItem } from '@/utils/response'
import { printTable } from '@/utils/exporter'
import type { Commencement, LogRow } from '../types'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const order = ref<Commencement | null>(null)
const logs = ref<LogRow[]>([])
const logsLoading = ref(false)

const statusMap: Record<string, { label: string; tagType: string; step: number }> = {
  draft:       { label: '草稿',     tagType: 'info',    step: 0 },
  approved:    { label: '已审批',   tagType: 'success', step: 1 },
  in_progress: { label: '施工中',   tagType: 'warning', step: 2 },
  completed:   { label: '已完工',   tagType: '',        step: 3 },
  cancelled:   { label: '已取消',   tagType: 'danger',  step: 0 },
}
const statusLabel = (s: string) => statusMap[s]?.label || s || '-'
const statusTagType = (s: string): string => statusMap[s]?.tagType || 'info'
const stepIndex = (s: string) => statusMap[s]?.step ?? 0

const orderId = computed(() => Number(route.params.id))

const durationDays = computed(() => {
  const o = order.value
  if (!o?.planned_start || !o?.planned_end) return 0
  const d1 = new Date(o.planned_start).getTime()
  const d2 = new Date(o.planned_end).getTime()
  if (Number.isNaN(d1) || Number.isNaN(d2)) return 0
  return Math.max(0, Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1)
})

const loadDetail = async () => {
  if (!orderId.value) return
  loading.value = true
  try {
    const res = await commencementApi.show(orderId.value)
    order.value = unwrapItem(res) || null
    await loadLogs()
  } catch {
    order.value = null
  } finally {
    loading.value = false
  }
}

const loadLogs = async () => {
  if (!orderId.value) return
  logsLoading.value = true
  try {
    const res = await logApi.list({ commencement_id: orderId.value, per_page: 100 })
    logs.value = unwrapList(res)
  } catch {
    logs.value = []
  } finally {
    logsLoading.value = false
  }
}

const goBack = () => router.push('/construction/commencement')
const handlePrint = () => {
  if (!order.value) {
    ElMessage.warning('开工单未加载')
    return
  }
  const o = order.value
  const headers = ['字段', '内容']
  const rows = [
    ['开工单号', o.code || '-'],
    ['关联项目', o.project?.name || '-'],
    ['施工队', o.team?.name || '-'],
    ['开工日期', o.commencement_date?.slice(0, 10) || '-'],
    ['计划完工', o.planned_end_date?.slice(0, 10) || '-'],
    ['工作内容', o.work_content || '-'],
    ['工作地点', o.work_location || '-'],
    ['安全要求', o.safety_requirements || '-'],
    ['状态', o.status || '-'],
    ['备注', o.remarks || '-'],
  ]
  printTable(`开工单 - ${o.code || o.id || ''}`, headers, rows, { orientation: 'portrait' })
}

watch(orderId, () => { if (orderId.value) loadDetail() })
onMounted(() => { if (orderId.value) loadDetail() })
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
}
.status-card, .info-card { margin-bottom: 12px; }
.card-title { font-weight: 600; color: #303133; }
.status-bar { display: flex; flex-direction: column; gap: 12px; }
.status-info { display: flex; align-items: center; gap: 8px; }
.status-label { color: #909399; }
.status-steps { margin-top: 4px; }
</style>
