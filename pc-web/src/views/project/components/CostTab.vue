<template>
  <div class="tab-content">
    <h3 class="section-title">成本核算</h3>

    <!-- 4 个核心 KPI -->
    <el-row :gutter="16" class="kpi-row">
      <el-col :span="6">
        <div class="kpi-card">
          <div class="kpi-label">合同总额</div>
          <div class="kpi-value">¥ {{ formatWan(totalContract) }}</div>
          <div class="kpi-sub">万元</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="kpi-card">
          <div class="kpi-label">预算总额</div>
          <div class="kpi-value">¥ {{ formatWan(totalBudgetYuan) }}</div>
          <div class="kpi-sub">万元</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="kpi-card">
          <div class="kpi-label">预估利润</div>
          <div class="kpi-value" :style="{ color: estimatedProfit > 0 ? '#1D9E75' : '#A32D2D' }">
            ¥ {{ formatWan(estimatedProfit) }}
          </div>
          <div class="kpi-sub">万元</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="kpi-card">
          <div class="kpi-label">利润率</div>
          <div class="kpi-value" :style="{ color: parseFloat(profitRate) >= 20 ? '#1D9E75' : parseFloat(profitRate) >= 10 ? '#BA7517' : '#A32D2D' }">
            {{ profitRate }}%
          </div>
          <div class="kpi-sub">预估</div>
        </div>
      </el-col>
    </el-row>

    <!-- 物料领用统计 -->
    <el-row :gutter="16" class="kpi-row" style="margin-top: 12px">
      <el-col :span="8">
        <div class="kpi-card">
          <div class="kpi-label">物料到位率</div>
          <div class="kpi-value" :style="{ color: fulfillRate >= 80 ? '#1D9E75' : fulfillRate >= 50 ? '#BA7517' : '#A32D2D' }">
            {{ fulfillRate }}%
          </div>
          <div class="kpi-sub">采购计划完成度</div>
        </div>
      </el-col>
      <el-col :span="8">
        <div class="kpi-card">
          <div class="kpi-label">物料领用笔数</div>
          <div class="kpi-value">{{ materialStats.issued_records || 0 }}</div>
          <div class="kpi-sub">次</div>
        </div>
      </el-col>
      <el-col :span="8">
        <div class="kpi-card">
          <div class="kpi-label">物料领用金额</div>
          <div class="kpi-value">¥ {{ formatMoney(materialStats.issued_cost) }}</div>
          <div class="kpi-sub">元</div>
        </div>
      </el-col>
    </el-row>

    <!-- 预算 vs 实际 -->
    <h4 class="sub-title">预算 vs 实际 (按类别)</h4>
    <el-table :data="budgetVsActual" border>
      <el-table-column type="index" label="#" width="55" align="center" />
      <el-table-column prop="category" label="费用类别" min-width="160" />
      <el-table-column prop="budget" label="预算(元)" width="140" align="right">
        <template #default="{ row }">¥ {{ formatMoney(row.budget) }}</template>
      </el-table-column>
      <el-table-column prop="actual" label="物料领用(元)" width="140" align="right">
        <template #default="{ row }">
          <span :style="{ color: row.actual > row.budget ? '#A32D2D' : '#303133' }">
            ¥ {{ formatMoney(row.actual) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="diff" label="差异(元)" width="140" align="right">
        <template #default="{ row }">
          <span :style="{ color: row.diff < 0 ? '#A32D2D' : '#1D9E75' }">
            ¥ {{ formatMoney(row.diff) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="rate" label="执行率" min-width="240">
        <template #default="{ row }">
          <el-progress
            :percentage="Math.min(100, Math.round(row.rate))"
            :color="getProgressColor(row.rate)"
            :stroke-width="10"
          />
          <span style="margin-left: 8px; font-size: 12px; color: #909399">{{ row.hint }}</span>
        </template>
      </el-table-column>
    </el-table>

    <el-empty v-if="budgetVsActual.length === 0" description="暂无成本数据" :image-size="60" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  type Project, type MaterialStats, type PurchaseStats,
  computeTotalBudgetYuan,
} from '../types'

const props = defineProps<{
  project: Project
  materialStats: MaterialStats
  purchaseStats: PurchaseStats
  totalContract: number
  paidAmount: number
}>()

const totalBudgetYuan = computed(() => computeTotalBudgetYuan(props.project))
const estimatedProfit = computed(() => Math.max(0, props.totalContract - totalBudgetYuan.value))
const profitRate = computed(() => {
  if (props.totalContract <= 0) return '0.0'
  return ((estimatedProfit.value / props.totalContract) * 100).toFixed(1)
})
const fulfillRate = computed(() => Number(props.purchaseStats.fulfill_rate) || 0)

const budgetVsActual = computed(() => {
  const b = props.project
  const materialActual = Number(props.materialStats.issued_cost) || 0
  const cats = [
    { category: '设备费', budget: Number(b.budget_device) || 0, actual: 0, hint: '主要在采购计划中体现' },
    { category: '材料费', budget: Number(b.budget_material) || 0, actual: materialActual, hint: '来自物料领用' },
    { category: '人工费', budget: Number(b.budget_labor) || 0, actual: 0, hint: '尚未自动归集' },
    { category: '外包费', budget: Number(b.budget_outsource) || 0, actual: 0, hint: '尚未自动归集' },
    { category: '其他费', budget: Number(b.budget_other) || 0, actual: 0, hint: '尚未自动归集' },
  ]
  return cats.map(c => {
    const diff = c.budget - c.actual
    const rate = c.budget > 0 ? (c.actual / c.budget) * 100 : 0
    return { ...c, diff, rate }
  })
})

const formatMoney = (n: number) => (Number(n) || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const formatWan = (n: number) => (Number(n) / 10000).toFixed(2)
const getProgressColor = (rate: number) => {
  if (rate > 100) return '#A32D2D'
  if (rate >= 80) return '#1D9E75'
  if (rate >= 50) return '#185FA5'
  return '#BA7517'
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
  margin: 24px 0 12px 0;
}
.kpi-row {
  margin-bottom: 0;
}
.kpi-card {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 16px 20px;
  position: relative;
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
</style>
