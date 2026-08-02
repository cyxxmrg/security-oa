<template>
  <div v-if="visible" class="maintenance-card" :class="`severity-${severity}`">
    <div class="card-left">
      <div class="card-icon">🛠️</div>
      <div class="card-body">
        <div class="card-title">维修中心</div>
        <div class="card-desc">{{ descText }}</div>
      </div>
    </div>
    <div class="card-right">
      <div class="metric-row">
        <div class="metric">
          <div class="metric-num">{{ data.work_orders?.in_progress || 0 }}</div>
          <div class="metric-label">进行中工单</div>
        </div>
        <div class="metric">
          <div class="metric-num">{{ data.repair_orders?.in_repair || 0 }}</div>
          <div class="metric-label">在修</div>
        </div>
        <div class="metric">
          <div class="metric-num">{{ data.repair_orders?.repaired || 0 }}</div>
          <div class="metric-label">待寄回</div>
        </div>
        <div class="metric highlight">
          <div class="metric-num">{{ data.work_orders?.conv_rate || 0 }}%</div>
          <div class="metric-label">本周转返修率</div>
        </div>
        <div class="metric">
          <div class="metric-num">{{ data.repair_orders?.avg_cycle_days || 0 }}天</div>
          <div class="metric-label">平均周期</div>
        </div>
        <!-- V0.5.7 块4 — 维修成本卡片 -->
        <div class="metric highlight-cost">
          <div class="metric-num">¥{{ fmtCost(data.cost?.this_month_total) }}</div>
          <div class="metric-label">本月售后成本</div>
        </div>
        <div class="metric">
          <div class="metric-num">{{ data.cost?.cost_ratio_pct || 0 }}%</div>
          <div class="metric-label">占合同比</div>
        </div>
      </div>
      <el-button size="small" type="primary" @click="goMaintenance">进入看板 →</el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { get } from '@/utils/request'

const router = useRouter()
const data = ref<Record<string, unknown>>({})
const loaded = ref(false)

const loadData = async () => {
  try {
    const res = await get('/dashboard/maintenance-stats')
    data.value = res.data || {}
  } catch { data.value = {} }
  loaded.value = true
}

const visible = computed(() => {
  if (!loaded.value) return false
  // 没数据 + 0 进行中 → 隐藏
  return (data.value.work_orders?.in_progress || 0) > 0
    || (data.value.repair_orders?.in_repair || 0) > 0
    || (data.value.repair_orders?.repaired || 0) > 0
})

const severity = computed(() => {
  const conv = data.value.work_orders?.conv_rate || 0
  if (conv >= 30) return 'danger'
  if (conv >= 15) return 'warning'
  return 'info'
})

const descText = computed(() => {
  const wo = data.value.work_orders || {}
  const ro = data.value.repair_orders || {}
  const parts: string[] = []
  if (wo.in_progress) parts.push(`${wo.in_progress} 个工单进行中`)
  if (ro.in_repair) parts.push(`${ro.in_repair} 件在修`)
  if (ro.repaired) parts.push(`${ro.repaired} 件待寄回`)
  if (wo.conv_rate) parts.push(`本周转返修率 ${wo.conv_rate}%`)
  return parts.join(' · ') || '暂无活跃单据'
})

const goMaintenance = () => router.push('/maintenance/kanban')

const fmtCost = (n: number | undefined) => {
  if (!n) return '0'
  if (n >= 10000) return (n / 10000).toFixed(1) + '万'
  return n.toFixed(0)
}

onMounted(() => loadData())
</script>

<style scoped lang="scss">
.maintenance-card {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-radius: 8px; margin-bottom: 16px;
  background: #F4F4F5; border-left: 4px solid #909399;
}
.maintenance-card.severity-info { background: #ECF5FF; border-left-color: #409EFF; }
.maintenance-card.severity-warning { background: #FDF6EC; border-left-color: #E6A23C; }
.maintenance-card.severity-danger { background: #FEF0F0; border-left-color: #F56C6C; }

.card-left { display: flex; align-items: center; gap: 12px; flex: 1; }
.card-icon { font-size: 28px; }
.card-title { font-size: 15px; font-weight: 600; color: #303133; }
.card-desc { font-size: 12px; color: #606266; margin-top: 4px; }

.card-right { display: flex; align-items: center; gap: 16px; }
.metric-row { display: flex; gap: 16px; }
.metric { text-align: center; min-width: 60px; }
.metric-num { font-size: 18px; font-weight: 700; color: #303133; }
.metric-label { font-size: 11px; color: #909399; margin-top: 2px; }
.metric.highlight .metric-num { color: #F56C6C; }
.metric.highlight-cost .metric-num { color: #E6A23C; font-size: 17px; }

@media (max-width: 768px) {
  .maintenance-card { flex-direction: column; align-items: stretch; }
  .card-right { margin-top: 12px; flex-wrap: wrap; }
  .metric-row { gap: 8px; flex-wrap: wrap; }
}
</style>
