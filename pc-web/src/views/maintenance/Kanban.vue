<template>
  <div class="page-container kanban-page">
    <div class="page-header">
      <span class="page-title">维修中心 · 看板</span>
      <div class="header-actions">
        <el-radio-group v-model="view" size="default">
          <el-radio-button value="all">全部</el-radio-button>
          <el-radio-button value="work_orders">仅工单</el-radio-button>
          <el-radio-button value="repairs">仅返修</el-radio-button>
        </el-radio-group>
        <el-button @click="loadData" :icon="Refresh">刷新</el-button>
      </div>
    </div>

    <div class="kanban-grid" :class="`view-${view}`">
      <div v-show="view !== 'repairs'" class="kanban-col">
        <div class="col-header">
          <span class="col-title">🛠️ 维修工单</span>
          <span class="col-count">{{ woRows.length }} 条</span>
        </div>
        <div class="kanban-cols-inner">
          <div v-for="col in WO_COLUMNS" :key="col.key" class="kanban-list">
            <div class="list-header" :class="`hdr-${col.key}`">
              <span>{{ col.label }}</span>
              <el-badge :value="woByStatus[col.key]?.length || 0" :max="99" type="primary" />
            </div>
            <div class="list-body">
              <div v-for="wo in woByStatus[col.key] || []" :key="wo.id" class="kanban-card" @click="goWorkOrder(wo.id)">
                <div class="card-top">
                  <span class="card-code">{{ wo.code }}</span>
                  <el-tag v-if="wo.priority" :type="PRIORITY_TAG[wo.priority]?.type" size="small">
                    {{ PRIORITY_TAG[wo.priority]?.label || wo.priority }}
                  </el-tag>
                </div>
                <div class="card-eq">{{ wo.equipment_brand }} {{ wo.equipment_model }}</div>
                <div class="card-fault">{{ wo.fault_description?.slice(0, 50) }}{{ wo.fault_description?.length > 50 ? '…' : '' }}</div>
                <div class="card-foot">
                  <span class="cust">{{ wo.customer_name || '—' }}</span>
                  <span class="time">{{ formatRelative(wo.created_at) }}</span>
                </div>
                <div v-if="wo.status === 'converted_to_repair'" class="card-flag">🔁 已转返修 #{{ wo.converted_to_repair_code }}</div>
              </div>
              <div v-if="!woByStatus[col.key]?.length" class="empty-col">—</div>
            </div>
          </div>
        </div>
      </div>

      <div v-show="view !== 'work_orders'" class="kanban-col">
        <div class="col-header">
          <span class="col-title">📦 返修管理</span>
          <span class="col-count">{{ roRows.length }} 条</span>
        </div>
        <div class="kanban-cols-inner">
          <div v-for="col in RO_COLUMNS" :key="col.key" class="kanban-list">
            <div class="list-header" :class="`hdr-${col.key}`">
              <span>{{ col.label }}</span>
              <el-badge :value="roByStatus[col.key]?.length || 0" :max="99" type="primary" />
            </div>
            <div class="list-body">
              <div v-for="ro in roByStatus[col.key] || []" :key="ro.id" class="kanban-card" @click="goRepair(ro.id)">
                <div class="card-top">
                  <span class="card-code">{{ ro.code }}</span>
                  <el-tag v-if="ro.method_type" :type="METHOD_TAG[ro.method_type]?.type" size="small">
                    {{ METHOD_TAG[ro.method_type]?.label }}
                  </el-tag>
                </div>
                <div class="card-eq">{{ ro.equipment_brand }} {{ ro.equipment_model }}</div>
                <div class="card-source">
                  <span v-if="ro.source_type === 'work_order'">🛠️ {{ ro.source_code }}</span>
                  <span v-else>📞 客户送修</span>
                </div>
                <div class="card-foot">
                  <span class="cust">{{ ro.customer_name || '—' }}</span>
                  <span class="time">{{ formatRelative(ro.received_at) }}</span>
                </div>
              </div>
              <div v-if="!roByStatus[col.key]?.length" class="empty-col">—</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Refresh } from '@element-plus/icons-vue'
import { get } from '@/utils/request'

const router = useRouter()
const view = ref<'all' | 'work_orders' | 'repairs'>('all')

const WO_COLUMNS = [
  { key: 'pending', label: '待派单' },
  { key: 'assigned', label: '已派单' },
  { key: 'in_progress', label: '进行中' },
  { key: 'resolved', label: '已解决' },
  { key: 'converted_to_repair', label: '🔁 已转返修' },
  { key: 'cancelled', label: '已取消' },
]

const RO_COLUMNS = [
  { key: 'received', label: '已接件' },
  { key: 'sent_for_repair', label: '寄修中' },
  { key: 'in_repair', label: '维修中' },
  { key: 'repaired', label: '已修好' },
  { key: 'sent_back', label: '寄回中' },
  { key: 'closed', label: '已关闭' },
  { key: 'cancelled', label: '已取消' },
]

