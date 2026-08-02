<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">商机池</span>
      <div class="header-actions">
        <el-button :icon="DataLine" plain @click="$router.push('/sales/opps/board')">看板视图</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建商机</el-button>
      </div>
    </div>

    <!-- 漏斗统计 -->
    <div class="funnel-row">
      <div
        v-for="s in funnelData"
        :key="s.value"
        class="funnel-item"
        :style="{ borderColor: s.color }"
      >
        <div
          class="funnel-bar"
          :style="{
            background: `linear-gradient(90deg, ${s.color}15, ${s.color}05)`,
            width: funnelWidth(s) + '%',
          }"
        ></div>
        <div class="funnel-content">
          <div class="funnel-label">
            <el-icon :size="14" :color="s.color"><component :is="s.icon" /></el-icon>
            {{ s.label }}
          </div>
          <div class="funnel-stats">
            <div class="funnel-count">{{ s.count }}<span class="funnel-unit">个</span></div>
            <div class="funnel-amount">¥ {{ formatMoney(s.total_amount) }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="商机名称">
          <el-input
            v-model="searchForm.keyword"
            placeholder="模糊搜索"
            clearable
            style="width: 180px"
          />
        </el-form-item>
        <el-form-item label="阶段">
          <el-select
            v-model="searchForm.stage"
            placeholder="全部阶段"
            clearable
            style="width: 160px"
          >
            <el-option
              v-for="s in STAGE_OPTIONS"
              :key="s.value"
              :label="s.label"
              :value="s.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="content-card">
      <el-table
        :data="pagedList"
        stripe
        border
        v-loading="loading"
        :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
      >
        <el-table-column prop="opp_no" label="商机编号" width="160" fixed>
          <template #default="{ row }">
            <span class="link-text" @click="handleView(row)">{{ row.opp_no }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="商机名称" min-width="220" fixed show-overflow-tooltip />
        <el-table-column label="客户" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="阶段" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="stageTagType(row.stage)" effect="dark" size="small">
              {{ stageLabel(row.stage) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="probability" label="概率" width="100" align="center">
          <template #default="{ row }">
            <el-progress
              :percentage="row.probability || 0"
              :stroke-width="6"
              :show-text="false"
              :color="probabilityColor(row.probability)"
            />
            <span style="font-size: 11px; color: #909399">{{ row.probability || 0 }}%</span>
          </template>
        </el-table-column>
        <el-table-column prop="estimated_amount" label="预计金额" width="120" align="right">
          <template #default="{ row }">¥ {{ formatMoney(row.estimated_amount) }}</template>
        </el-table-column>
        <el-table-column label="销售" width="100">
          <template #default="{ row }">{{ row.sales?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="售前" width="100">
          <template #default="{ row }">{{ row.presale?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="预计签约" width="120" align="center">
          <template #default="{ row }">{{ formatDate(row.expected_sign_date) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleView(row)">查看</el-button>
            <el-button v-if="row.stage !== 'lost'" link type="warning" @click="handleEdit(row)">
              编辑
            </el-button>
            <el-button
              v-if="row.stage !== 'lost'"
              link
              type="success"
              @click="$router.push(`/sales/opps/${row.id}/quote`)"
            >
              报价
            </el-button>
            <template v-if="!isClosed(row.stage)">
              <el-button link type="success" @click="handleWin(row)">成交</el-button>
              <el-button link type="danger" @click="handleLost(row)">战败</el-button>
            </template>
            <el-button
              v-if="row.stage === 'lost'"
              link
              type="warning"
              @click="handleRevive(row)"
            >
              战败复活
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
          @size-change="loadList"
          @current-change="loadList"
        />
      </div>
    </div>

    <OppDialog
      v-model:visible="showFormDialog"
      :mode="formMode"
      :submitting="submitting"
      :customer-options="customerOptions"
      :user-options="userOptions"
      :referrer-options="referrerOptions"
      :target="formTarget"
      @save="handleSave"
    />

    <WinDialog
      v-model:visible="showWinDialog"
      :submitting="submitting"
      :target="winTarget"
      @confirm="confirmWin"
    />

    <LostDialog
      v-model:visible="showLostDialog"
      :submitting="submitting"
      :target="lostTarget"
      :lost-reasons="lostReasons"
      @confirm="confirmLost"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Plus, Search, Refresh, DataLine, Aim, ChatLineRound, Document, Money, Promotion, Trophy,
} from '@element-plus/icons-vue'
import {
  getOpps, getOppStageOptions, getOppFunnel, getOppLostReasons,
  createOpp, updateOpp, markOppWon, markOppLost, reviveOpp, getCustomerOptions,
  getReferrers,  // V1.2.5
} from '@/api/sales'
import { getEmployeeList } from '@/api/employee'
import { unwrapList } from '@/utils/response'
import OppDialog, { type OppFormData } from './components/OppDialog.vue'
import WinDialog, { type WinFormData } from './components/WinDialog.vue'
import LostDialog, { type LostFormData } from './components/LostDialog.vue'
import {
  STAGE_OPTIONS, stageLabel, stageTagType, probabilityColor, formatMoney, formatDate, isClosed,
} from './types'
import type { StageOption } from './types'

// ===== 类型定义 =====
interface Opp {
  id: number | string
  opp_no?: string
  name?: string
  customer?: { id?: number; name?: string }
  stage?: string
  probability?: number
  estimated_amount?: number
  sales?: { id?: number; name?: string }
  presale?: { id?: number; name?: string }
  expected_sign_date?: string
  [k: string]: unknown
}
interface FunnelItem {
  value: string
  label: string
  color: string
  count: number
  total_amount: number
  icon: unknown
  [k: string]: unknown
}
interface FunnelRow { stage?: string; count?: number; total_amount?: number; [k: string]: unknown }
interface LostReason { value?: string; label?: string; [k: string]: unknown }
interface UserOption { id: number | string; name?: string; [k: string]: unknown }
interface CustomerOption { id: number | string; name?: string; [k: string]: unknown }
interface ReferrerOption { id: number | string; name?: string; [k: string]: unknown }
interface OppListResponse { data?: { data?: Opp[]; total?: number }; [k: string]: unknown }
interface StageOptionsResponse { data?: StageOption[]; [k: string]: unknown }
interface LostReasonsResponse { data?: LostReason[]; [k: string]: unknown }
interface FunnelResponse { data?: FunnelRow[]; [k: string]: unknown }
interface EmployeeListResponse { data?: UserOption[]; [k: string]: unknown }
interface CustomerOptionsResponse { data?: CustomerOption[]; [k: string]: unknown }
interface ReferrersResponse { data?: { data?: ReferrerOption[] } | ReferrerOption[]; [k: string]: unknown }

const router = useRouter()
const loading = ref(false)
const submitting = ref(false)
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const list = ref<Opp[]>([])
const funnelData = ref<FunnelItem[]>([])

const searchForm = reactive({ keyword: '', stage: '' })

// 后端 stage 接口返回的优先,否则用本地默认 STAGE_OPTIONS
const backendStages = ref<StageOption[]>([])
const stageOptionsComputed = computed(() =>
  backendStages.value.length > 0 ? backendStages.value : STAGE_OPTIONS,
)
const lostReasons = ref<LostReason[]>([])
const userOptions = ref<UserOption[]>([])
const customerOptions = ref<CustomerOption[]>([])
const referrerOptions = ref<ReferrerOption[]>([])  // V1.2.5

const pagedList = computed(() => list.value)

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: page.value, per_page: pageSize.value }
    if (searchForm.keyword) params.keyword = searchForm.keyword
    if (searchForm.stage) params.stage = searchForm.stage
    const r: OppListResponse = await getOpps(params)
    // V0.6.3: res = {code, data: paginator}
    const d = r?.data ?? {}
    list.value = Array.isArray(d?.data) ? d.data : []
    total.value = d?.total || 0
  } catch (e) {
    /* toast */
  } finally {
    loading.value = false
  }
}

