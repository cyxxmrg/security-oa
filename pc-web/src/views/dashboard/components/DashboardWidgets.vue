<template>
  <div class="widgets-grid">
    <!-- 1. 维修方式饼图 -->
    <el-card shadow="never" class="widget-card" v-loading="loading">
      <template #header>
        <div class="w-header">
          <span>🔧 维修方式分布 ({{ days }}d)</span>
          <el-tag size="small" :type="methods.length ? 'success' : 'info'">{{ totalCount }} 单</el-tag>
        </div>
      </template>
      <div v-if="!methods.length" class="empty">暂无数据</div>
      <div v-else>
        <div v-for="(m, i) in methods" :key="m.code" class="method-row">
          <el-tag :type="methodColor(m.code)" size="small" class="method-tag">
            {{ methodLabel(m.code) }}
          </el-tag>
          <div class="method-bar">
            <div class="method-fill" :style="{ width: m.pct + '%', background: methodHex(m.code) }" />
          </div>
          <span class="method-count">{{ m.count }} 单 ({{ m.pct }}%)</span>
        </div>
      </div>
    </el-card>

    <!-- 2. P50/P90 维修周期 -->
    <el-card shadow="never" class="widget-card" v-loading="loading">
      <template #header>
        <div class="w-header">
          <span>⏱️ 维修周期 ({{ days }}d)</span>
          <el-tag size="small" :type="cycle.available ? 'success' : 'info'">
            {{ cycle.sample_count || 0 }} 样本
          </el-tag>
        </div>
      </template>
      <div v-if="!cycle.available" class="empty">样本不足</div>
      <div v-else class="cycle-grid">
        <div class="cycle-cell p50">
          <div class="cycle-num">{{ cycle.p50_days?.toFixed(1) }}<small>天</small></div>
          <div class="cycle-label">P50 (中位数)</div>
        </div>
        <div class="cycle-cell p90">
          <div class="cycle-num">{{ cycle.p90_days?.toFixed(1) }}<small>天</small></div>
          <div class="cycle-label">P90 (90% 单据)</div>
        </div>
        <div class="cycle-cell max">
          <div class="cycle-num">{{ cycle.max_days?.toFixed(0) }}<small>天</small></div>
          <div class="cycle-label">最慢</div>
        </div>
      </div>
    </el-card>

    <!-- 3. 返修原因 Top 5 -->
    <el-card shadow="never" class="widget-card" v-loading="loading">
      <template #header>
        <div class="w-header">
          <span>⚠️ 返修原因 Top 5 (30d)</span>
          <el-tag size="small" type="warning">{{ faultTop.length }} 项</el-tag>
        </div>
      </template>
      <div v-if="!faultTop.length" class="empty">暂无数据</div>
      <div v-else class="fault-list">
        <div v-for="(f, i) in faultTop" :key="f.code" class="fault-row">
          <div class="fault-rank" :class="`rank-${i}`">{{ i + 1 }}</div>
          <div class="fault-info">
            <div class="fault-label">{{ f.label }}</div>
            <el-progress :percentage="f.percentage" :stroke-width="8" :show-text="false" />
          </div>
          <div class="fault-count">
            <span class="num">{{ f.count }}</span>
            <span class="unit">单</span>
          </div>
        </div>
      </div>
    </el-card>

    <!-- 4. 工程师效率 Top 5 -->
    <el-card shadow="never" class="widget-card" v-loading="loading">
      <template #header>
        <div class="w-header">
          <span>👷 工程师效率 Top 5 (30d)</span>
          <el-tag size="small" type="primary">{{ techRank.length }} 人</el-tag>
        </div>
      </template>
      <div v-if="!techRank.length" class="empty">暂无数据</div>
      <div v-else class="tech-list">
        <div v-for="(t, i) in techRank" :key="t.user_id" class="tech-row">
          <div class="tech-rank" :class="`rank-${i}`">{{ i + 1 }}</div>
          <el-avatar :size="32" class="tech-avatar">{{ t.name?.charAt(0) }}</el-avatar>
          <div class="tech-info">
            <div class="tech-name">{{ t.name }}</div>
            <div class="tech-stats">
              <span>⏱ {{ t.avg_days?.toFixed(1) }}天/单</span>
              <span>💰 ¥{{ fmt(t.total_revenue) }}</span>
            </div>
          </div>
          <div class="tech-count">
            <span class="num">{{ t.completed_count }}</span>
            <span class="unit">单</span>
          </div>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { get } from '@/utils/request'
import { unwrapStats } from '@/utils/response'

const props = defineProps<{ days?: number }>()
const days = props.days || 90

const loading = ref(false)
const methodData = ref<Record<string, number>>({})
const cycle = ref<Record<string, unknown>>({})
const faultTop = ref<Record<string, unknown>[]>([])
const techRank = ref<Record<string, unknown>[]>([])

const totalCount = computed(() => Object.values(methodData.value).reduce((a, b) => a + b, 0))

