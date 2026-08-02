<template>
  <div class="page-container">
    <div class="page-header">
      <div class="title-area">
        <el-button :icon="ArrowLeft" text @click="$router.back()">返回商机</el-button>
        <span class="page-title">报价单</span>
        <el-tag effect="light" type="info">商机 #{{ oppId }}</el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="CopyDocument" @click="handleNewVersion">新建版本</el-button>
        <el-button :icon="Upload" :disabled="!currentQuote || currentQuote.status !== 'draft'" @click="handleSubmit">提交审批</el-button>
        <el-button type="primary" :icon="Check" :disabled="!currentQuote || !['submitted', 'negotiating'].includes(currentQuote.status)" @click="handleAccept">客户接受</el-button>
      </div>
    </div>

    <div class="quote-grid">
      <!-- 左：报价单列表 -->
      <div class="quote-list">
        <div class="list-header">报价版本</div>
        <QuoteListSkeleton v-if="listLoading" />
        <QuoteErrorState
          v-else-if="listError"
          title="报价单加载失败"
          :message="listError"
          :retrying="listLoading"
          @retry="loadList"
        />
        <template v-else>
          <div v-for="q in quotes" :key="q.id" class="quote-item" :class="{ active: currentQuote?.id === q.id }" @click="selectQuote(q)">
            <div class="qi-head">
              <span class="qi-version">V{{ q.version }}</span>
              <el-tag :type="quoteStatusTagType(q.status)" size="small" effect="dark">{{ quoteStatusLabel(q.status) }}</el-tag>
            </div>
            <div class="qi-no">{{ q.quote_no }}</div>
            <div class="qi-amount">¥ {{ formatMoney(q.total_amount) }}</div>
            <div class="qi-date">{{ formatDate(q.created_at) }}</div>
          </div>
          <el-empty v-if="!quotes.length" :image-size="50" description="暂无报价单" />
        </template>
      </div>

      <!-- 右：报价单详情 -->
      <div class="quote-detail">
        <el-empty v-if="!currentQuote" description="选择左侧版本查看详情" :image-size="80" />
        <template v-else>
          <div class="detail-header">
            <div>
              <div class="dh-title">
                <span class="dh-version">V{{ currentQuote.version }}</span>
                <span class="dh-no">{{ currentQuote.quote_no }}</span>
                <el-tag :type="quoteStatusTagType(currentQuote.status)" effect="dark" size="default">{{ quoteStatusLabel(currentQuote.status) }}</el-tag>
              </div>
              <div class="dh-meta">创建于 {{ formatDateTime(currentQuote.created_at) }} · 有效期至 {{ formatDate(currentQuote.valid_until) || '—' }}</div>
            </div>
            <div class="dh-actions">
              <el-button :icon="Download" size="default" @click="openExportDialog" plain>导出</el-button>
              <el-button
                v-if="quotes.length > 1"
                :icon="View"
                size="default"
                @click="openCompareDialog"
                plain
              >
                版本对比
              </el-button>
              <el-tag :type="quoteStatusTagType(currentQuote.status)" effect="plain" size="large">总金额 ¥ {{ formatMoney(currentQuote.total_amount) }}</el-tag>
            </div>
          </div>

          <!-- 摘要/折扣/税率设置 (草稿可改) -->
          <el-form inline :model="form" :disabled="!isDraft" class="form-summary">
            <el-form-item label="折扣率(%)">
              <el-input-number v-model="form.discount_rate" :min="0" :max="30" :step="0.5" size="small" style="width: 110px" />
            </el-form-item>
            <el-form-item label="税率(%)">
              <el-select v-model="form.tax_rate" size="small" style="width: 110px">
                <el-option label="0% 免税" :value="0" />
                <el-option label="3%" :value="3" />
                <el-option label="6%" :value="6" />
                <el-option label="9%" :value="9" />
                <el-option label="13%" :value="13" />
              </el-select>
            </el-form-item>
            <el-form-item label="有效期">
              <el-date-picker v-model="form.valid_until" type="date" size="small" value-format="YYYY-MM-DD" placeholder="选择日期" />
            </el-form-item>
            <el-form-item v-if="expiringSoon" label=" ">
              <el-tag type="warning" size="small">⚠️ 即将过期 (7天内)</el-tag>
            </el-form-item>
          </el-form>

          <!-- 产品清单 -->
          <div class="items-head">
            <h3 class="section-title">产品清单 ({{ items.length }} 项)</h3>
            <div class="items-actions" v-if="isDraft">
              <el-button v-if="selectedItems.length > 0" type="danger" size="small" :icon="Delete" @click="batchRemoveItems">
                删除选中 ({{ selectedItems.length }})
              </el-button>
              <el-button v-if="selectedItems.length > 0" size="small" @click="batchApplyDiscount">批量折扣</el-button>
              <el-button type="primary" size="small" :icon="Plus" @click="openProductPicker">从产品库选</el-button>
              <el-button size="small" :icon="Plus" @click="addCustomItem">添加非标品</el-button>
              <el-button :icon="Refresh" size="small" @click="saveItems" :loading="saving">保存并重算</el-button>
            </div>
          </div>

          <el-table
            :data="items"
            border
            size="default"
            class="items-table"
            @selection-change="(rows: QuoteItem[]) => selectedItems = rows"
            row-key="key_"
          >
            <el-table-column v-if="isDraft" type="selection" width="48" :selectable="() => true" />
            <el-table-column type="index" label="#" width="50" align="center" />
            <el-table-column label="产品/服务名称" min-width="200">
              <template #default="{ row, $index }">
                <el-input v-if="isDraft && row._edit" v-model="row.name" size="small" placeholder="名称" />
                <span v-else>{{ row.name }}</span>
              </template>
            </el-table-column>
            <el-table-column label="规格" min-width="120">
              <template #default="{ row }">
                <el-input v-if="isDraft && row._edit" v-model="row.specification" size="small" placeholder="规格" />
                <span v-else>{{ row.specification || '—' }}</span>
              </template>
            </el-table-column>
            <el-table-column label="单位" width="80" align="center">
              <template #default="{ row }">
                <el-input v-if="isDraft && row._edit" v-model="row.unit" size="small" />
                <span v-else>{{ row.unit || '—' }}</span>
              </template>
            </el-table-column>
            <el-table-column label="数量" width="110" align="right">
              <template #default="{ row }">
                <el-input-number v-if="isDraft" v-model="row.quantity" :min="0" :step="0.5" size="small" :precision="2" controls-position="right" />
                <span v-else>{{ row.quantity }}</span>
              </template>
            </el-table-column>
            <el-table-column label="单价" width="130" align="right">
              <template #default="{ row }">
                <el-input-number v-if="isDraft" v-model="row.unit_price" :min="0" :step="0.01" size="small" :precision="2" controls-position="right" />
                <span v-else>¥ {{ formatMoney(row.unit_price) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="小计" width="130" align="right">
              <template #default="{ row }">
                <span style="font-weight: 600; color: #BA7517">¥ {{ formatMoney(rowTotal(row)) }}</span>
              </template>
            </el-table-column>
            <el-table-column v-if="isDraft" label="操作" width="80" align="center">
              <template #default="{ $index }">
                <el-button link type="danger" :icon="Delete" @click="removeItem($index)" />
              </template>
            </el-table-column>
          </el-table>

          <!-- 金额汇总 -->
          <QuoteAmountBreakdown
            :form="form"
            :local-totals="localTotals"
            :server-total-diff="serverTotalDiff"
            :server-total="Number(currentQuote.total_amount || 0)"
            :diff-amount="Math.abs(localTotals.total - Number(currentQuote.total_amount || 0))"
            :format-money="formatMoney"
          />

          <h3 class="section-title">状态流转</h3>
          <el-steps :active="quoteStatusStep(currentQuote.status)" finish-status="success" align-center>
            <el-step title="草稿" />
            <el-step title="已提交" />
            <el-step title="谈判中" />
            <el-step title="客户接受" />
          </el-steps>
        </template>
      </div>
    </div>

    <!-- 产品库选择对话框 -->
    <ProductPickerDialog
      v-model:visible="showPicker"
      :existing-items="items"
      @pick="handlePickProducts"
    />

    <!-- 版本对比 drawer -->
    <QuoteCompareDrawer
      v-model:visible="compareVisible"
      :left-id="compareLeftId"
      :right-id="compareRightId"
      :left-version="compareLeftVersion"
      :right-version="compareRightVersion"
    />

    <!-- 导出对话框 -->
    <QuoteExportDialog
      v-model:visible="exportVisible"
      :quote="currentQuote"
      :items="items"
      :customer-name="customerName"
      :opp-name="oppName"
    />

    <!-- 批量折扣对话框 -->
    <el-dialog
      v-model="batchDiscountVisible"
      title="批量应用折扣"
      width="420px"
      :close-on-click-modal="false"
    >
      <el-form label-width="100px">
        <el-form-item label="选中数量">
          <el-tag>{{ selectedItems.length }} 个产品</el-tag>
        </el-form-item>
        <el-form-item label="操作方式">
          <el-radio-group v-model="batchDiscountMode">
            <el-radio value="override">直接覆盖单价</el-radio>
            <el-radio value="percent">按百分比打折</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="batchDiscountMode === 'override'" label="新单价 (¥)">
          <el-input-number v-model="batchDiscountValue" :min="0" :step="1" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item v-else label="折扣百分比 (%)">
          <el-input-number v-model="batchDiscountValue" :min="0" :max="100" :step="1" :precision="1" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="batchDiscountVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmBatchDiscount">应用并保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowLeft, CopyDocument, Upload, Check, Plus, Delete, Refresh, Download, View } from '@element-plus/icons-vue'
import {
  getQuotes, getQuoteDetail, getQuoteStatusOptions,
  newQuoteVersion, updateQuoteStatus, addQuoteItems,
} from '@/api/sales'
import { unwrapPaginate, unwrapList } from '@/utils/response'
import ProductPickerDialog from './components/ProductPickerDialog.vue'
import QuoteListSkeleton from './components/QuoteListSkeleton.vue'
import QuoteErrorState from './components/QuoteErrorState.vue'
import QuoteCompareDrawer from './components/QuoteCompareDrawer.vue'
import QuoteExportDialog from './components/QuoteExportDialog.vue'
import QuoteAmountBreakdown from './components/quotes/QuoteAmountBreakdown.vue'
import {
  QUOTE_STATUS_OPTIONS, quoteStatusLabel, quoteStatusTagType, quoteStatusStep,
  formatMoney, formatDate, formatDateTime, type QuoteItem,
} from './quoteTypes'

// 报价单摘要 (列表项 + 当前选中详情共用)
interface QuoteSummary {
  id: number
  version: number
  quote_no: string
  status: string
  total_amount?: number | string
  created_at?: string
  valid_until?: string
  opportunity_name?: string
  customer_name?: string
  [key: string]: unknown
}
// 状态下拉项
interface StatusOption {
  value: string
  label: string
  [key: string]: unknown
}

const route = useRoute()
const oppId = computed(() => Number(route.params.id))
const quotes = ref<QuoteSummary[]>([])
const currentQuote = ref<QuoteSummary | null>(null)
const items = ref<QuoteItem[]>([])
const saving = ref(false)

const statusOptions = ref<StatusOption[]>([])

// v0.3.14 B3: 加载/错误状态
const listLoading = ref(false)
const listError = ref<string | null>(null)
// 客户/商机名（导出用）
const customerName = ref('')
const oppName = ref('')

// v0.3.14 B3: 批量操作
const selectedItems = ref<QuoteItem[]>([])
const batchDiscountVisible = ref(false)
const batchDiscountMode = ref<'override' | 'percent'>('percent')
const batchDiscountValue = ref(10)

// v0.3.14 B3: 版本对比 / 导出
const compareVisible = ref(false)
const compareLeftId = ref<number>()
const compareRightId = ref<number>()
const compareLeftVersion = ref<number>()
const compareRightVersion = ref<number>()

const exportVisible = ref(false)

const form = reactive({
  discount_rate: 0,
  tax_rate: 13,
  valid_until: '' as string,
})

const isDraft = computed(() => currentQuote.value?.status === 'draft')

const expiringSoon = computed(() => {
  if (!form.valid_until) return false
  const days = (new Date(form.valid_until).getTime() - Date.now()) / 86400000
  return days >= 0 && days <= 7
})

// 本地金额预览 (前端 watch)
const localTotals = computed(() => {
  const subtotal = items.value.reduce(
    (sum, r) => sum + (Number(r.quantity) || 0) * (Number(r.unit_price) || 0), 0,
  )
  const discount_amount = subtotal * (Number(form.discount_rate) || 0) / 100
  const afterDiscount = subtotal - discount_amount
  const tax_amount = afterDiscount * (Number(form.tax_rate) || 0) / 100
  const total = afterDiscount + tax_amount
  return { subtotal, discount_amount, tax_amount, total }
})

const serverTotalDiff = computed(() => {
  if (!currentQuote.value) return false
  return Math.abs(localTotals.value.total - Number(currentQuote.value.total_amount)) > 0.01
})

const rowTotal = (r: QuoteItem | Record<string, unknown>) => (Number(r.quantity) || 0) * (Number(r.unit_price) || 0)

const loadStatusOptions = async () => {
  try {
    const r: unknown = await getQuoteStatusOptions()
    // 统一用 unwrapList 解包 (兼容 {code,data:[...]} / 裸数组)
    const opts = unwrapList(r)
    statusOptions.value = (opts.length > 0 ? opts : QUOTE_STATUS_OPTIONS) as StatusOption[]
  } catch (e) {
    statusOptions.value = QUOTE_STATUS_OPTIONS
  }
}

const loadList = async () => {
  if (!oppId.value || !Number.isFinite(oppId.value)) {
    listError.value = '商机 ID 无效，请从商机列表点击进入'
    return
  }
  listLoading.value = true
  listError.value = null
  try {
    const r: unknown = await getQuotes({ opportunity_id: oppId.value, per_page: 50 })
    // V1.2.10: 用 unwrapPaginate 统一解包，兼容所有分页格式
    const pag = unwrapPaginate(r)
    quotes.value = pag.list
    // 顺便抓取商机/客户名（导出用）
    oppName.value = quotes.value?.[0]?.opportunity_name || ''
    customerName.value = quotes.value?.[0]?.customer_name || ''
    if (quotes.value.length && !currentQuote.value) selectQuote(quotes.value[0])
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    listError.value = err?.response?.data?.message || err?.message || '网络异常'
  } finally {
    listLoading.value = false
  }
}

const loadDetail = async (q: QuoteSummary) => {
  try {
    // V0.6.3: res = {code, data: <detail>}
    const r: unknown = await getQuoteDetail(q.id)
    const detail = ((r as { data?: unknown })?.data ?? r) as Record<string, unknown>
    const detailItems = Array.isArray(detail.items) ? (detail.items as QuoteItem[]) : []
    items.value = detailItems.map((it, idx) => ({
      ...it,
      // stable key for el-table row-key + batch select dedup
      key_: String(it.id ?? `tmp-${q.id}-${idx}`),
      _edit: false,
    }))
    form.discount_rate = Number(detail.discount_rate || 0)
    form.tax_rate = Number(detail.tax_rate || 13)
    form.valid_until = detail.valid_until ? String(detail.valid_until).slice(0, 10) : ''
  } catch (e) {
    items.value = []
  }
}

const selectQuote = (q: QuoteSummary) => {
  currentQuote.value = q
  loadDetail(q)
}

const handleNewVersion = async () => {
  if (!currentQuote.value) {
    ElMessage.warning('请先选择要复制的报价单')
    return
  }
  try {
    await ElMessageBox.confirm(
      `将基于 V${currentQuote.value.version} 复制为新版本（自动 +1），原版本保留。`,
      '新建版本',
      { type: 'info' },
    )
  } catch {
    return
  }
  try {
    await newQuoteVersion(currentQuote.value.id)
    ElMessage.success('新版本已创建')
    await loadList()
  } catch (e) {
    /* toast */
  }
}

const handleSubmit = async () => {
  if (!currentQuote.value) return
  try {
    await ElMessageBox.confirm(
      `确认将 V${currentQuote.value.version} 提交审批？提交后状态变为「已提交」且不可修改产品清单。`,
      '提交审批',
      { type: 'info' },
    )
  } catch {
    return
  }
  try {
    await updateQuoteStatus(currentQuote.value.id, 'submitted')
    ElMessage.success('已提交审批')
    await loadList()
  } catch (e) {
    /* toast */
  }
}

const handleAccept = async () => {
  if (!currentQuote.value) return
  try {
    await ElMessageBox.confirm(
      `客户接受 V${currentQuote.value.version}？\n该商机下其他报价单将自动置为「拒绝」，商机阶段推到「合同拟定」。`,
      '客户接受',
      { type: 'success' },
    )
  } catch {
    return
  }
  try {
    await updateQuoteStatus(currentQuote.value.id, 'accepted')
    ElMessage.success('已标记客户接受，商机已推进到合同拟定')
    await loadList()
  } catch (e) {
    /* toast */
  }
}

// ==================== 产品库 picker (子组件接管) ====================
const showPicker = ref(false)

const handlePickProducts = (picked: QuoteItem[]) => {
  items.value = [...items.value, ...picked]
  saveItems()
}

const openProductPicker = () => {
  showPicker.value = true
}

const addCustomItem = () => {
  items.value.push({
    key_: `new-${Date.now()}-${Math.random()}`,
    name: '',
    specification: '',
    unit: '件',
    quantity: 1,
    unit_price: 0,
    _edit: true,
  })
}

const removeItem = async (idx: number) => {
  try {
    await ElMessageBox.confirm('确认删除该产品行？', '提示', { type: 'warning' })
  } catch {
    return
  }
  items.value.splice(idx, 1)
  await saveItems()
}

// === v0.3.14 B3: 批量操作 ===
const batchRemoveItems = async () => {
  if (!selectedItems.value.length) return
  try {
    await ElMessageBox.confirm(
      `确认删除选中的 ${selectedItems.value.length} 个产品？`,
      '批量删除',
      { type: 'warning' },
    )
  } catch { return }
  const keysToRemove = new Set(selectedItems.value.map((it: QuoteItem) => it.key_))
  items.value = items.value.filter((it: QuoteItem) => !keysToRemove.has(it.key_))
  selectedItems.value = []
  await saveItems()
  ElMessage.success('已删除选中产品')
}

const batchApplyDiscount = () => {
  if (!selectedItems.value.length) return
  batchDiscountValue.value = 10
  batchDiscountMode.value = 'percent'
  batchDiscountVisible.value = true
}

const confirmBatchDiscount = async () => {
  if (!selectedItems.value.length) return
  const keys = new Set(selectedItems.value.map((it: QuoteItem) => it.key_))
  for (const it of items.value) {
    if (!keys.has(it.key_)) continue
    if (batchDiscountMode.value === 'override') {
      it.unit_price = Number(batchDiscountValue.value) || 0
    } else {
      it.unit_price = Math.round((Number(it.unit_price) || 0) * (1 - (Number(batchDiscountValue.value) || 0) / 100) * 100) / 100
    }
  }
  batchDiscountVisible.value = false
  selectedItems.value = []
  await saveItems()
  ElMessage.success('已应用批量折扣')
}

// === v0.3.14 B3: 版本对比 ===
const openCompareDialog = () => {
  if (!currentQuote.value || quotes.value.length < 2) {
    ElMessage.warning('需要至少 2 个版本才能对比')
    return
  }
  const sorted = [...quotes.value].sort((a: QuoteSummary, b: QuoteSummary) => a.version - b.version)
  const idx = sorted.findIndex((q: QuoteSummary) => q.id === currentQuote.value!.id)
  // 左：前一个版本；右：当前版本
  const left = idx > 0 ? sorted[idx - 1] : sorted[sorted.length - 2] || sorted[0]
  const right = currentQuote.value
  compareLeftId.value = left.id
  compareRightId.value = right.id
  compareLeftVersion.value = left.version
  compareRightVersion.value = right.version
  compareVisible.value = true
}

// === v0.3.14 B3: 导出 ===
const openExportDialog = () => {
  if (!currentQuote.value) {
    ElMessage.warning('请先选择报价单')
    return
  }
  exportVisible.value = true
}

const saveItems = async () => {
  if (!currentQuote.value) return
  saving.value = true
  try {
    const payload = items.value.map((it: QuoteItem) => ({
      product_id: it.product_id || null,
      code: it.code || null,
      name: it.name,
      specification: it.specification || null,
      unit: it.unit || null,
      quantity: Number(it.quantity) || 0,
      unit_price: Number(it.unit_price) || 0,
    }))
    await addQuoteItems(currentQuote.value.id, {
      items: payload,
      discount_rate: form.discount_rate,
      tax_rate: form.tax_rate,
      valid_until: form.valid_until || null,
    })
    ElMessage.success('已保存并重算')
    await loadDetail(currentQuote.value)
    // 重新拉 quotes 列表更新总金额
    const r: unknown = await getQuotes({ opportunity_id: oppId.value, per_page: 50 })
    quotes.value = unwrapPaginate(r).list
  } catch (e) {
    /* toast */
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadStatusOptions()
  loadList()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .title-area { display: flex; align-items: center; gap: 12px; }
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.quote-grid { display: grid; grid-template-columns: 320px 1fr; gap: 12px; }
.quote-list {
  background: #fff; border-radius: 8px; padding: 16px; height: calc(100vh - 180px);
  overflow-y: auto; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .list-header { font-size: 14px; font-weight: 600; color: #0C447C; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #ebeef5; }
  .quote-item {
    padding: 12px; border-radius: 6px; border: 1px solid #ebeef5;
    margin-bottom: 8px; cursor: pointer; transition: all 0.2s;
    &:hover { border-color: #0C447C; background: rgba(12, 68, 124, 0.04); }
    &.active { border-color: #0C447C; background: rgba(12, 68, 124, 0.08); box-shadow: 0 2px 8px rgba(12, 68, 124, 0.1); }
    .qi-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
    .qi-version { font-size: 16px; font-weight: 700; color: #0C447C; }
    .qi-no { font-size: 11px; color: #909399; font-family: 'Consolas', monospace; margin-bottom: 4px; }
    .qi-amount { font-size: 16px; font-weight: 600; color: #BA7517; margin-bottom: 4px; }
    .qi-date { font-size: 11px; color: #909399; }
  }
}
.quote-detail {
  background: #fff; border-radius: 8px; padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); height: calc(100vh - 180px); overflow-y: auto;
  .detail-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 16px; border-bottom: 1px solid #ebeef5; margin-bottom: 16px; }
  .dh-actions { display: flex; align-items: center; gap: 8px; }
  .dh-title { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
  .dh-version { font-size: 22px; font-weight: 700; color: #0C447C; }
  .dh-no { font-size: 13px; color: #909399; font-family: 'Consolas', monospace; }
  .dh-meta { font-size: 12px; color: #909399; }
  .section-title { font-size: 15px; font-weight: 600; color: #303133; margin: 20px 0 12px; }
  .form-summary { background: #f7f9fc; padding: 12px 16px; border-radius: 6px; margin-bottom: 12px; :deep(.el-form-item) { margin-bottom: 0; } }
  .items-head { display: flex; justify-content: space-between; align-items: center; }
  .items-actions { display: flex; gap: 8px; }
  .items-table { :deep(.el-input-number) { width: 100px; } }
  .amount-breakdown {
    margin-top: 16px; padding: 16px; background: #f5f7fa; border-radius: 6px;
    .ab-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #606266; }
    .ab-row.total { border-top: 1px solid #ebeef5; margin-top: 6px; padding-top: 12px; font-size: 16px; font-weight: 700; color: #0C447C; }
    .text-danger { color: #A32D2D; }
  }
}
.picker-search { padding: 0 0 12px; }
.muted { color: #909399; font-size: 12px; }
</style>