const PRIORITY_TAG: Record<string, { type: string; label: string }> = {
  urgent: { type: 'danger', label: '紧急' },
  high:   { type: 'warning', label: '高' },
  medium: { type: 'primary', label: '中' },
  low:    { type: 'info', label: '低' },
}

const METHOD_TAG: Record<string, { type: string; label: string }> = {
  free_warranty: { type: 'success', label: '🆓 保内' },
  free_contract: { type: 'success', label: '🆓 合同' },
  paid_repair:   { type: 'warning', label: '💰 维修' },
  paid_replace:  { type: 'warning', label: '💰 换新' },
  returned:      { type: 'info', label: '↩️ 退回' },
}

const woRows = ref<Record<string, unknown>[]>([])
const roRows = ref<Record<string, unknown>[]>([])

const woByStatus = computed(() => {
  const map: Record<string, Record<string, unknown>[]> = {}
  for (const wo of woRows.value) (map[wo.status] = map[wo.status] || []).push(wo)
  return map
})

const roByStatus = computed(() => {
  const map: Record<string, Record<string, unknown>[]> = {}
  for (const ro of roRows.value) (map[ro.status] = map[ro.status] || []).push(ro)
  return map
})

const loadData = async () => {
  try {
    const [wo, ro] = await Promise.all([
      get('/work-orders', { per_page: 100 }),
      get('/repair-orders', { per_page: 100 }),
    ])
    woRows.value = (wo.data?.data || wo.data || [])
    roRows.value = (ro.data?.data || ro.data || [])
  } catch (e) { /* ignore */ }
}

const goWorkOrder = (id: number) => router.push(`/maintenance/work-orders/${id}`)
const goRepair = (id: number) => router.push(`/maintenance/repairs/${id}`)

const formatRelative = (s: string) => {
  if (!s) return ''
  const ms = Date.now() - new Date(s).getTime()
  const d = Math.floor(ms / 86400000)
  const h = Math.floor(ms / 3600000)
  if (d > 0) return `${d}天前`
  if (h > 0) return `${h}小时前`
  return `${Math.floor(ms / 60000)}分钟前`
}

onMounted(() => loadData())
</script>

<style scoped lang="scss">
.kanban-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-title { font-size: 20px; font-weight: 600; }
.header-actions { display: flex; gap: 12px; align-items: center; }

.kanban-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.kanban-grid.view-work_orders, .kanban-grid.view-repairs { grid-template-columns: 1fr; }

.kanban-col { background: #F5F7FA; border-radius: 8px; padding: 12px; min-height: 600px; }
.col-header { display: flex; justify-content: space-between; align-items: center; padding: 4px 8px 12px; border-bottom: 1px solid #E4E7ED; margin-bottom: 12px; }
.col-title { font-size: 16px; font-weight: 600; color: #303133; }
.col-count { font-size: 12px; color: #909399; }

.kanban-cols-inner { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 8px; }
.kanban-list { flex: 0 0 180px; background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.list-header { padding: 8px 10px; font-size: 12px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #EBEEF5; }
.list-body { padding: 6px; max-height: 540px; overflow-y: auto; }

.kanban-card { background: #fff; border: 1px solid #EBEEF5; border-radius: 6px; padding: 8px; margin-bottom: 6px; cursor: pointer; transition: all 0.15s; }
.kanban-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); transform: translateY(-1px); }
.card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.card-code { font-size: 11px; color: #409EFF; font-family: monospace; }
.card-eq { font-size: 12px; font-weight: 500; color: #303133; margin-bottom: 4px; }
.card-fault { font-size: 11px; color: #606266; line-height: 1.4; margin-bottom: 4px; }
.card-source { font-size: 11px; color: #67C23A; margin-bottom: 4px; }
.card-foot { display: flex; justify-content: space-between; font-size: 10px; color: #909399; }
.card-flag { font-size: 10px; color: #E6A23C; background: #FDF6EC; padding: 2px 4px; border-radius: 3px; margin-top: 4px; }
.empty-col { padding: 12px; text-align: center; color: #C0C4CC; font-size: 11px; }

.hdr-pending, .hdr-received { background: #F4F4F5; color: #909399; }
.hdr-assigned, .hdr-sent_for_repair { background: #ECF5FF; color: #409EFF; }
.hdr-in_progress, .hdr-in_repair { background: #FDF6EC; color: #E6A23C; }
.hdr-resolved, .hdr-repaired { background: #F0F9EB; color: #67C23A; }
.hdr-converted_to_repair, .hdr-sent_back { background: #FEF0F0; color: #F56C6C; }
.hdr-closed { background: #F0F9EB; color: #67C23A; }
.hdr-cancelled { background: #F4F4F5; color: #909399; }

@media (max-width: 1100px) {
  .kanban-grid { grid-template-columns: 1fr; }
}
</style>
