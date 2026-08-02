<template>
  <div class="page-wrap">
    <div class="page-header">
      <h2 class="page-title">售后成本报表</h2>
      <div class="page-filters">
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          size="default"
          style="width: 260px"
        />
        <el-button type="primary" @click="loadAll">查询</el-button>
        <el-button @click="resetFilters">重置</el-button>
      </div>
    </div>

    <!-- KPI 概览卡 -->
    <el-row :gutter="16" class="kpi-row">
      <el-col :xs="12" :sm="6">
        <div class="kpi-card">
          <div class="kpi-label">已完成单数</div>
          <div class="kpi-value">{{ overview.completed_orders || 0 }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6">
        <div class="kpi-card primary">
          <div class="kpi-label">总成本</div>
          <div class="kpi-value">¥{{ fmt(overview.total_cost) }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6">
        <div class="kpi-card success">
          <div class="kpi-label">免费/质保</div>
          <div class="kpi-value">¥{{ fmt(overview.warranty_cost) }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6">
        <div class="kpi-card warning">
          <div class="kpi-label">付费维修</div>
          <div class="kpi-value">¥{{ fmt(overview.paid_cost) }}</div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="kpi-row">
      <el-col :xs="12" :sm="8">
        <div class="kpi-card-sm">
          <span class="kpi-label-sm">配件成本</span>
          <span class="kpi-value-sm">¥{{ fmt(overview.total_parts_cost) }}</span>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8">
        <div class="kpi-card-sm">
          <span class="kpi-label-sm">人工成本</span>
          <span class="kpi-value-sm">¥{{ fmt(overview.total_labor_cost) }}</span>
        </div>
      </el-col>
      <el-col :xs="12" :sm="8">
        <div class="kpi-card-sm">
          <span class="kpi-label-sm">运费</span>
          <span class="kpi-value-sm">¥{{ fmt(overview.total_shipping_cost) }}</span>
        </div>
      </el-col>
    </el-row>

    <!-- 4 Tab 维度 -->
    <el-tabs v-model="activeTab" class="cost-tabs">
      <!-- Tab 1: 月度 -->
      <el-tab-pane label="月度趋势" name="month">
        <el-table :data="byMonth" border stripe size="default">
          <el-table-column prop="month" label="月份" width="120" />
          <el-table-column prop="orders_count" label="单数" width="100" align="right" />
          <el-table-column label="配件成本" min-width="140" align="right">
            <template #default="{ row }">¥{{ fmt(row.parts_cost) }}</template>
          </el-table-column>
          <el-table-column label="人工成本" min-width="140" align="right">
            <template #default="{ row }">¥{{ fmt(row.labor_cost) }}</template>
          </el-table-column>
          <el-table-column label="运费" min-width="120" align="right">
            <template #default="{ row }">¥{{ fmt(row.shipping_cost) }}</template>
          </el-table-column>
          <el-table-column label="合计" min-width="160" align="right">
            <template #default="{ row }">
              <span class="total-cell">¥{{ fmt(row.total_cost) }}</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab 2: 项目 -->
      <el-tab-pane label="按项目" name="project">
        <el-table :data="byProject" border stripe size="default">
          <el-table-column label="项目" min-width="240">
            <template #default="{ row }">
              <span v-if="row.project_id">
                {{ row.project_code }} {{ row.project_name }}
              </span>
              <el-tag v-else type="info" size="small">未关联</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="orders_count" label="单数" width="100" align="right" />
          <el-table-column label="总成本" min-width="160" align="right">
            <template #default="{ row }">
              <span class="total-cell">¥{{ fmt(row.total_cost) }}</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab 3: 客户 -->
      <el-tab-pane label="按客户" name="customer">
        <el-table :data="byCustomer" border stripe size="default">
          <el-table-column label="客户" min-width="180">
            <template #default="{ row }">
              <span v-if="row.customer_id">{{ row.customer_name }}</span>
              <el-tag v-else type="info" size="small">未关联</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="orders_count" label="单数" width="100" align="right" />
          <el-table-column label="免费/质保" min-width="140" align="right">
            <template #default="{ row }">¥{{ fmt(row.warranty_cost) }}</template>
          </el-table-column>
          <el-table-column label="付费" min-width="140" align="right">
            <template #default="{ row }">¥{{ fmt(row.paid_cost) }}</template>
          </el-table-column>
          <el-table-column label="合计" min-width="160" align="right">
            <template #default="{ row }">
              <span class="total-cell">¥{{ fmt(row.total_cost) }}</span>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- Tab 4: 维修方式 -->
      <el-tab-pane label="按维修方式" name="method">
        <el-table :data="byMethod" border stripe size="default">
          <el-table-column label="维修方式" min-width="160">
            <template #default="{ row }">
              <el-tag :type="methodTagType(row.method_type)" size="small">
                {{ methodLabel(row.method_type) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="orders_count" label="单数" width="100" align="right" />
          <el-table-column label="总成本" min-width="160" align="right">
            <template #default="{ row }">¥{{ fmt(row.total_cost) }}</template>
          </el-table-column>
          <el-table-column label="占比" min-width="220">
            <template #default="{ row }">
              <el-progress
                :percentage="row.percentage"
                :stroke-width="14"
                :show-text="true"
                :color="methodColor(row.method_type)"
              />
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { get } from '@/utils/request'
import type { RepairCostOverview, RepairCostMonth, RepairCostProject, RepairCostCustomer, RepairCostMethod } from './types'

const activeTab = ref('month')
const dateRange = ref<[string, string] | null>(null)

const overview = ref<RepairCostOverview>({})
const byMonth = ref<RepairCostMonth[]>([])
const byProject = ref<RepairCostProject[]>([])
const byCustomer = ref<RepairCostCustomer[]>([])
const byMethod = ref<RepairCostMethod[]>([])

const fmt = (n: number | undefined | null | string): string => {
  if (n == null) return '0.00'
  return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

const buildQuery = (): string => {
  const params = new URLSearchParams()
  if (dateRange.value) {
    params.set('from', dateRange.value[0])
    params.set('to', dateRange.value[1])
  }
  const s = params.toString()
  return s ? '?' + s : ''
}

const loadOverview  = async () => { try { const r = await get('/repair-cost/overview'  + buildQuery()); overview.value  = r?.data ?? {} } catch { overview.value = {} } }
const loadMonth     = async () => { try { const r = await get('/repair-cost/by-month'   + buildQuery()); byMonth.value    = r?.data ?? [] } catch { byMonth.value = [] } }
const loadProject   = async () => { try { const r = await get('/repair-cost/by-project' + buildQuery()); byProject.value  = r?.data ?? [] } catch { byProject.value = [] } }
const loadCustomer  = async () => { try { const r = await get('/repair-cost/by-customer'+ buildQuery()); byCustomer.value = r?.data ?? [] } catch { byCustomer.value = [] } }
const loadMethod    = async () => { try { const r = await get('/repair-cost/by-method'  + buildQuery()); byMethod.value   = r?.data ?? [] } catch { byMethod.value = [] } }

const loadAll = async () => {
  await Promise.all([loadOverview(), loadMonth(), loadProject(), loadCustomer(), loadMethod()])
}

const resetFilters = () => {
  dateRange.value = null
  loadAll()
}

const methodLabel = (t: string) => ({
  free_warranty: '免费保修',
  free_contract: '免费合同',
  paid_repair:   '付费维修',
  paid_replace:  '付费换件',
  returned:      '退回',
  unspecified:   '未指定',
}[t] || t)

const methodTagType = (t: string): 'success' | 'warning' | 'danger' | 'info' => {
  if (t === 'free_warranty' || t === 'free_contract') return 'success'
  if (t === 'paid_repair' || t === 'paid_replace') return 'warning'
  if (t === 'returned') return 'info'
  return 'info'
}

const methodColor = (t: string): string => {
  if (t === 'free_warranty' || t === 'free_contract') return '#67C23A'
  if (t === 'paid_repair' || t === 'paid_replace') return '#E6A23C'
  return '#909399'
}

onMounted(loadAll)
</script>

<style scoped lang="scss">
.page-wrap { padding: 16px; background: #F4F4F5; min-height: 100vh; }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 16px;
  flex-wrap: wrap; gap: 12px;
}
.page-title { font-size: 18px; font-weight: 600; margin: 0; color: #303133; }
.page-filters { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

.kpi-row { margin-bottom: 16px; }
.kpi-card {
  background: #fff; border-radius: 8px; padding: 20px;
  border-left: 4px solid #909399;
}
.kpi-card.primary { border-left-color: #409EFF; }
.kpi-card.success { border-left-color: #67C23A; }
.kpi-card.warning { border-left-color: #E6A23C; }
.kpi-label { font-size: 13px; color: #909399; margin-bottom: 8px; }
.kpi-value { font-size: 24px; font-weight: 700; color: #303133; }

.kpi-card-sm {
  background: #fff; border-radius: 8px; padding: 12px 20px;
  display: flex; justify-content: space-between; align-items: center;
}
.kpi-label-sm { font-size: 13px; color: #909399; }
.kpi-value-sm { font-size: 18px; font-weight: 600; color: #303133; }

.cost-tabs { background: #fff; border-radius: 8px; padding: 16px 20px; }
.total-cell { color: #F56C6C; font-weight: 600; }

:deep(.el-tabs__content) { padding-top: 12px; }
</style>