const loadStages = async () => {
  try {
    const r: StageOptionsResponse = await getOppStageOptions()
    // V0.6.3: 响应 {code, data: [...]}
    const stages = r?.data ?? (Array.isArray(r) ? r : null)
    if (Array.isArray(stages) && stages.length > 0) backendStages.value = stages
  } catch (e) {
    /* fallback to STAGE_OPTIONS */
  }
}

const loadLostReasons = async () => {
  try {
    const r: LostReasonsResponse = await getOppLostReasons()
    lostReasons.value = r || []
  } catch (e) {
    lostReasons.value = []
  }
}

const loadFunnel = async () => {
  try {
    const r: FunnelResponse = await getOppFunnel()
    const rows = r || []
    const icons: Record<string, unknown> = {
      inquiry: Aim, qualification: Document, proposal: DocumentCopy,
      negotiating: Money, quoted: Promotion, won: Trophy, lost: ChatLineRound,
    }
    funnelData.value = stageOptionsComputed.value.map((s: StageOption) => {
      const found = rows.find((rr: FunnelRow) => rr.stage === s.value) || { count: 0, total_amount: 0 }
      return {
        ...s, count: found.count, total_amount: found.total_amount,
        icon: icons[s.value] || Document, color: s.color || '#0C447C',
      }
    })
  } catch (e) {
    /* fallback */
  }
}

const loadUsers = async () => {
  try {
    const r = await getEmployeeList({ per_page: 200 })
    userOptions.value = unwrapList(r as unknown as Record<string, unknown>) as UserOption[]
  } catch (e) {
    userOptions.value = []
  }
}

