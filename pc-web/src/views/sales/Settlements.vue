<template>
  <div class="page-container">
    <h2>推荐人居间费结算</h2>
    <p class="hint">商机成交时自动生成待审核结算单，财务审核 → 财务发放</p>

    <!-- 统计卡片 -->
    <div class="stat-row">
      <el-card class="stat-card" v-for="(card, idx) in statCards" :key="idx" :body-style="{ padding: '16px' }">
        <div class="stat-label">{{ card.label }}</div>
        <div class="stat-value" :style="{ color: card.color }">{{ card.value }}</div>
        <div class="stat-sub">{{ card.sub }}</div>
      </el-card>
    </div>

    <!-- 过滤 -->
    <el-form inline :model="filter" class="filter-form">
      <el-form-item label="状态">
        <el-select v-model="filter.status" placeholder="全部" clearable style="width: 140px">
          <el-option label="待审核" value="pending" />
          <el-option label="已审核" value="approved" />
          <el-option label="已发放" value="paid" />
          <el-option label="已取消" value="cancelled" />
        </el-select>
      </el-form-item>
      <el-form-item label="推荐人">
        <el-select v-model="filter.referrer_id" placeholder="全部" clearable filterable style="width: 200px">
          <el-option v-for="r in referrerOptions" :key="r.id" :label="r.name" :value="r.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="关键词">
        <el-input v-model="filter.keyword" placeholder="搜索备注/流水号/推荐人" clearable style="width: 240px" @keyup.enter="loadList" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="loadList">查询</el-button>
        <el-button @click="resetFilter">重置</el-button>
      </el-form-item>
    </el-form>

    <!-- 列表 -->
    <el-table :data="list" v-loading="loading" stripe border>
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column label="商机" min-width="200">
        <template #default="{ row }">
          <div>{{ row.opportunity?.name || '-' }}</div>
          <div class="muted">#{{ row.opportunity_id }}</div>
        </template>
      </el-table-column>
      <el-table-column label="推荐人" min-width="120">
        <template #default="{ row }">
          {{ row.referrer?.name || '-' }}
        </template>
      </el-table-column>
      <el-table-column label="结算金额" width="160" align="right">
        <template #default="{ row }">
          <div class="money">¥ {{ Number(row.amount).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) }}</div>
          <div class="muted">{{ row.commission_rate }}% × ¥{{ Number(row.contract_amount).toLocaleString() }}</div>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="statusTagType(row.status)" effect="dark">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="审核" width="160">
        <template #default="{ row }">
          <div v-if="row.approved_at" class="muted">{{ formatDate(row.approved_at) }}</div>
          <div v-if="row.approver">{{ row.approver.name }}</div>
          <div v-else class="muted">-</div>
        </template>
      </el-table-column>
      <el-table-column label="发放" width="160">
        <template #default="{ row }">
          <div v-if="row.paid_at" class="muted">{{ formatDate(row.paid_at) }}</div>
          <div v-if="row.payment_no" class="muted">流水: {{ row.payment_no }}</div>
        </template>
      </el-table-column>
      <el-table-column label="备注" min-width="200">
        <template #default="{ row }">
          <div class="truncate">{{ row.notes || '-' }}</div>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-tag
            v-if="row.status === 'pending' && canApprove"
            size="small"
            type="info"
            effect="plain"
          >去审批中心</el-tag>
          <el-button
            v-if="row.status === 'approved' && canPay"
            link
            type="primary"
            @click="openPay(row)"
          >发放</el-button>
          <el-button link type="primary" @click="showDetail(row)">查看</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 分页 -->
    <el-pagination
      class="pager"
      v-model:current-page="page"
      v-model:page-size="perPage"
      :page-sizes="[10, 20, 50, 100]"
      :total="total"
      layout="total, sizes, prev, pager, next, jumper"
      @size-change="loadList"
      @current-change="loadList"
    />

    <!-- 发放对话框 -->
    <el-dialog v-model="showPayDialog" title="财务发放" width="500px">
      <el-form :model="payForm" label-width="100px">
        <el-form-item label="结算金额">
          <div class="money-big">¥ {{ payTarget?.amount }}</div>
        </el-form-item>
        <el-form-item label="推荐人">
          {{ payTarget?.referrer?.name }}
        </el-form-item>
        <el-form-item label="流水号" required>
          <el-input v-model="payForm.payment_no" placeholder="如 BANK20260623001" />
        </el-form-item>
        <el-form-item label="回单文件">
          <el-input v-model="payForm.payment_voucher" placeholder="disk/sales/referral/2026/06/xxx.pdf" />
          <div class="muted">v0.3.11: 文件路径（v0.4 接上传组件）</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPayDialog = false">取消</el-button>
        <el-button type="primary" :loading="paying" @click="confirmPay">确认发放</el-button>
      </template>
    </el-dialog>

    <!-- 详情抽屉 -->
    <el-drawer v-model="showDetailDrawer" title="结算单详情" size="50%">
      <div v-if="currentSettlement" class="detail">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="ID">{{ currentSettlement.id }}</el-descriptions-item>
          <el-descriptions-item label="商机">{{ currentSettlement.opportunity?.name }} (#{{ currentSettlement.opportunity_id }})</el-descriptions-item>
          <el-descriptions-item label="推荐人">{{ currentSettlement.referrer?.name }}</el-descriptions-item>
          <el-descriptions-item label="结算金额">¥ {{ currentSettlement.amount }} ({{ currentSettlement.commission_rate }}% × ¥{{ currentSettlement.contract_amount }})</el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag :type="statusTagType(currentSettlement.status)">{{ statusLabel(currentSettlement.status) }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="创建人">{{ currentSettlement.creator?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="审核人">{{ currentSettlement.approver?.name || '-' }} / {{ currentSettlement.approved_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="发放人">{{ currentSettlement.payer?.name || '-' }} / {{ currentSettlement.paid_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="流水号">{{ currentSettlement.payment_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="回单">{{ currentSettlement.payment_voucher || '-' }}</el-descriptions-item>
          <el-descriptions-item label="备注">{{ currentSettlement.notes || '-' }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ currentSettlement.created_at }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-drawer>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getReferralSettlements, getReferralSettlementDetail,
  approveReferralSettlement, payReferralSettlement, getReferralSettlementStats
} from '@/api/sales'
import { getReferrers } from '@/api/sales'
import { useUserStore } from '@/stores/user'
import type { Settlement, SettlementStats, Referrer } from './types'

const userStore = useUserStore()
// v0.3.14 D3: 权限细分 — 审核(sales_manager/finance/admin) + 发放(finance/admin only)
const canApprove = computed(() =>
  userStore.hasRole('admin') || userStore.hasRole('sales_manager') || userStore.hasRole('finance') || userStore.hasRole('manager'),
)
const canPay = computed(() =>
  userStore.hasRole('admin') || userStore.hasRole('finance'),
)

const loading = ref(false)
const list = ref<Record<string, unknown>[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const stats = ref<Record<string, unknown>>({})

const filter = reactive({
  status: '' as string,
  referrer_id: null as number | null,
  keyword: '' as string,
})

const referrerOptions = ref<Referrer[]>([])
const showPayDialog = ref(false)
const payTarget = ref<Settlement | null>(null)
const payForm = reactive({ payment_no: '', payment_voucher: '' })
const paying = ref(false)
const showDetailDrawer = ref(false)
const currentSettlement = ref<Settlement | null>(null)

const statCards = computed(() => [
  { label: '待审核', value: stats.value.pending || 0, sub: '¥ ' + Number(stats.value.total_amount_pending || 0).toLocaleString(), color: '#E6A23C' },
  { label: '已审核', value: stats.value.approved || 0, sub: '¥ ' + Number(stats.value.total_amount_approved || 0).toLocaleString(), color: '#0C447C' },
  { label: '已发放', value: stats.value.paid || 0, sub: '¥ ' + Number(stats.value.total_amount_paid || 0).toLocaleString(), color: '#1D9E75' },
])

const statusLabel = (s: string) => ({
  pending: '待审核', approved: '已审核', paid: '已发放', cancelled: '已取消',
}[s] || s)
const STATUS_TAG_TYPES: Record<string, string> = { pending: 'warning', approved: 'primary', paid: 'success', cancelled: 'info' }
const statusTagType = (s: string): string => STATUS_TAG_TYPES[s] || 'info'
const formatDate = (d: string) => d ? d.slice(0, 16).replace('T', ' ') : '-'

const loadList = async () => {
  loading.value = true
  try {
    const r = await getReferralSettlements({
      page: page.value,
      per_page: perPage.value,
      status: filter.status || undefined,
      referrer_id: filter.referrer_id || undefined,
      keyword: filter.keyword || undefined,
    })
    // V0.6.3: res = {code, data: paginator}
    const d = r?.data ?? {}
    list.value = Array.isArray(d?.data) ? d.data : []
    total.value = d?.total || 0
  } catch (e) { /* toast */ } finally { loading.value = false }
}

const loadStats = async () => {
  try { const r = await getReferralSettlementStats(); stats.value = r?.data ?? {} } catch {}
}

const loadReferrers = async () => {
  try { referrerOptions.value = (await getReferrers({ per_page: 100 })).data?.data || [] } catch {}
}

const resetFilter = () => {
  filter.status = ''
  filter.referrer_id = null
  filter.keyword = ''
  page.value = 1
  loadList()
}

const approve = async (row: Settlement) => {
  try {
    await ElMessageBox.confirm(`确认审核通过结算单 #${row.id} (¥${row.amount})?`, '财务审核', { type: 'warning' })
  } catch { return }
  try {
    await approveReferralSettlement(row.id)
    ElMessage.success('审核通过')
    loadList(); loadStats()
  } catch (e) {}
}

const openPay = (row: Settlement) => {
  payTarget.value = row
  payForm.payment_no = ''
  payForm.payment_voucher = ''
  showPayDialog.value = true
}

const confirmPay = async () => {
  if (!payForm.payment_no) { ElMessage.warning('请输入流水号'); return }
  paying.value = true
  try {
    await payReferralSettlement(payTarget.value.id, payForm)
    ElMessage.success('已发放')
    showPayDialog.value = false
    loadList(); loadStats()
  } catch (e) {} finally { paying.value = false }
}

const showDetail = async (row: Record<string, unknown>) => {
  try {
    currentSettlement.value = (await getReferralSettlementDetail(row.id)).data
    showDetailDrawer.value = true
  } catch {}
}

onMounted(() => { loadList(); loadStats(); loadReferrers() })
</script>

<style scoped>
.page-container { padding: 16px; }
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; }
.stat-card { background: #f7f9fc; }
.stat-label { font-size: 13px; color: #666; }
.stat-value { font-size: 26px; font-weight: 600; margin: 4px 0; }
.stat-sub { font-size: 12px; color: #999; }
.filter-form { background: #fff; padding: 12px; border-radius: 4px; margin-bottom: 12px; }
.money { font-weight: 600; }
.money-big { font-size: 22px; font-weight: 600; color: #1D9E75; }
.muted { font-size: 12px; color: #999; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 240px; }
.pager { margin-top: 16px; text-align: right; }
.hint { color: #666; font-size: 13px; margin: 0 0 16px; }
.detail { padding: 0 16px; }
</style>
