<template>
  <div>
    <div class="filter-bar">
      <el-date-picker
        v-model="statsDateRangeModel"
        type="monthrange"
        range-separator="至"
        start-placeholder="开始月份"
        end-placeholder="结束月份"
        value-format="YYYY-MM"
        @change="$emit('load-stats')"
        style="width: 280px"
      />
      <el-button @click="$emit('reset-date')">全部</el-button>
      <el-button type="primary" :icon="Refresh" @click="$emit('load-stats')">刷新</el-button>
    </div>

    <!-- 统计 KPI 卡片 -->
    <el-row :gutter="16" class="mb-16">
      <el-col :span="6">
        <el-card shadow="hover" class="kpi-card">
          <div class="kpi-label">报销总单数</div>
          <div class="kpi-value primary">{{ statsData.totalCount }}</div>
          <div class="kpi-sub">期间合计</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="kpi-card">
          <div class="kpi-label">报销总金额</div>
          <div class="kpi-value success">¥{{ formatMoney(statsData.totalAmount) }}</div>
          <div class="kpi-sub">已付款: ¥{{ formatMoney(statsData.paidAmount) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="kpi-card">
          <div class="kpi-label">已审批</div>
          <div class="kpi-value warning">{{ statsData.approvedCount }}</div>
          <div class="kpi-sub">金额: ¥{{ formatMoney(statsData.approvedAmount) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="kpi-card">
          <div class="kpi-label">待审批</div>
          <div class="kpi-value danger">{{ statsData.pendingCount }}</div>
          <div class="kpi-sub">金额: ¥{{ formatMoney(statsData.pendingAmount) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 员工月度报销排行 -->
    <el-row :gutter="16">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header>
            <div class="card-header">
              <span>📊 员工月度报销金额排行</span>
              <el-radio-group v-model="statsGroupByModel" size="small" @change="$emit('load-stats')">
                <el-radio-button value="user">按员工</el-radio-button>
                <el-radio-button value="category">按费用类别</el-radio-button>
                <el-radio-button value="project">按项目</el-radio-button>
              </el-radio-group>
            </div>
          </template>
          <el-table v-loading="statsLoading" :data="statsGroup" stripe border style="width: 100%">
            <el-table-column type="index" label="排名" width="80" align="center">
              <template #default="{ $index }">
                <el-tag :type="$index < 3 ? 'danger' : 'info'" effect="dark" size="small">
                  #{{ $index + 1 }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="statsGroupBy === 'user' ? '员工' : statsGroupBy === 'category' ? '费用类别' : '项目'" min-width="180">
              <template #default="{ row }">
                <strong>{{ row.name }}</strong>
              </template>
            </el-table-column>
            <el-table-column label="报销单数" width="120" align="center">
              <template #default="{ row }">{{ row.count }}</template>
            </el-table-column>
            <el-table-column label="报销金额" width="180" align="right">
              <template #default="{ row }">
                <span class="amount-bold">¥ {{ formatMoney(row.amount) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="占比" width="120" align="center">
              <template #default="{ row }">
                <el-progress :percentage="Math.round((row.amount / Math.max(statsData.totalAmount, 1)) * 100)" :stroke-width="10" />
              </template>
            </el-table-column>
            <el-table-column label="月均金额" width="180" align="right">
              <template #default="{ row }">
                ¥ {{ formatMoney(row.amount / Math.max(row.months, 1)) }}
              </template>
            </el-table-column>
          </el-table>
          <el-empty v-if="!statsLoading && statsGroup.length === 0" description="该时间段暂无报销记录" :image-size="80" />
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { Refresh } from '@element-plus/icons-vue'

defineProps<{
  statsLoading: boolean
  statsData: { totalCount: number; totalAmount: number; paidAmount: number; approvedCount: number; approvedAmount: number; pendingCount: number; pendingAmount: number }
  statsGroup: Array<{ name: string; count: number; amount: number; months: number }>
  statsGroupBy: 'user' | 'category' | 'project'
  formatMoney: (v: number | string | null | undefined) => string
}>()

defineEmits<{
  'load-stats': []
  'reset-date': []
}>()

const statsDateRangeModel = defineModel<string[]>('statsDateRange', { default: () => [] })
const statsGroupByModel = defineModel<'user' | 'category' | 'project'>('statsGroupByModel', { default: 'user' })
</script>

<style scoped>
.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding: 16px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  flex-wrap: wrap;
}
.mb-16 { margin-bottom: 16px; }
.kpi-card { .kpi-label { font-size: 13px; color: #909399; margin-bottom: 8px; } .kpi-value { font-size: 26px; font-weight: 700; line-height: 1.2; &.primary { color: #0C447C; } &.success { color: #67c23a; } &.warning { color: #e6a23c; } &.danger { color: #f56c6c; } } .kpi-sub { font-size: 12px; color: #909399; margin-top: 4px; } }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.amount-bold { color: #f56c6c; font-weight: 600; }
</style>
