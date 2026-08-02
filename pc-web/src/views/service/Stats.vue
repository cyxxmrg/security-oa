<template>
  <div class="page-container">
    <div class="page-header">
      <h2>服务统计</h2>
    </div>
    <div class="stat-cards" v-loading="loading">
      <div class="stat-card" v-for="card in statCards" :key="card.label">
        <div class="stat-value" :style="{ color: card.color }">{{ card.value }}</div>
        <div class="stat-label">{{ card.label }}</div>
        <div class="stat-trend" :class="card.trendDir">{{ card.trend }}</div>
      </div>
    </div>
    <div class="content-card">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="本月工单概览" name="overview">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="本月工单总数">{{ stats?.totalOrders ?? 0 }}</el-descriptions-item>
            <el-descriptions-item label="本月完成工单">{{ stats?.completedOrders ?? 0 }}</el-descriptions-item>
            <el-descriptions-item label="SLA 达标率">
              <span :style="{ color: (stats?.slaRate ?? 100) >= 95 ? '#1D9E75' : (stats?.slaRate ?? 100) >= 85 ? '#BA7517' : '#A32D2D', fontWeight: 600 }">
                {{ stats?.slaRate ?? 100 }}%
              </span>
            </el-descriptions-item>
            <el-descriptions-item label="平均响应时间">
              {{ Math.round(Number(stats?.avgResponse ?? 0)) }} 分钟
            </el-descriptions-item>
            <el-descriptions-item label="平均客户评分" :span="2">
              <el-rate v-model="avgRating" disabled :max="5" show-score :show-text="false" />
            </el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>
        <el-tab-pane label="说明" name="note">
          <div class="note-block">
            <p>本页面数据来自 <code>/api/service/stats</code> 端点（本月数据）。</p>
            <p>更详细的「按设备类型故障率」「按维修人员工单量」统计需要额外的报表端点，已列入后续需求。</p>
          </div>
        </el-tab-pane>
      </el-tabs>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { get } from '@/utils/request'

const loading = ref(false)
const activeTab = ref('overview')
const stats = ref<Record<string, unknown> | null>(null)

const avgRating = computed(() => Number(stats.value?.avgRating ?? 0))

const statCards = computed(() => {
  const s = stats.value || {}
  return [
    { label: '本月工单',     value: String(s.totalOrders ?? '-'),     color: '#0C447C', trend: `已完成 ${s.completedOrders ?? 0}`, trendDir: 'info' },
    { label: 'SLA 达标率',   value: `${s.slaRate ?? 100}%`,            color: '#1D9E75', trend: '本月数据', trendDir: 'info' },
    { label: '平均响应时间', value: `${Math.round(Number(s.avgResponse ?? 0))} 分钟`, color: '#BA7517', trend: '本月数据', trendDir: 'info' },
    { label: '客户满意度',   value: `${Number(s.avgRating ?? 0).toFixed(1)}/5`, color: '#534AB7', trend: '本月平均', trendDir: 'info' },
  ]
})

async function loadStats() {
  loading.value = true
  try {
    const res = await get('/service/stats')
    stats.value = res.data || res
  } catch (e) {
    console.error('[loadStats]', e)
    stats.value = null
  } finally {
    loading.value = false
  }
}

onMounted(loadStats)
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header {
  margin-bottom: 16px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
}
.stat-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}
.stat-card {
  background: #fff; border-radius: 8px; padding: 20px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  text-align: center;
}
.stat-value { font-size: 28px; font-weight: 700; margin-bottom: 6px; }
.stat-label { font-size: 14px; color: #666; margin-bottom: 4px; }
.stat-trend { font-size: 12px; }
.content-card {
  background: #fff; border-radius: 8px; padding: 16px 24px 24px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.note-block p {
  line-height: 1.8; color: #606266;
  code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; color: #0C447C; }
}
</style>
