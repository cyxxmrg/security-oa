// 报销管理页 composable — 数据加载 / 状态管理 / 业务操作
// 从 expense/index.vue <script setup> 抽出, 主组件 + 子组件共享
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { get, post, del } from '@/utils/request'
import { unwrapItem } from '@/utils/response'
import { useUserStore } from '@/stores/user'
import { expenseCategoryLabel, statusLabel as commonStatusLabel } from '@/utils/labels'

// ===== 类型定义 =====
interface ExpenseListItem {
  id: number | string
  claim_no?: string
  status?: string
  total_amount?: number | string
  user_id?: number
  user?: { name?: string }
  created_at?: string
  category?: string
  description?: string
  project_id?: number | null
  items?: Array<{ item_date: string; description: string; amount: number; category: string }>
  [k: string]: unknown
}
interface ExpenseStats { total_amount?: number; paid_amount?: number; approved_amount?: number; pending_amount?: number; [k: string]: unknown }
interface ProjectOption { id: number; name?: string; [k: string]: unknown }
interface ApiResponse<T = unknown> { data?: T; message?: string; [k: string]: unknown }
interface ApiError { message?: string; response?: { data?: { message?: string } } }
interface ExpenseListParams { page?: number; per_page?: number; keyword?: string; status?: string; category?: string; [k: string]: unknown }
interface ExpenseStatsParams { group_by?: 'user' | 'category' | 'project'; date_from?: string; date_to?: string; [k: string]: unknown }
interface ExpenseSubmitResult { data?: { claim_no?: string }; [k: string]: unknown }

