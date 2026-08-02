<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">供应商总账</span>
      <el-button :icon="Refresh" plain @click="loadAll">刷新</el-button>
    </div>

    <div class="filter-bar">
      <el-form :inline="true">
        <el-form-item label="供应商">
          <el-input v-model="filter.supplier_id" placeholder="供应商ID" clearable style="width:140px" />
        </el-form-item>
        <el-form-item label="项目">
          <el-input v-model="filter.project_id" placeholder="项目ID" clearable style="width:140px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filter.status" placeholder="全部" clearable style="width:140px">
            <el-option label="待付" value="pending" />
            <el-option label="部分付" value="partial" />
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

    <LedgerSummaryCards :summary="overview.summary ?? {}" />

    <MonthlyTrendChart :data="monthlyData" kind="supplier" />

    <AgingTable type="payable" />

    <el-card shadow="never" style="margin-top: 16px">
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span>应付明细</span>
          <el-button type="primary" :icon="Plus" size="small" @click="showPaymentDialog = true">
            新增付款
          </el-button>
        </div>
      </template>
      <el-table :data="items" border v-loading="loading">
        <el-table-column prop="supplier.name" label="供应商" min-width="180">
          <template #default="{ row }">
            {{ row.supplier?.name || '-' }}
            <span style="color:#999;font-size:12px">({{ row.supplier?.code }})</span>
          </template>
        </el-table-column>
        <el-table-column prop="project.name" label="项目" min-width="140" />
        <el-table-column prop="ref_no" label="单号" width="140" />
        <el-table-column prop="source_type" label="来源" width="80">
          <template #default="{ row }">
            <el-tag size="small">{{ sourceLabel(row.source_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="amount" label="应付" width="100" align="right">
          <template #default="{ row }">¥{{ Number(row.amount).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column prop="paid_amount" label="已付" width="100" align="right">
          <template #default="{ row }">¥{{ Number(row.paid_amount).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column prop="balance" label="未付" width="100" align="right">
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
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="payOne(row)" :disabled="Number(row.balance) <= 0">
              付款
            </el-button>
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

    <PaymentDialog
      v-model:visible="showPaymentDialog"
      :supplier-id="paymentSupplierId"
      :supplier-name="paymentSupplierName"
      @saved="loadAll"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Search, Refresh } from '@element-plus/icons-vue'
import { ledger } from '@/api/ledger'
import { unwrapPaginate } from '@/utils/response'
import LedgerSummaryCards from './components/LedgerSummaryCards.vue'
import MonthlyTrendChart from './components/MonthlyTrendChart.vue'
import AgingTable from './components/AgingTable.vue'
import PaymentDialog from './components/PaymentDialog.vue'
import type { LedgerRow, LedgerOverview, MonthlyPoint } from './types'

const loading = ref(false)
const items = ref<LedgerRow[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)

const overview = ref<LedgerOverview>({ summary: { total_amount: 0, total_paid: 0, total_balance: 0, payable_count: 0, overdue_count: 0 } })
const monthlyData = ref<MonthlyPoint[]>([])

const filter = reactive({
  supplier_id: '' as string,
  project_id: '' as string,
  status: '',
  from: '',
  to: '',
})

const showPaymentDialog = ref(false)
const paymentSupplierId = ref(0)
const paymentSupplierName = ref('')

const sourceLabel = (s?: string): string => ({ quote: '报价', contract: '合同', manual: '手工' }[s ?? ''] ?? s ?? '-')
const statusLabel = (s?: string): string => ({ pending: '待付', partial: '部分付', paid: '已结清', overdue: '逾期' }[s ?? ''] ?? '-')
const statusType = (s?: string): 'info' | 'warning' | 'success' | 'danger' | '' => ({ pending: 'info', partial: 'warning', paid: 'success', overdue: 'danger' }[s ?? ''] ?? '') as 'info' | 'warning' | 'success' | 'danger' | ''

const loadAll = async () => {
  loading.value = true
  try {
    const res = await ledger.listSuppliers({
      supplier_id: filter.supplier_id ? Number(filter.supplier_id) || undefined : undefined,
      project_id: filter.project_id ? Number(filter.project_id) || undefined : undefined,
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
  // 额外加载月度趋势（取第一供应商的）
  try {
    const params: Record<string, unknown> = {}
    if (filter.supplier_id) params.id = filter.supplier_id
    const targetSupplier = items.value[0]?.supplier_id
    if (targetSupplier) {
      const r = await ledger.getSupplierLedger(targetSupplier, params)
      const d = r?.data ?? r ?? {}
      monthlyData.value = d?.monthly ?? []
    } else {
      monthlyData.value = []
    }
  } catch { monthlyData.value = [] }
}

const payOne = (row: LedgerRow) => {
  paymentSupplierId.value = row.supplier_id ?? 0
  paymentSupplierName.value = row.supplier?.name || ''
  showPaymentDialog.value = true
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
</style>
