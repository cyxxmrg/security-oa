<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">维修统计</span>
      <el-select v-model="days" style="width: 140px" @change="loadData">
        <el-option label="最近 7 天" :value="7" />
        <el-option label="最近 30 天" :value="30" />
        <el-option label="最近 90 天" :value="90" />
        <el-option label="最近 365 天" :value="365" />
      </el-select>
    </div>

    <!-- 工单统计 -->
    <h3 class="section-title">工单统计</h3>
    <div class="stats-row">
      <div class="stat-card stat-primary">
        <div class="stat-num">{{ woStats?.total || 0 }}</div>
        <div class="stat-label">总工单</div>
      </div>
      <div class="stat-card stat-warning">
        <div class="stat-num">{{ woStats?.converted_to_repair || 0 }}</div>
        <div class="stat-label">转返修</div>
        <div class="stat-sub">转化率 {{ woStats?.conversion_rate || 0 }}%</div>
      </div>
      <div class="stat-card stat-info">
        <div class="stat-num">{{ woStats?.by_status?.in_progress || 0 }}</div>
        <div class="stat-label">进行中</div>
      </div>
      <div class="stat-card stat-success">
        <div class="stat-num">{{ woStats?.by_status?.resolved || 0 }}</div>
        <div class="stat-label">已解决</div>
      </div>
    </div>

    <!-- 优先级分布 -->
    <h3 class="section-title">优先级分布</h3>
    <div class="priority-row">
      <div v-for="(count, key) in woStats?.by_priority || {}" :key="key" class="priority-card" :class="`pri-${key}`">
        <div class="pri-num">{{ count }}</div>
        <div class="pri-label">{{ priorityLabel(key) }}</div>
      </div>
    </div>

    <!-- 返修统计 -->
    <h3 class="section-title">返修统计</h3>
    <div class="stats-row">
      <div class="stat-card stat-primary">
        <div class="stat-num">{{ roStats?.total || 0 }}</div>
        <div class="stat-label">总返修</div>
      </div>
      <div class="stat-card stat-success">
        <div class="stat-num">{{ roStats?.closed || 0 }}</div>
        <div class="stat-label">已关闭</div>
        <div class="stat-sub">完成率 {{ roStats?.close_rate || 0 }}%</div>
      </div>
      <div class="stat-card stat-warning">
        <div class="stat-num">¥{{ roStats?.total_cost || 0 }}</div>
        <div class="stat-label">总费用</div>
      </div>
      <div class="stat-card stat-info">
        <div class="stat-num">{{ roStats?.avg_cycle_days || 0 }}</div>
        <div class="stat-label">平均周期 (天)</div>
      </div>
    </div>

    <!-- 维修方式分布 -->
    <h3 class="section-title">维修方式分布</h3>
    <div class="method-row">
      <div v-for="(count, key) in roStats?.by_method || {}" :key="key" class="method-card" :class="`method-${key}`">
        <div class="method-num">{{ count }}</div>
        <div class="method-label">{{ methodLabel(key) }}</div>
      </div>
    </div>

    <!-- 返修状态分布 -->
    <h3 class="section-title">返修状态分布</h3>
    <div class="status-row">
      <div v-for="(count, key) in roStats?.by_status || {}" :key="key" class="status-card">
        <div class="status-num">{{ count }}</div>
        <div class="status-label">{{ roStatusLabel(key) }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { get } from '@/utils/request'

const days = ref(30)
const woStats = ref<Record<string, unknown> | null>(null)
const roStats = ref<Record<string, unknown> | null>(null)

const loadData = async () => {
  try {
    const [wo, ro] = await Promise.all([
      get('/work-orders/stats', { days: days.value }),
      get('/repair-orders/stats', { days: days.value }),
    ])
    // 解包后 res 本身是 stats 对象 {days, total, by_status, ...}
    woStats.value = wo?.data ?? wo
    roStats.value = ro?.data ?? ro
  } catch (e) { /* ignore */ }
}

const priorityLabel = (k: string) => ({ low: '低', medium: '中', high: '高', urgent: '紧急' }[k] || k)
const methodLabel = (k: string) => ({ free_warranty: '🆓 免费（保内）', free_contract: '🆓 免费（合同）', paid_repair: '💰 付费（维修）', paid_replace: '💰 付费（换新）', returned: '↩️ 退回' }[k] || k)
const roStatusLabel = (k: string) => ({ received: '已接件', sent_for_repair: '寄修中', in_repair: '维修中', repaired: '已修好', sent_back: '寄回中', closed: '已关闭', cancelled: '已取消' }[k] || k)

onMounted(() => loadData())
</script>

<style scoped lang="scss">
.page-container { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.page-title { font-size: 20px; font-weight: 600; }
.section-title { font-size: 16px; font-weight: 600; margin: 24px 0 12px; color: #303133; }

.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px; }
.stat-card { background: #fff; padding: 20px; border-radius: 8px; text-align: center; border-top: 3px solid #409EFF; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.stat-card.stat-warning { border-top-color: #E6A23C; }
.stat-card.stat-info { border-top-color: #909399; }
.stat-card.stat-success { border-top-color: #67C23A; }
.stat-num { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: #606266; margin-top: 4px; }
.stat-sub { font-size: 11px; color: #909399; margin-top: 2px; }

.priority-row, .method-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 16px; }
.priority-card, .method-card, .status-card { background: #fff; padding: 16px; border-radius: 8px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.priority-card.pri-urgent { border-top: 3px solid #F56C6C; }
.priority-card.pri-high { border-top: 3px solid #E6A23C; }
.priority-card.pri-medium { border-top: 3px solid #409EFF; }
.priority-card.pri-low { border-top: 3px solid #909399; }
.pri-num, .method-num, .status-num { font-size: 24px; font-weight: 700; }
.pri-label, .method-label, .status-label { font-size: 12px; color: #606266; margin-top: 4px; }

.method-row { grid-template-columns: repeat(5, 1fr); }
.status-row { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }

@media (max-width: 768px) {
  .stats-row, .priority-row, .method-row { grid-template-columns: repeat(2, 1fr); }
  .status-row { grid-template-columns: repeat(3, 1fr); }
}
</style>
