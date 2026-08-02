<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">客户总账</span>
      <el-button :icon="Refresh" plain @click="loadAll">刷新</el-button>
    </div>

    <div class="filter-bar">
      <el-form :inline="true">
        <el-form-item label="客户">
          <el-input v-model="filter.customer_id" placeholder="客户ID" clearable style="width:140px" />
        </el-form-item>
        <el-form-item label="项目">
          <el-input v-model="filter.project_id" placeholder="项目ID" clearable style="width:140px" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="filter.receivable_type" placeholder="全部" clearable style="width:140px">
            <el-option label="合同款" value="contract" />
            <el-option label="进度款" value="progress" />
            <el-option label="保留金" value="retention" />
            <el-option label="质保金" value="warranty" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filter.status" placeholder="全部" clearable style="width:120px">
            <el-option label="待收" value="pending" />
            <el-option label="部分收" value="partial" />
            <el-option label="已结清" value="paid" />
            <el-option label="逾期" value="overdue" />
          </el-select>
        </el-form-item>
        <el-form-item label="起">
          <el-date-picker v-model="filter.from" type="date" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="止">
          <el-date-picker v-model="filter.to" type="date" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="page = 1; loadAll()">查询</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-card shadow="never" class="summary-card">
      <el-row :gutter="16">
        <el-col :span="6">
          <div style="text-align:center">
            <div style="color:#999;font-size:12px">累计应收</div>
            <div style="font-size:22px;font-weight:700;color:#0C447C">¥{{ overview.summary?.total_amount || 0 }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div style="text-align:center">
            <div style="color:#999;font-size:12px">已收款</div>
            <div style="font-size:22px;font-weight:700;color:#67c23a">¥{{ overview.summary?.total_received || 0 }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div style="text-align:center">
            <div style="color:#999;font-size:12px">未收余额</div>
            <div style="font-size:22px;font-weight:700;color:#e6a23c">¥{{ overview.summary?.total_balance || 0 }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div style="text-align:center">
            <div style="color:#999;font-size:12px">应收单数</div>
            <div style="font-size:22px;font-weight:700">{{ overview.summary?.receivable_count || total }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <MonthlyTrendChart :data="monthlyData" kind="customer" />

    <AgingTable type="receivable" />

    <el-card shadow="never" style="margin-top: 16px">
      <template #header><span>应收明细</span></template>
      <el-table :data="items" border v-loading="loading">
        <el-table-column prop="customer.name" label="客户" min-width="180" />
        <el-table-column prop="project.name" label="项目" min-width="140" />
        <el-table-column prop="ref_no" label="单号" width="140" />
        <el-table-column prop="receivable_type" label="类型" width="90">
          <template #default="{ row }">
            <el-tag size="small" :type="typeTagType(row.receivable_type)">
              {{ typeLabel(row.receivable_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="amount" label="应收" width="100" align="right">
          <template #default="{ row }">¥{{ Number(row.amount).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column prop="received_amount" label="已收" width="100" align="right">
          <template #default="{ row }">¥{{ Number(row.received_amount).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column prop="balance" label="未收" width="100" align="right">
          <template #default="{ row }">
            <span style="color:#e6a23c;font-weight:600">¥{{ Number(row.balance).toLocaleString() }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="due_date" label="到期日" width="110" />
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          background
        />
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Plus, Search, Refresh } from '@element-plus/icons-vue'
import { ledger } from '@/api/ledger'
import { unwrapPaginate } from '@/utils/response'
import MonthlyTrendChart from './components/MonthlyTrendChart.vue'
import AgingTable from './components/AgingTable.vue'
import type { CustomerLedgerRow, LedgerOverview, MonthlyPoint } from './types'

const loading = ref(false)
const items = ref<CustomerLedgerRow[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const overview = ref<LedgerOverview>({ summary: { total_amount: 0, total_received: 0, total_balance: 0, receivable_count: 0 } })
const monthlyData = ref<MonthlyPoint[]>([])

const filter = reactive({
  customer_id: '' as string,
  project_id: '' as string,
  receivable_type: '',
  status: '',
  from: '',
  to: '',
})

const typeLabel = (t?: string): string => ({ contract: '合同', progress: '进度', retention: '保留金', warranty: '质保金' }[t ?? ''] ?? '-')
const typeTagType = (t?: string): 'primary' | 'success' | 'warning' | 'info' | '' => ({ contract: 'primary', progress: 'success', retention: 'warning', warranty: 'info' }[t ?? ''] ?? '') as 'primary' | 'success' | 'warning' | 'info' | ''
const statusLabel = (s?: string): string => ({ pending: '待收', partial: '部分收', paid: '已结清', overdue: '逾期' }[s ?? ''] ?? '-')
const statusType = (s?: string): 'info' | 'warning' | 'success' | 'danger' | '' => ({ pending: 'info', partial: 'warning', paid: 'success', overdue: 'danger' }[s ?? ''] ?? '') as 'info' | 'warning' | 'success' | 'danger' | ''

const loadAll = async () => {
  loading.value = true
  try {
    const res = await ledger.listCustomers({
      customer_id: filter.customer_id ? Number(filter.customer_id) || undefined : undefined,
      project_id: filter.project_id ? Number(filter.project_id) || undefined : undefined,
      receivable_type: filter.receivable_type || undefined,
      status: filter.status || undefined,
      from: filter.from || undefined,
      to: filter.to || undefined,
      page: page.value,
      per_page: pageSize.value,
    })
    // V0.6.3: res = {code, data: paginator}
    const pag = unwrapPaginate(res)
    overview.value = pag
    items.value = pag.list
    total.value = pag.total
  } catch {
    items.value = []; total.value = 0
  } finally {
    loading.value = false
  }
  try {
    const targetCustomer = items.value[0]?.customer_id
    if (targetCustomer) {
      const r = await ledger.getCustomerLedger(targetCustomer, {})
      const d = r?.data ?? r ?? {}
      monthlyData.value = d?.monthly ?? []
    } else {
      monthlyData.value = []
    }
  } catch { monthlyData.value = [] }
}

onMounted(loadAll)
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
}
.filter-bar { background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
.summary-card { margin-bottom: 16px; }
</style>
