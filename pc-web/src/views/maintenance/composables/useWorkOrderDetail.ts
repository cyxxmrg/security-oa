// 工单详情页 composable — 数据加载 / 状态管理 / 业务操作
// 从 WorkOrderDetail.vue <script setup> 抽出, 主组件 + 子组件共享
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { get, post, put } from '@/utils/request'
import { unwrapList, unwrapItem } from '@/utils/response'
import { purchaseFlow } from '@/api/purchase-flow'

export function useWorkOrderDetail() {
  const route = useRoute()
  const router = useRouter()
  const id = Number(route.params.id)

  const wo = ref<Record<string, unknown> | null>(null)
  const activeTab = ref('basic')
  const engineers = ref<Record<string, unknown>[]>([])

  // 转返修
  const convertVisible = ref(false)
  const converting = ref(false)
  const convertForm = ref({ reason: '', method_type: '', expected_finish_at: '' })

  // 派单
  const assignVisible = ref(false)
  const assigning = ref(false)
  const assignForm = ref({ engineer_id: null as number | null, note: '' })

  const formatDate = (s: string) => {
    if (!s) return ''
    const d = new Date(s)
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
  }

  const timeline = computed(() => {
    if (!wo.value) return []
    const arr: Record<string, unknown>[] = []
    if (wo.value.created_at) arr.push({ type: 'primary', time: formatDate(wo.value.created_at), title: '工单创建', desc: '接单登记' })
    if (wo.value.scheduled_at) arr.push({ type: 'info', time: formatDate(wo.value.scheduled_at), title: '预约时间', desc: '' })
    if (wo.value.started_at) arr.push({ type: 'warning', time: formatDate(wo.value.started_at), title: '开始服务', desc: `工程师: ${wo.value.assignee_name}` })
    if (wo.value.completed_at) arr.push({ type: 'success', time: formatDate(wo.value.completed_at), title: wo.value.status === 'converted_to_repair' ? '已转返修' : '已完成', desc: wo.value.result_notes || '' })
    return arr
  })

  // V0.6.3: 发起采购需求
  const showPurchaseDialog = ref(false)
  const submittingPurchase = ref(false)
  const purchaseForm = ref({
    inventory_item_id: null as number | null,
    material: '',
    spec: '',
    quantity: 1,
    unit: '个',
    need_date: '',
    priority: 'normal',
    remark: '',
  })

  const openPurchaseDialog = () => {
    purchaseForm.value = {
      inventory_item_id: null,
      material: '',
      spec: '',
      quantity: 1,
      unit: '个',
      need_date: new Date().toISOString().slice(0, 10),
      priority: 'normal',
      remark: `工单 ${wo.value?.code} 完成, 需采购`,
    }
    showPurchaseDialog.value = true
  }

  const purchasePickerVisible = ref(false)
  const handlePurchaseItemSelected = (items: Record<string, unknown>[]) => {
    const item = items[0]
    if (!item) return
    purchaseForm.value.inventory_item_id = item.id
    purchaseForm.value.material = item.name || ''
    purchaseForm.value.spec = item.spec || item.specification || ''
    purchaseForm.value.unit = item.unit || '个'
    purchasePickerVisible.value = false
  }

  const submitPurchase = async () => {
    if (!purchaseForm.value.inventory_item_id || !purchaseForm.value.material) {
      ElMessage.warning('请选择库存物料')
      return
    }
    submittingPurchase.value = true
    try {
      const r = await purchaseFlow.fromWorkOrder(wo.value.id, purchaseForm.value)
      if (r.code === 0) {
        ElMessage.success('采购需求已创建, 进入采购员审核流')
        showPurchaseDialog.value = false
        // 跳到采购需求详情
        router.push(`/purchase-collab/requirement?highlight=${r.data.id}`)
      } else {
        ElMessage.error(r.message || '创建失败')
      }
    } catch (e: unknown) {
      ElMessage.error(e?.response?.data?.message || '创建失败')
    } finally {
      submittingPurchase.value = false
    }
  }

  const loadData = async () => {
    try {
      // V0.6.3: res = {code, data: <entity>}
      const res = await get(`/work-orders/${id}`)
      wo.value = unwrapItem(res) || {}
    } catch (e: unknown) {
      ElMessage.error('加载失败: ' + (e?.message || ''))
    }
  }

  const loadEngineers = async () => {
    try {
      // 简化为所有 active 用户 — /users 返回 paginator
      const res = await get('/users', { per_page: 100 })
      engineers.value = unwrapList(res)
    } catch { engineers.value = [] }
  }

  const onAssign = () => { assignForm.value = { engineer_id: null, note: '' }; assignVisible.value = true }
  const onAssignConfirm = async () => {
    if (!assignForm.value.engineer_id) return ElMessage.warning('请选工程师')
    assigning.value = true
    try {
      await post(`/work-orders/${id}/assign`, assignForm.value)
      ElMessage.success('已派单')
      assignVisible.value = false
      await loadData()
    } catch (e: unknown) { ElMessage.error(e?.message || '派单失败') }
    finally { assigning.value = false }
  }

  const onStart = async () => {
    await ElMessageBox.confirm('开始服务?', '确认', { type: 'info' }).catch(() => null)
    if (!arguments[0]) return  // 用户取消
    try {
      await post(`/work-orders/${id}/start`)
      ElMessage.success('已开始')
      await loadData()
    } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
  }

  const resolveVisible = ref(false)
  const resolveForm = ref({ result_notes: '', service_fee: 0, parts_cost: 0, customer_signature: '' })
  const resolving = ref(false)

  const onResolve = () => {
    resolveForm.value = { result_notes: '', service_fee: 0, parts_cost: 0, customer_signature: '' }
    resolveVisible.value = true
    // 等 dialog 渲染完再清 canvas
    setTimeout(() => clearSignature(), 100)
  }

  const sigCanvas = ref<HTMLCanvasElement | null>(null)
  const drawing = ref(false)

  const startDraw = (e: MouseEvent) => {
    drawing.value = true
    const ctx = sigCanvas.value?.getContext('2d')
    if (!ctx || !sigCanvas.value) return
    const rect = sigCanvas.value.getBoundingClientRect()
    ctx.beginPath()
    ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top)
  }

  const draw = (e: MouseEvent) => {
    if (!drawing.value) return
    const ctx = sigCanvas.value?.getContext('2d')
    if (!ctx || !sigCanvas.value) return
    const rect = sigCanvas.value.getBoundingClientRect()
    ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top)
    ctx.stroke()
  }

  const endDraw = () => { drawing.value = false }

  const clearSignature = () => {
    const ctx = sigCanvas.value?.getContext('2d')
    if (ctx && sigCanvas.value) ctx.clearRect(0, 0, sigCanvas.value.width, sigCanvas.value.height)
  }

  const saveSignature = () => {
    if (!sigCanvas.value) return ''
    return sigCanvas.value.toDataURL('image/png')
  }

  const onResolveConfirm = async () => {
    if (!resolveForm.value.result_notes) return ElMessage.warning('请填处理结果')
    // 上门服务必须签字
    if (wo.value?.service_type === 'on_site' && !resolveForm.value.customer_signature) {
      return ElMessage.warning('上门服务必须提供客户签字')
    }
    resolving.value = true
    try {
      if (wo.value?.service_type === 'on_site') {
        resolveForm.value.customer_signature = saveSignature()
      }
      await post(`/work-orders/${id}/resolve`, resolveForm.value)
      ElMessage.success('已完成')
      resolveVisible.value = false
      await loadData()
    } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
    finally { resolving.value = false }
  }

  const onCancel = async () => {
    const { value } = await ElMessageBox.prompt('请输入取消原因', '取消工单', { inputType: 'textarea' }).catch(() => null)
    if (!value) return
    try {
      await post(`/work-orders/${id}/cancel`, { reason: value })
      ElMessage.success('已取消')
      await loadData()
    } catch (e: unknown) { ElMessage.error(e?.message || '失败') }
  }

  const onConvert = async () => {
    try {
      await ElMessageBox.confirm(
        '转为返修后将自动生成返修单, 原工单状态变为「已转返修」且不可再编辑。\n\n请确认此工单需要返厂或退回处理?',
        '⚠️ 二次确认',
        {
          type: 'warning',
          confirmButtonText: '确认转返修',
          cancelButtonText: '再想想',
          confirmButtonClass: 'el-button--danger',
        }
      )
    } catch {
      return
    }
    convertForm.value = { reason: '', method_type: '', expected_finish_at: '' }
    convertVisible.value = true
  }

  const onConvertConfirm = async () => {
    if (!convertForm.value.reason) return ElMessage.warning('请填原因')
    converting.value = true
    try {
      const res = await post(`/work-orders/${id}/convert-to-repair`, convertForm.value)
      // request.ts 不解包, res = {code, message, data: {repair_order, work_order}}
      const result = unwrapItem(res) || {}
      const repairOrder = result?.repair_order || result
      ElMessage.success(`已转为返修单 ${repairOrder?.code || '新'}`)
      convertVisible.value = false
      if (repairOrder?.id) router.push(`/maintenance/repairs/${repairOrder.id}`)
    } catch (e: unknown) {
      ElMessage.error(e?.message || '转返修失败')
    } finally { converting.value = false }
  }

  onMounted(() => {
    loadData()
    loadEngineers()
  })

  return {
    id, wo, activeTab, engineers,
    convertVisible, converting, convertForm,
    assignVisible, assigning, assignForm,
    timeline, formatDate,
    showPurchaseDialog, submittingPurchase, purchaseForm, purchasePickerVisible,
    openPurchaseDialog, handlePurchaseItemSelected, submitPurchase,
    loadData, loadEngineers,
    onAssign, onAssignConfirm, onStart,
    resolveVisible, resolveForm, resolving, onResolve, onResolveConfirm,
    sigCanvas, drawing, startDraw, draw, endDraw, clearSignature, saveSignature,
    onCancel, onConvert, onConvertConfirm,
  }
}
