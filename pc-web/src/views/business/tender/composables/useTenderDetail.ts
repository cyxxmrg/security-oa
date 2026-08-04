// 招标详情页 composable — 数据加载 / 状态管理 / 业务操作
// 从 Detail.vue <script setup> 抽出, 主组件 + 子组件共享
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { openAuthenticatedFile } from '@/utils/privateFile'
import { tender } from '@/api/tender'
import type {
  TenderProject, TenderBid, TenderAttachment, TenderDeposit, TenderDepositRule,
} from '@/api/tender'
import { unwrapList, unwrapItem, unwrapStats } from '@/utils/response'

export function useTenderDetail() {
  const route = useRoute()
  const router = useRouter()
  const id = computed(() => route.params.id as string)

  // ============== 数据状态 ==============
  const detail = ref<TenderProject | null>(null)
  const bids = ref<TenderBid[]>([])
  const attachments = ref<TenderAttachment[]>([])
  const downstream = ref<Record<string, unknown> | null>(null)
  const deposits = ref<TenderDeposit[]>([])
  const depositRule = ref<TenderDepositRule | null>(null)
  const depositSummary = ref<Record<string, unknown>>({})
  const loading = ref(false)
  const loadingBids = ref(false)
  const loadingDeposits = ref(false)
  const activeTab = ref('basic')

  // 弹窗显隐
  const showCompare = ref(false)
  const showEvaluate = ref(false)
  const showAward = ref(false)
  const showBidDetail = ref(false)
  const showEdit = ref(false)
  const currentBid = ref<TenderBid | null>(null)
  const defaultAwardBidId = ref<number | undefined>()

  // V0.6.5 Sprint 4: 状态机弹窗控制
  const showRejectDialog = ref(false)
  const showWithdrawDialog = ref(false)
  const showCancelV2Dialog = ref(false)
  const rejectForm = ref({ reason: '', backToDraft: true })
  const withdrawForm = ref({ reason: '' })
  const cancelV2Form = ref({ reason: '' })

  // V0.6.5 Sprint 4: 保证金弹窗控制
  const showDepositRuleDialog = ref(false)
  const showRefundDialog = ref(false)
  const showForfeitDialog = ref(false)
  const depositRuleForm = ref<Record<string, unknown>>({
    required: true,
    amount: 5000,
    deadline_hours_before_open: 24,
    refund_policy: { auto_refund_days: 7, forfeit_on_no_contract_sign_days: 14 },
    bank_account: '',
    note: '',
  })
  const refundForm = ref<Record<string, unknown>>({
    depositId: 0, supplierName: '', originalAmount: 0,
    refund_amount: 0, method: 'bank_transfer', reason: '',
  })
  const forfeitForm = ref<Record<string, unknown>>({ depositId: 0, supplierName: '', amount: 0, reason: '' })

  // ============== 计算属性 ==============
  const publicUrl = computed(() => {
    if (!detail.value?.public_token) return ''
    return `${window.location.origin}/portal/tender/${detail.value.public_token}`
  })

  // 状态机权限 — V0.6.5 Sprint 4
  const canEdit = computed(() => detail.value?.status === 'draft')
  const canSubmitReview = computed(() => detail.value?.status === 'draft')
  const canApprove = computed(() => detail.value?.status === 'pending_review')
  const canReject = computed(() => detail.value?.status === 'pending_review')
  const canWithdraw = computed(() => detail.value?.status === 'open' && bids.value.length === 0)
  const canCancelV2 = computed(() => ['draft', 'pending_review', 'open'].includes(detail.value?.status || ''))
  const canEditDepositRule = computed(() =>
    detail.value && !['closed', 'cancelled', 'withdrawn'].includes(detail.value.status))

  // V0.6.0 旧按钮 (向后兼容, 标记「旧」字样)
  const canPublish = computed(() => detail.value?.status === 'draft')
  const canEvaluate = computed(() => detail.value?.status === 'open')
  const canAward = computed(() => detail.value?.status === 'open')
  const canClose = computed(() => ['open', 'closed'].includes(detail.value?.status || ''))
  const canCancel = computed(() => ['draft', 'open'].includes(detail.value?.status || ''))

  // ============== 数据加载 ==============
  const loadDetail = async () => {
    loading.value = true
    try {
      const res = await tender.get(Number(id.value))
      detail.value = unwrapItem<TenderProject>(res)
    } finally { loading.value = false }
  }

  const loadBids = async () => {
    loadingBids.value = true
    try {
      const res = await tender.listBids(Number(id.value))
      bids.value = unwrapList(res) as TenderBid[]
    } finally { loadingBids.value = false }
  }

  const loadAttachments = async () => {
    const res = await tender.listAttachments(Number(id.value))
    attachments.value = unwrapList(res) as TenderAttachment[]
  }

  // V0.6.4 招标联动 — 联查下游 (PO + Payable + 入库)
  const loadDownstream = async () => {
    try {
      const res = await tender.downstream(Number(id.value))
      downstream.value = unwrapItem(res)
    } catch {
      downstream.value = null
    }
  }

  // V0.6.5 Sprint 4: 加载保证金
  const loadDeposits = async () => {
    loadingDeposits.value = true
    try {
      const res = await tender.listDeposits(Number(id.value))
      const payload = unwrapStats<{ rule?: Record<string, unknown>; deposits?: Record<string, unknown>[]; summary?: Record<string, unknown> }>(res)
      depositRule.value = payload?.rule || null
      deposits.value = Array.isArray(payload?.deposits) ? payload.deposits : []
      depositSummary.value = payload?.summary || {}
    } catch {
      deposits.value = []
      depositRule.value = null
      depositSummary.value = {}
    } finally {
      loadingDeposits.value = false
    }
  }

  const loadAll = async () => {
    await Promise.all([loadDetail(), loadBids(), loadAttachments(), loadDeposits()])
    if (['awarded', 'closed'].includes(detail.value?.status || '')) {
      await loadDownstream()
    } else {
      downstream.value = null
    }
  }

  // ============== 路由 / 通用 ==============
  const goPurchaseDetail = (poId: number) => {
    router.push({ path: `/purchase/purchase-detail/${poId}` })
  }

  const goBack = () => router.push({ name: 'BusinessTender' })

  const copyUrl = async () => {
    if (!publicUrl.value) return
    try {
      await navigator.clipboard.writeText(publicUrl.value)
      ElMessage.success('公开链接已复制, 可发送给受邀供应商')
    } catch { ElMessage.warning('复制失败, 请手动选取') }
  }

  // ============== 附件 ==============
  const openFile = async (att: TenderAttachment) => {
    if (!att.url) return
    try { await openAuthenticatedFile(att.url, att.file_name) } catch { ElMessage.error('附件打开失败') }
  }

  const beforeUpload = (file: File) => {
    if (file.size > 50 * 1024 * 1024) {
      ElMessage.error('文件超过 50MB')
      return false
    }
    return true
  }

  const onUpload = async (opt: Record<string, unknown>) => {
    const fd = new FormData()
    fd.append('file', opt.file)
    fd.append('category', 'tender_doc')
    fd.append('visibility', 'public')
    try {
      await tender.uploadAttachment(Number(id.value), fd)
      ElMessage.success('已上传')
      await loadAttachments()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '上传失败')
    }
  }

  const onDeleteAtt = async (att: TenderAttachment) => {
    try { await ElMessageBox.confirm(`确认删除「${att.file_name}」?`, '删除确认', { type: 'warning' }) } catch { return }
    await tender.deleteAttachment(Number(id.value), att.id)
    ElMessage.success('已删除')
    await loadAttachments()
  }

  // ============== 编辑 / 状态机 ==============
  const onEdit = () => { showEdit.value = true }

  // V0.6.5 Sprint 4: 状态机操作
  const onSubmitReview = async () => {
    try { await ElMessageBox.confirm('提交后进入待审核状态, 需审核人通过才能发布。确认?', '提交审核', { type: 'warning' }) } catch { return }
    try {
      await tender.submitReview(Number(id.value))
      ElMessage.success('已提交审核')
      await loadAll()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '提交失败')
    }
  }
  const onApprove = async () => {
    try { await ElMessageBox.confirm('审核通过后招标将正式发布, 供应商可投标。确认?', '审核通过', { type: 'success' }) } catch { return }
    try {
      await tender.approve(Number(id.value))
      ElMessage.success('审核通过, 已发布')
      await loadAll()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '审核失败')
    }
  }
  const onConfirmReject = async () => {
    if (!rejectForm.value.reason?.trim()) {
      ElMessage.warning('请填写驳回原因')
      return
    }
    try {
      await tender.reject(Number(id.value), rejectForm.value.reason, rejectForm.value.backToDraft)
      ElMessage.success('已驳回')
      showRejectDialog.value = false
      rejectForm.value = { reason: '', backToDraft: true }
      await loadAll()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '驳回失败')
    }
  }
  const onConfirmWithdraw = async () => {
    if (!withdrawForm.value.reason?.trim()) {
      ElMessage.warning('请填写撤回原因')
      return
    }
    try {
      await tender.withdraw(Number(id.value), withdrawForm.value.reason)
      ElMessage.success('已撤回')
      showWithdrawDialog.value = false
      withdrawForm.value = { reason: '' }
      await loadAll()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '撤回失败')
    }
  }
  const onConfirmCancelV2 = async () => {
    if (!cancelV2Form.value.reason?.trim()) {
      ElMessage.warning('请填写废标原因')
      return
    }
    try {
      await tender.cancelV2(Number(id.value), cancelV2Form.value.reason)
      ElMessage.success('已废标')
      showCancelV2Dialog.value = false
      cancelV2Form.value = { reason: '' }
      await loadAll()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '废标失败')
    }
  }

  // ============== 保证金操作 ==============
  const onSaveDepositRule = async () => {
    if (!depositRuleForm.value.amount || depositRuleForm.value.amount <= 0) {
      ElMessage.warning('请填写保证金金额')
      return
    }
    try {
      await tender.setDepositRule(Number(id.value), depositRuleForm.value)
      ElMessage.success('已设置保证金规则')
      showDepositRuleDialog.value = false
      await loadDeposits()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '保存失败')
    }
  }
  const onMarkPaid = async (row: TenderDeposit) => {
    try { await ElMessageBox.confirm(`确认「${row.supplier?.name}」已缴纳 ¥${Number(row.amount).toLocaleString()} 保证金?`, '确认收款', { type: 'success' }) } catch { return }
    try {
      await tender.markDepositPaid(Number(id.value), row.id)
      ElMessage.success('已确认收款')
      await loadDeposits()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '操作失败')
    }
  }
  const onRefund = (row: TenderDeposit) => {
    refundForm.value = {
      depositId: row.id,
      supplierName: row.supplier?.name || '',
      originalAmount: row.amount,
      refund_amount: row.amount,
      method: 'bank_transfer',
      reason: '招标未中标, 自动退还',
    }
    showRefundDialog.value = true
  }
  const onConfirmRefund = async () => {
    if (!refundForm.value.reason?.trim()) {
      ElMessage.warning('请填写退款原因')
      return
    }
    try {
      await tender.refundDeposit(Number(id.value), refundForm.value.depositId, {
        refund_amount: Number(refundForm.value.refund_amount),
        method: refundForm.value.method,
        reason: refundForm.value.reason,
      })
      ElMessage.success('已退还')
      showRefundDialog.value = false
      await loadDeposits()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '退款失败')
    }
  }
  const onForfeit = (row: TenderDeposit) => {
    forfeitForm.value = {
      depositId: row.id,
      supplierName: row.supplier?.name || '',
      amount: row.amount,
      reason: '',
    }
    showForfeitDialog.value = true
  }
  const onConfirmForfeit = async () => {
    if (!forfeitForm.value.reason?.trim()) {
      ElMessage.warning('请填写没收原因')
      return
    }
    try {
      await tender.forfeitDeposit(Number(id.value), forfeitForm.value.depositId, forfeitForm.value.reason)
      ElMessage.success('已没收')
      showForfeitDialog.value = false
      await loadDeposits()
    } catch (e: unknown) {
      ElMessage.error(e?.message || '操作失败')
    }
  }

  // ============== V0.6.0 旧按钮 (向后兼容) ==============
  const onPublish = async () => {
    try { await ElMessageBox.confirm('发布后不可回退到草稿, 确认?', '发布确认', { type: 'success' }) } catch { return }
    await tender.publish(Number(id.value))
    ElMessage.success('已发布')
    await loadAll()
  }
  const onClose = async () => {
    try { await ElMessageBox.confirm('关闭后供应商无法继续投标, 确认?', '关闭确认', { type: 'warning' }) } catch { return }
    await tender.close(Number(id.value))
    ElMessage.success('已关闭')
    await loadAll()
  }
  const onCancel = async () => {
    try { await ElMessageBox.confirm('取消后不可恢复, 确认?', '取消确认', { type: 'warning' }) } catch { return }
    await tender.cancel(Number(id.value))
    ElMessage.success('已取消')
    await loadAll()
  }

  // ============== 投标 ==============
  const viewBid = (b: TenderBid) => { currentBid.value = b; showBidDetail.value = true }
  const quickAward = (b: TenderBid) => { defaultAwardBidId.value = b.id; showAward.value = true }
  const onAwarded = async () => {
    showAward.value = false
    await loadAll()
    if (downstream.value?.summary?.has_po) {
      activeTab.value = 'downstream'
    }
  }

  onMounted(loadAll)

  return {
    // 路由
    id,
    // 数据状态
    detail, bids, attachments, downstream, deposits, depositRule, depositSummary,
    loading, loadingBids, loadingDeposits, activeTab,
    // 弹窗状态
    showCompare, showEvaluate, showAward, showBidDetail, showEdit,
    currentBid, defaultAwardBidId,
    showRejectDialog, showWithdrawDialog, showCancelV2Dialog,
    rejectForm, withdrawForm, cancelV2Form,
    showDepositRuleDialog, showRefundDialog, showForfeitDialog,
    depositRuleForm, refundForm, forfeitForm,
    // 计算属性
    publicUrl, canEdit, canSubmitReview, canApprove, canReject,
    canWithdraw, canCancelV2, canEditDepositRule,
    canPublish, canEvaluate, canAward, canClose, canCancel,
    // 数据加载
    loadDetail, loadBids, loadAttachments, loadDownstream, loadDeposits, loadAll,
    // 路由 / 通用
    goBack, goPurchaseDetail, copyUrl,
    // 附件
    openFile, beforeUpload, onUpload, onDeleteAtt,
    // 编辑 / 状态机
    onEdit, onSubmitReview, onApprove, onConfirmReject, onConfirmWithdraw, onConfirmCancelV2,
    // 保证金
    onSaveDepositRule, onMarkPaid, onRefund, onConfirmRefund, onForfeit, onConfirmForfeit,
    // 旧按钮
    onPublish, onClose, onCancel,
    // 投标
    viewBid, quickAward, onAwarded,
  }
}
