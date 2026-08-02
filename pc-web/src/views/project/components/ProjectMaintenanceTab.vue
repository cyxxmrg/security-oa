<template>
  <div>
    <div v-if="maintenanceLoading" class="loading-state">加载中...</div>
    <div v-else>
      <!-- 阶段校验提示 -->
      <el-alert
        v-if="!maintenanceData?.can_create_maintenance?.allowed"
        :title="maintenanceData?.can_create_maintenance?.reason"
        type="warning"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <!-- 4 个统计卡 -->
      <div class="m-stats">
        <div class="m-stat">
          <div class="m-num">{{ maintenanceData?.stats?.work_order_count || 0 }}</div>
          <div class="m-label">维修工单</div>
        </div>
        <div class="m-stat">
          <div class="m-num">{{ maintenanceData?.stats?.repair_order_count || 0 }}</div>
          <div class="m-label">返修单</div>
        </div>
        <div class="m-stat">
          <div class="m-num">{{ maintenanceData?.stats?.in_repair_count || 0 }}</div>
          <div class="m-label">在修中</div>
        </div>
        <div class="m-stat">
          <div class="m-num">¥{{ (maintenanceData?.stats?.total_cost || 0).toFixed(0) }}</div>
          <div class="m-label">售后总成本</div>
        </div>
      </div>

      <!-- 操作按钮 (受阶段限制) -->
      <div class="m-actions">
        <el-button
          :disabled="!maintenanceData?.can_create_maintenance?.allowed"
          type="primary"
          :icon="Plus"
          @click="goCreateWorkOrder"
        >
          新建维修工单
        </el-button>
        <el-button
          :disabled="!maintenanceData?.can_create_maintenance?.allowed"
          :icon="Box"
          @click="goCreateRepair"
        >
          新建返修单
        </el-button>
        <el-tag v-if="!maintenanceData?.can_create_maintenance?.allowed" type="info" size="small">
          需进入「结算/质保」阶段
        </el-tag>
      </div>

      <!-- 列表 -->
      <div v-if="!maintenanceData?.items?.length" class="m-empty">
        暂无售后记录
      </div>
      <div v-else class="m-list">
        <div v-for="item in maintenanceData.items" :key="`${item.type}-${item.id}`" class="m-card" @click="goMaintenanceItem(item)">
          <div class="m-card-top">
            <span class="m-code">{{ item.code }}</span>
            <el-tag v-if="item.type === 'work_order'" size="small" type="primary">工单</el-tag>
            <el-tag v-else size="small" type="warning">返修</el-tag>
            <el-tag v-if="item.source_type === 'work_order'" size="small" effect="plain" type="info">
              来自 {{ item.source_code }}
            </el-tag>
          </div>
          <div class="m-fault">{{ item.fault_description }}</div>
          <div class="m-foot">
            <el-tag :type="STATUS_TAG[item.status]?.type || 'info'" size="small">
              {{ STATUS_TAG[item.status]?.label || item.status }}
            </el-tag>
            <span v-if="item.method_type" class="m-method">{{ METHOD_LABEL[item.method_type] || item.method_type }}</span>
            <span class="m-time">{{ formatDate(item.created_at || item.updated_at || item.received_at) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { get } from '@/utils/request'
import { Plus, Box } from '@element-plus/icons-vue'

const props = defineProps<{
  projectId: number
  activeTab: string
}>()

const emit = defineEmits<{
  loaded: [data: Record<string, unknown>]
}>()

const router = useRouter()
const maintenanceData = ref<Record<string, unknown> | null>(null)
const maintenanceLoading = ref(false)

const STATUS_TAG: Record<string, { type: string; label: string }> = {
  pending:    { type: 'info',    label: '待派单' },
  assigned:   { type: 'primary', label: '已派单' },
  in_progress:{ type: 'warning', label: '进行中' },
  resolved:   { type: 'success', label: '已解决' },
  cancelled:  { type: 'info',    label: '已取消' },
  converted_to_repair: { type: 'danger', label: '🔁 已转返修' },
  received:       { type: 'info',    label: '已接件' },
  sent_for_repair:{ type: 'primary', label: '寄修中' },
  in_repair:      { type: 'warning', label: '维修中' },
  repaired:       { type: 'success', label: '已修好' },
  sent_back:      { type: 'warning', label: '寄回中' },
  closed:         { type: 'success', label: '已关闭' },
}
const METHOD_LABEL: Record<string, string> = {
  free_warranty: '🆓 保内',
  free_contract: '🆓 合同',
  paid_repair:   '💰 付费维修',
  paid_replace:  '💰 付费换新',
  returned:      '↩️ 退回',
}

const formatDate = (s?: string) => {
  if (!s) return ''
  const d = new Date(s)
  return `${d.getMonth() + 1}-${d.getDate()}`
}

const loadMaintenance = async () => {
  if (!props.projectId) return
  maintenanceLoading.value = true
  try {
    const res = await get(`/projects/${props.projectId}/maintenance`)
    // V0.6.3: res = {code, data: <maintenance>}
    maintenanceData.value = res?.data ?? null
    emit('loaded', maintenanceData.value)
  } catch { maintenanceData.value = null }
  finally { maintenanceLoading.value = false }
}

const goCreateWorkOrder = () => {
  router.push({ path: '/maintenance/work-orders/create', query: { project_id: props.projectId } })
}
const goCreateRepair = () => {
  router.push({ path: '/maintenance/repairs/create', query: { project_id: props.projectId } })
}
const goMaintenanceItem = (item: Record<string, unknown>) => {
  if (item.type === 'work_order') router.push(`/maintenance/work-orders/${item.id}`)
  else router.push(`/maintenance/repairs/${item.id}`)
}

// 监听 activeTab 变化, 切到 maintenance 时才加载
watch(() => props.activeTab, (v) => {
  if (v === 'maintenance' && !maintenanceData.value) loadMaintenance()
})
</script>

<style lang="scss" scoped>
.m-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.m-stat { background: #fff; padding: 16px; border-radius: 6px; text-align: center; border-top: 3px solid #409EFF; }
.m-stat:nth-child(2) { border-top-color: #E6A23C; }
.m-stat:nth-child(3) { border-top-color: #F56C6C; }
.m-stat:nth-child(4) { border-top-color: #67C23A; }
.m-num { font-size: 22px; font-weight: 700; }
.m-label { font-size: 12px; color: #909399; margin-top: 4px; }
.m-actions { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.m-empty { padding: 60px; text-align: center; color: #C0C4CC; font-size: 13px; background: #fff; border-radius: 6px; }
.m-list { display: flex; flex-direction: column; gap: 8px; }
.m-card { background: #fff; padding: 12px 16px; border-radius: 6px; cursor: pointer; transition: all 0.15s; border-left: 3px solid transparent; }
.m-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left-color: #409EFF; transform: translateX(2px); }
.m-card-top { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.m-code { font-size: 12px; color: #409EFF; font-family: monospace; font-weight: 500; }
.m-fault { font-size: 13px; color: #303133; line-height: 1.4; margin-bottom: 6px; }
.m-foot { display: flex; align-items: center; gap: 12px; font-size: 11px; color: #909399; }
.m-method { color: #E6A23C; }
.m-time { margin-left: auto; }
.loading-state { padding: 60px; text-align: center; color: #909399; }
@media (max-width: 768px) { .m-stats { grid-template-columns: repeat(2, 1fr); } }
</style>