const loadCustomers = async () => {
  try {
    const r = await getCustomerOptions({ per_page: 200 })
    customerOptions.value = unwrapList(r as unknown as Record<string, unknown>) as CustomerOption[]
  } catch (e) {
    customerOptions.value = []
  }
}

const loadReferrers = async () => {  // V1.2.5
  try {
    const r = await getReferrers({ per_page: 200 })
    referrerOptions.value = unwrapList(r as unknown as Record<string, unknown>) as ReferrerOption[]
  } catch (e) {
    referrerOptions.value = []
  }
}

const funnelWidth = (s: FunnelItem) => {
  if (!s.total_amount || s.total_amount === 0) return 30
  const max = Math.max(...funnelData.value.map((f) => f.total_amount), 1)
  return Math.max(20, (s.total_amount / max) * 100)
}

const handleSearch = () => { page.value = 1; loadList() }
const handleReset = () => { searchForm.keyword = ''; searchForm.stage = ''; page.value = 1; loadList() }
const handleView = (row: Opp) => router.push(`/sales/opps/${row.id}`)

// 新建/编辑
const showFormDialog = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const formTarget = ref<Opp | null>(null)

const handleAdd = () => {
  formMode.value = 'create'
  formTarget.value = { id: 0 }
  showFormDialog.value = true
}

const handleEdit = (row: Opp) => {
  formMode.value = 'edit'
  formTarget.value = row
  showFormDialog.value = true
}

const handleSave = async (data: OppFormData, mode: 'create' | 'edit') => {
  submitting.value = true
  try {
    const payload: Record<string, unknown> = { ...data }
    delete payload.id
    if (mode === 'create') {
      await createOpp(payload)
      ElMessage.success('商机创建成功')
    } else {
      await updateOpp(data.id, payload)
      ElMessage.success('商机已更新')
    }
    showFormDialog.value = false
    page.value = 1
    await loadList()
    loadFunnel()
  } catch (e) {
    /* toast */
  } finally {
    submitting.value = false
  }
}

// 成交
const showWinDialog = ref(false)
const winTarget = ref<Opp | null>(null)

const handleWin = (row: Opp) => {
  winTarget.value = row
  showWinDialog.value = true
}

const confirmWin = async (data: WinFormData) => {
  submitting.value = true
  try {
    await markOppWon(winTarget.value.id, data)
    ElMessage.success(`「${winTarget.value.name}」已成交，自动创建项目池`)
    showWinDialog.value = false
    await loadList()
    loadFunnel()
  } catch (e) {
    /* toast */
  } finally {
    submitting.value = false
  }
}

// 战败
const showLostDialog = ref(false)
const lostTarget = ref<Opp | null>(null)

const handleLost = (row: Opp) => {
  lostTarget.value = row
  showLostDialog.value = true
}

const confirmLost = async (data: LostFormData) => {
  submitting.value = true
  try {
    await markOppLost(lostTarget.value.id, data)
    ElMessage.success('已标记为战败')
    showLostDialog.value = false
    await loadList()
    loadFunnel()
  } catch (e) {
    /* toast */
  } finally {
    submitting.value = false
  }
}

const handleRevive = async (row: Opp) => {
  try {
    await ElMessageBox.confirm(
      `确认将战败商机「${row.name || row.opp_no}」复活到「需求确认」阶段？`,
      '战败复活',
      { type: 'warning', confirmButtonText: '确认复活', cancelButtonText: '取消' },
    )
  } catch {
    return
  }
  submitting.value = true
  try {
    await reviveOpp(row.id)
    ElMessage.success('已复活到「需求确认」')
    await loadList()
    loadFunnel()
  } catch (e) {
    /* toast */
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadStages(); loadLostReasons(); loadUsers(); loadCustomers(); loadReferrers(); loadList(); loadFunnel()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.funnel-row { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 12px; }
.funnel-item {
  position: relative; padding: 12px 16px;
  background: #fff; border-radius: 6px; border-left: 4px solid;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04); overflow: hidden; min-height: 80px;
  .funnel-bar { position: absolute; left: 0; top: 0; bottom: 0; z-index: 0; transition: width 0.3s; }
  .funnel-content { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 4px; }
  .funnel-label { font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
  .funnel-stats { display: flex; justify-content: space-between; align-items: baseline; }
  .funnel-count { font-size: 20px; font-weight: 700; color: #303133; }
  .funnel-unit { font-size: 11px; color: #909399; font-weight: normal; margin-left: 2px; }
  .funnel-amount { font-size: 12px; color: #BA7517; font-weight: 600; }
}
.filter-bar { background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; &:hover { text-decoration: underline; } }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