const methods = computed(() => {
  const entries = Object.entries(methodData.value)
  const total = totalCount.value
  return entries
    .map(([code, count]) => ({
      code,
      count,
      pct: total > 0 ? Math.round(count / total * 100) : 0,
    }))
    .sort((a, b) => b.count - a.count)
})

const methodLabel = (c: string) => ({
  free_warranty: '免费保修', free_contract: '免费合同',
  paid_repair: '付费维修', paid_replace: '付费换件',
  returned: '退回', unspecified: '未指定',
}[c] || c)

const methodColor = (c: string) => ({
  free_warranty: 'success', free_contract: 'success',
  paid_repair: 'warning', paid_replace: 'warning',
  returned: 'info', unspecified: 'info',
}[c] as Record<string, unknown> || 'info')

const methodHex = (c: string) => ({
  free_warranty: '#67C23A', free_contract: '#67C23A',
  paid_repair: '#E6A23C', paid_replace: '#E6A23C',
  returned: '#909399', unspecified: '#909399',
}[c] || '#909399')

const fmt = (n: number | undefined) => {
  if (!n) return '0'
  if (n >= 10000) return (n / 10000).toFixed(1) + '万'
  return n.toFixed(0)
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await get(`/dashboard/widget/all?days=${days}`)
    // V0.6.3: request.ts 不解包
    const d = unwrapStats<Record<string, unknown>>(res)
    methodData.value = d.method_distribution || {}
    cycle.value = d.cycle_percentile || {}
    faultTop.value = d.fault_top || []
    techRank.value = d.technician_ranking || []
  } catch (e) {
    methodData.value = {}; cycle.value = {}; faultTop.value = []; techRank.value = []
  } finally { loading.value = false }
}

onMounted(loadData)
</script>

<style scoped lang="scss">
.widgets-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

@media (max-width: 768px) {
  .widgets-grid { grid-template-columns: 1fr; }
}

.widget-card { border-radius: 12px; border: none; }
:deep(.el-card__header) { padding: 12px 16px; background: #fafbfc; }
.w-header { display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; }

.empty { text-align: center; color: #909399; padding: 32px 0; font-size: 13px; }

/* 维修方式条 */
.method-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.method-tag { min-width: 70px; justify-content: center; }
.method-bar { flex: 1; height: 14px; background: #f0f2f5; border-radius: 7px; overflow: hidden; }
.method-fill { height: 100%; transition: width 0.4s; }
.method-count { font-size: 12px; color: #606266; min-width: 90px; text-align: right; }

/* P50/P90 */
.cycle-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.cycle-cell { text-align: center; padding: 12px; border-radius: 8px; }
.cycle-cell.p50 { background: #ECF5FF; color: #409EFF; }
.cycle-cell.p90 { background: #FDF6EC; color: #E6A23C; }
.cycle-cell.max { background: #FEF0F0; color: #F56C6C; }
.cycle-num { font-size: 24px; font-weight: 700; }
.cycle-num small { font-size: 12px; margin-left: 2px; font-weight: 400; }
.cycle-label { font-size: 11px; color: #909399; margin-top: 2px; }

/* 返修原因 */
.fault-list { display: flex; flex-direction: column; gap: 10px; }
.fault-row { display: flex; align-items: center; gap: 10px; }
.fault-rank { width: 22px; height: 22px; border-radius: 50%; background: #909399; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.fault-rank.rank-0 { background: #F56C6C; }
.fault-rank.rank-1 { background: #E6A23C; }
.fault-rank.rank-2 { background: #67C23A; }
.fault-info { flex: 1; }
.fault-label { font-size: 13px; font-weight: 500; margin-bottom: 4px; }
.fault-count { min-width: 60px; text-align: right; }
.fault-count .num { font-size: 18px; font-weight: 700; color: #303133; }
.fault-count .unit { font-size: 11px; color: #909399; margin-left: 2px; }

/* 工程师 */
.tech-list { display: flex; flex-direction: column; gap: 8px; }
.tech-row { display: flex; align-items: center; gap: 10px; padding: 6px 0; }
.tech-rank { width: 22px; height: 22px; border-radius: 50%; background: #909399; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.tech-rank.rank-0 { background: #F56C6C; }
.tech-rank.rank-1 { background: #E6A23C; }
.tech-rank.rank-2 { background: #67C23A; }
.tech-avatar { background: #409EFF; color: #fff; flex-shrink: 0; }
.tech-info { flex: 1; }
.tech-name { font-size: 13px; font-weight: 600; color: #303133; }
.tech-stats { font-size: 11px; color: #909399; margin-top: 2px; }
.tech-stats span { margin-right: 8px; }
.tech-count { min-width: 50px; text-align: right; }
.tech-count .num { font-size: 18px; font-weight: 700; color: #409EFF; }
.tech-count .unit { font-size: 11px; color: #909399; margin-left: 2px; }
</style>