export function useExpense() {
  const router = useRouter()
  const route = useRoute()
  const userStore = useUserStore()

  // V1.2.7g: 2 tab 切换 (申请改成了弹窗)
  const activeTab = ref<'list' | 'stats'>('list')
  const showApplyDialog = ref(false)
  const switchApply = () => { resetForm(); showApplyDialog.value = true }

  // ===== 状态选项 =====
  const statusOptions = [
    { value: 'submitted', label: '待审批' },
    { value: 'approved',  label: '已审批' },
    { value: 'rejected',  label: '已驳回' },
    { value: 'paid',      label: '已付款' },
    { value: 'cancelled', label: '已撤销' },
    { value: 'draft',     label: '草稿' },
  ]

  const categoryOptions = [
    { value: 'travel',       label: '差旅费' },
    { value: 'hospitality',  label: '招待费' },
    { value: 'office',       label: '办公费' },
    { value: 'transport',    label: '交通费' },
    { value: 'project_cost', label: '项目成本' },
    { value: 'other',        label: '其他' },
  ]

  const expenseStatusType = (s: string): 'primary' | 'success' | 'warning' | 'info' | 'danger' => {
    const map: Record<string, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
      submitted: 'warning',
      approved:  'success',
      rejected:  'danger',
      paid:      'success',
      cancelled: 'info',
      draft:     'info',
    }
    return map[s] || 'info'
  }

  const formatDate = (s?: string) => {
    if (!s) return '-'
    const d = new Date(s)
    if (isNaN(d.getTime())) return s
    const pad = (n: number) => n.toString().padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
  }

  const formatMoney = (v: unknown) => {
    const n = parseFloat(v)
    return isNaN(n) ? '0.00' : n.toFixed(2)
  }

  const searchForm = ref({ keyword: '', status: '', category: '' })
  const list = ref<ExpenseListItem[]>([])
  const loading = ref(false)
  const pagination = reactive({ page: 1, per_page: 15, total: 0 })
  const stats = ref<ExpenseStats | null>(null)

  // V1.2.7g: 申请报销表单
  const formRef = ref()
  const submitting = ref(false)
  const projectOptions = ref<ProjectOption[]>([])
  const form = reactive({
    category: '',
    description: '',
    project_id: null as number | null,
    items: [] as Array<{ item_date: string; description: string; amount: number; category: string }>,
  })
  const formRules = {
    category:    [{ required: true, message: '请选择费用类别', trigger: 'change' }],
    description: [{ required: true, message: '请填写报销事由', trigger: 'blur' }],
  }
  const totalAmount = computed(() => form.items.reduce((s, it) => s + (Number(it.amount) || 0), 0))
  const addItem = () => form.items.push({ item_date: new Date().toISOString().slice(0, 10), description: '', amount: 0, category: form.category || '其他' })
  const removeItem = (i: number) => form.items.splice(i, 1)
  const resetForm = () => {
    form.category = ''; form.description = ''; form.project_id = null; form.items = []
  }
  async function loadProjects() {
    try { const r: ApiResponse<{ items?: ProjectOption[]; data?: ProjectOption[] }> = await get('/projects', { pageSize: 500 }); const d = r?.data ?? {}; projectOptions.value = Array.isArray(d?.items) ? d.items : (Array.isArray(d?.data) ? d.data : []) } catch {}
  }
  async function handleSubmit() {
    if (!formRef.value) return
    try { await formRef.value.validate() } catch { return }
    if (form.items.length === 0) { ElMessage.warning('请至少添加一条费用明细'); return }
    submitting.value = true
    try {
      const res: ExpenseSubmitResult = await post('/expenses', { ...form, total_amount: totalAmount.value })
      ElMessage.success(`报销单 ${res?.data?.claim_no || ''} 提交成功, 请等待审批`)
      resetForm()
      showApplyDialog.value = false
      loadList(1)
    } catch (e: ApiError) {
      ElMessage.error(e?.response?.data?.message || e.message || '提交失败')
    } finally { submitting.value = false }
  }

  async function loadList(page = 1) {
    pagination.page = page
    loading.value = true
    try {
      const params: ExpenseListParams = { page, per_page: pagination.per_page }
      if (searchForm.value.keyword)  params.keyword  = searchForm.value.keyword
      if (searchForm.value.status)   params.status   = searchForm.value.status
      if (searchForm.value.category) params.category = searchForm.value.category
      const res: ApiResponse<{ data?: ExpenseListItem[]; items?: ExpenseListItem[]; total?: number; current_page?: number }> = await get('/expenses', params)
      const d = res?.data ?? {}
      list.value = Array.isArray(d?.data) ? d.data : (Array.isArray(d?.items) ? d.items : [])
      pagination.total = d?.total || (Array.isArray(list.value) ? list.value.length : 0)
      if (d?.current_page) pagination.page = d?.current_page
    } catch (e) {
      console.error('[loadList]', e)
      list.value = []
      pagination.total = 0
    } finally {
      loading.value = false
    }
  }

  async function loadStats() {
    try {
      const res: ApiResponse<ExpenseStats> = await get('/expenses/stats')
      stats.value = res?.data ?? null
    } catch (e) {
      console.warn('[loadStats]', e)
    }
  }

  // ===== V1.2.7g: 报销统计 =====
  const statsDateRange = ref<string[]>([])
  const statsGroupBy = ref<'user' | 'category' | 'project'>('user')
  const statsLoading = ref(false)
  const statsData = ref({ totalCount: 0, totalAmount: 0, paidAmount: 0, approvedCount: 0, approvedAmount: 0, pendingCount: 0, pendingAmount: 0 })
  const statsGroup = ref<Array<{ name: string; count: number; amount: number; months: number }>>([])

  function resetStatsDate() { statsDateRange.value = []; loadStats() }

  async function loadStatsBoard() {
    statsLoading.value = true
    try {
      const params: ExpenseStatsParams = { group_by: statsGroupBy.value }
      if (statsDateRange.value && statsDateRange.value.length === 2) {
        params.date_from = `${statsDateRange.value[0]}-01`
        // 末日取下月第 1 天 - 1 天
        const [y, m] = (statsDateRange.value[1] || '').split('-').map(Number)
        const last = new Date(y, m, 0).toISOString().slice(0, 10)
        params.date_to = last
      }
      const res: ApiResponse<{ summary?: typeof statsData.value; group?: Array<{ name: string; count: number; amount: number; months: number }> }> = await get('/expenses/stats-group', params)
      const d = res?.data ?? {}
      statsData.value = d.summary ?? statsData.value
      statsGroup.value = Array.isArray(d.group) ? d.group : []
    } catch (e) {
      console.error('[loadStatsBoard]', e)
      // 失败兜底: 直接从 list 聚合
      computeStatsFromList()
    } finally {
      statsLoading.value = false
    }
  }

  function computeStatsFromList() {
    // 兜底: 用已有 list 算 KPI
    const all = list.value
    const totalAmount = all.reduce((s, r) => s + (Number(r.total_amount) || 0), 0)
    const approved = all.filter(r => r.status === 'approved' || r.status === 'paid')
    const paid = all.filter(r => r.status === 'paid')
    const pending = all.filter(r => r.status === 'submitted')
    statsData.value = {
      totalCount: all.length,
      totalAmount,
      paidAmount: paid.reduce((s, r) => s + (Number(r.total_amount) || 0), 0),
      approvedCount: approved.length,
      approvedAmount: approved.reduce((s, r) => s + (Number(r.total_amount) || 0), 0),
      pendingCount: pending.length,
      pendingAmount: pending.reduce((s, r) => s + (Number(r.total_amount) || 0), 0),
    }
    // 按 user 分组
    const map = new Map<string, { name: string; count: number; amount: number; months: number; mset: Set<string> }>()
    for (const r of all) {
      const name = r.user?.name || '未知'
      const month = (r.created_at || '').slice(0, 7)
      if (!map.has(name)) map.set(name, { name, count: 0, amount: 0, months: 0, mset: new Set() })
      const it = map.get(name)!
      it.count++; it.amount += Number(r.total_amount) || 0
      if (month) it.mset.add(month)
    }
    statsGroup.value = Array.from(map.values())
      .map(it => ({ name: it.name, count: it.count, amount: it.amount, months: it.mset.size || 1 }))
      .sort((a, b) => b.amount - a.amount)
  }

  function resetSearch() {
    searchForm.value = { keyword: '', status: '', category: '' }
    loadList(1)
  }

  // ===== 详情 =====
  const showDetailDialog = ref(false)
  const detailRow = ref<ExpenseListItem | null>(null)
  const detailLoading = ref(false)

  async function handleView(row: ExpenseListItem) {
    detailRow.value = row
    showDetailDialog.value = true
    detailLoading.value = true
    try {
      const res: ApiResponse<ExpenseListItem> = await get(`/expenses/${row.id}`)
      detailRow.value = unwrapItem(res)
    } catch (e) {
      console.error('[handleView]', e)
    } finally {
      detailLoading.value = false
    }
  }

  const currentUserId = computed(() => userStore.userInfo?.id)

  function canCancel(row: ExpenseListItem) {
    if (!row) return false
    if (row.user_id && currentUserId.value && row.user_id !== currentUserId.value) return false
    return ['submitted', 'draft'].includes(row.status)
  }

  function canDelete(row: ExpenseListItem) {
    if (!row) return false
    if (['approved', 'paid'].includes(row.status)) return false
    if (row.user_id && currentUserId.value && row.user_id === currentUserId.value) return true
    return userStore.hasPermission('expense.delete')
  }

  function canPay(row: ExpenseListItem) {
    return row?.status === 'approved'
  }

  function handleDetailAction(action: 'cancel' | 'delete' | 'pay') {
    if (!detailRow.value) return
    if (action === 'cancel') handleCancel(detailRow.value)
    else if (action === 'delete') handleDelete(detailRow.value)
    else if (action === 'pay') handlePay(detailRow.value)
  }

  async function handleCancel(row: ExpenseListItem) {
    await ElMessageBox.confirm(
      `确认撤销报销单 ${row.claim_no}？撤销后可重新提交。`,
      '撤销确认',
      { type: 'warning', confirmButtonText: '确认撤销' }
    )
    try {
      await post(`/expenses/${row.id}/cancel`)
      ElMessage.success(`${row.claim_no} 已撤销`)
      loadList(pagination.page)
      loadStats()
      if (showDetailDialog.value) handleView(row)
    } catch (e: ApiError) {
      ElMessage.error(e?.response?.data?.message || e.message || '撤销失败')
    }
  }

  async function handleDelete(row: ExpenseListItem) {
    await ElMessageBox.confirm(
      `确认删除报销单 ${row.claim_no}？删除后不可恢复。`,
      '删除确认',
      { type: 'error', confirmButtonText: '确认删除' }
    )
    try {
      await del(`/expenses/${row.id}`)
      ElMessage.success(`${row.claim_no} 已删除`)
      loadList(pagination.page)
      loadStats()
    } catch (e: ApiError) {
      ElMessage.error(e?.response?.data?.message || e.message || '删除失败')
    }
  }

  // ===== 付款 =====
  const showPayDialog = ref(false)
  const payTarget = ref<ExpenseListItem | null>(null)
  const payLoading = ref(false)
  const payForm = reactive({ paid_amount: 0 })

  function handlePay(row: ExpenseListItem) {
    payTarget.value = row
    payForm.paid_amount = Number(row.total_amount || 0)
    showPayDialog.value = true
  }

  async function confirmPay() {
    if (payForm.paid_amount <= 0) {
      ElMessage.warning('请输入付款金额')
      return
    }
    payLoading.value = true
    try {
      await post(`/expenses/${payTarget.value.id}/pay`, { paid_amount: payForm.paid_amount })
      ElMessage.success(`${payTarget.value.claim_no} 已标记付款`)
      showPayDialog.value = false
      loadList(pagination.page)
      loadStats()
    } catch (e: ApiError) {
      ElMessage.error(e?.response?.data?.message || e.message || '付款失败')
    } finally {
      payLoading.value = false
    }
  }

  onMounted(async () => {
    await Promise.all([loadList(1), loadStats(), loadProjects()])
    // 处理 ?view=ID 自动打开详情
    const viewId = route.query.view as string
    if (viewId) {
      const row = list.value.find((r: ExpenseListItem) => String(r.id) === String(viewId) || r.claim_no === viewId)
      if (row) handleView(row)
    }
    // 处理 ?tab=apply 自动跳申请
    if (route.query.tab === 'apply') switchApply()
    // 处理 ?tab=stats 自动跳统计
    if (route.query.tab === 'stats') { activeTab.value = 'stats'; loadStatsBoard() }
  })

  // 监听 tab 变化, 切换到 stats 时加载统计板
  const onTabChange = (t: unknown) => { if (t === 'stats') loadStatsBoard() }

  return {
    activeTab, showApplyDialog, switchApply, onTabChange,
    statusOptions, categoryOptions, expenseStatusType,
    formatDate, formatMoney,
    searchForm, list, loading, pagination, stats,
    loadList, resetSearch,
    formRef, submitting, projectOptions, form, formRules, totalAmount,
    addItem, removeItem, resetForm, handleSubmit,
    statsDateRange, statsGroupBy, statsLoading, statsData, statsGroup,
    resetStatsDate, loadStatsBoard,
    showDetailDialog, detailRow, detailLoading, handleView,
    canCancel, canDelete, canPay, handleDetailAction,
    handleCancel, handleDelete,
    showPayDialog, payTarget, payLoading, payForm, handlePay, confirmPay,
    expenseCategoryLabel, commonStatusLabel,
  }
}
