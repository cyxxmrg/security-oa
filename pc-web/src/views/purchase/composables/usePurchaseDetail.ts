// 采购详情页 composable — 数据加载 / 状态管理 / 业务操作
// 从 PurchaseDetail.vue <script setup> 抽出, 主组件 + 子组件共享
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { purchase } from '@/api/modules'
import { unwrapList } from '@/utils/response'
import { purchaseFlow } from '@/api/purchase-flow'

// 采购订单
interface PurchaseOrder {
  id: number
  code?: string
  po_no?: string
  title?: string
  status: string
  total_amount?: number
  supplier_id?: number
  supplier?: { name?: string }
  created_at?: string
  contract_id?: number
  [key: string]: unknown
}

// 合同
interface PurchaseContract {
  id: number
  code?: string
  [key: string]: unknown
}

// 合同清单行
interface ContractItem {
  id: number
  material: string
  spec?: string
  qty: number
  unit?: string
  unit_price: number
  remark?: string
  [key: string]: unknown
}

// 合同附件
interface ContractFile {
  id: number
  name: string
  url: string
  [key: string]: unknown
}

// 付款申请
interface PaymentRequest {
  id: number
  status?: string
  _vouchers?: PaymentVoucher[]
  [key: string]: unknown
}

// 付款凭证
interface PaymentVoucher {
  id: number
  name: string
  url: string
  [key: string]: unknown
}

// 发货记录
interface ShippingItem {
  id?: number
  contract_item_id?: number | null
  expected_at?: string
  carrier?: string
  tracking_no?: string
  remark?: string
  status?: string
  [key: string]: unknown
}

// 库存物料（选择器返回）
interface InventoryPickItem {
  id: number
  name?: string
  spec?: string
  specification?: string
  unit?: string
}

// 上传回调参数
interface UploadOpts {
  file: File
  [key: string]: unknown
}

// API 列表响应
interface ListResponse<T> {
  data?: T[] | { items?: T[] }
  [key: string]: unknown
}

export function usePurchaseDetail() {
  const router = useRouter()
  // V0.6.4 招标联动 — 跳转到来源招标详情
  const goTender = (tenderId: number) => {
    router.push({ path: `/business/tender/${tenderId}` })
  }

  // ========== 订单列表 ==========
  const loadingOrders = ref(false)
  const orders = ref<PurchaseOrder[]>([])
  const searchForm = reactive({ keyword: '', status: '' })
  const page = ref(1)
  const pageSize = ref(10)

  const orderStatusOptions = [
    { value: 'draft', label: '草稿' },
    { value: 'pending', label: '待审批' },
    { value: 'approved', label: '已审批' },
    { value: 'fulfilled', label: '已完成' },
    { value: 'rejected', label: '已驳回' },
    { value: 'cancelled', label: '已取消' },
  ]
  const orderStatusLabel = (s: string) => orderStatusOptions.find(o => o.value === s)?.label || s || '-'
  const orderStatusTagType = (s: string): string => ({ draft: 'info', pending: 'warning', approved: 'success', fulfilled: 'success', rejected: 'danger', cancelled: 'info' } as Record<string, string>)[s] || ''

  const filteredOrders = computed(() => {
    let arr = [...orders.value]
    if (searchForm.keyword) {
      const kw = searchForm.keyword.toLowerCase()
      arr = arr.filter(o =>
        (o.code || '').toLowerCase().includes(kw) ||
        (o.po_no || '').toLowerCase().includes(kw) ||
        (o.title || '').toLowerCase().includes(kw) ||
        (o.supplier?.name || '').toLowerCase().includes(kw)
      )
    }
    if (searchForm.status) arr = arr.filter(o => o.status === searchForm.status)
    return arr.sort((a, b) => (b.id || 0) - (a.id || 0))
  })
  const pagedOrders = computed(() => {
    const start = (page.value - 1) * pageSize.value
    return filteredOrders.value.slice(start, start + pageSize.value)
  })

  const loadOrders = async () => {
    loadingOrders.value = true
    try {
      const res = await purchase.getOrders({ per_page: 200, page: 1 })
      // V0.6.3 不再解包, 兼容两种形态
      orders.value = unwrapList(res) as PurchaseOrder[]
    } catch (e) {
      orders.value = []
    } finally {
      loadingOrders.value = false
    }
  }

  const handleSearch = () => { page.value = 1 }
  const handleReset = () => { searchForm.keyword = ''; searchForm.status = ''; page.value = 1 }

  // ========== 选中订单 + Tabs ==========
  const currentOrder = ref<PurchaseOrder | null>(null)
  const activeTab = ref('basic')
  const loadingBasic = ref(false)

  const handleSelectOrder = (row: PurchaseOrder) => {
    currentOrder.value = row
    activeTab.value = 'basic'
    loadContract()
    loadShipping()
  }

  watch(() => currentOrder.value?.id, () => {
    contract.value = null
    contractItems.value = []
    contractFiles.value = []
    paymentRequests.value = []
    shippingList.value = []
    activePayReqIds.value = []
  })

  // ========== Tab 2: 合同 + 清单 + 附件 ==========
  const loadingContract = ref(false)
  const contract = ref<PurchaseContract | null>(null)
  const contractItems = ref<ContractItem[]>([])
  const contractFiles = ref<ContractFile[]>([])
  const editingItemId = ref<number | null>(null)
  const showAddItemDialog = ref(false)
  const savingItem = ref(false)
  const newItem = reactive({ inventory_item_id: null as number | null, material: '', spec: '', qty: 1, unit: '件', unit_price: 0, remark: '' })
  const contractItemPickerVisible = ref(false)

  const loadContract = async () => {
    if (!currentOrder.value) return
    loadingContract.value = true
    try {
      const contractId = currentOrder.value.contract_id
      if (!contractId) {
        contract.value = null
        contractItems.value = []
        contractFiles.value = []
        return
      }
      // 拉合同基本信息
      const cRes = await purchase.getContractDetail(contractId)
      contract.value = (cRes?.data || cRes) as PurchaseContract
      // 拉清单 + 附件
      await Promise.all([loadContractItems(), loadContractFiles()])
    } catch (e) {
      contract.value = null
    } finally {
      loadingContract.value = false
    }
  }

  const loadContractItems = async () => {
    if (!contract.value) return
    try {
      const r = await purchaseFlow.listContractItems(contract.value!.id) as unknown as ListResponse<ContractItem>
      contractItems.value = (r?.data as unknown as ContractItem[]) || []
    } catch {
      contractItems.value = []
    }
  }

  const loadContractFiles = async () => {
    if (!contract.value) return
    try {
      const r = await purchaseFlow.listContractFiles(contract.value!.id) as unknown as ListResponse<ContractFile>
      contractFiles.value = (r?.data as unknown as ContractFile[]) || []
    } catch {
      contractFiles.value = []
    }
  }

  const handleSaveContractItem = async (row: ContractItem) => {
    try {
      await purchaseFlow.updateContractItem(contract.value!.id, row.id, { unit_price: row.unit_price })
      ElMessage.success('已更新单价')
      editingItemId.value = null
      await loadContractItems()
    } catch { /* 拦截器已提示 */ }
  }

  const handleAddContractItem = () => {
    Object.assign(newItem, { inventory_item_id: null, material: '', spec: '', qty: 1, unit: '件', unit_price: 0, remark: '' })
    showAddItemDialog.value = true
  }

  const handleContractInventorySelected = (items: InventoryPickItem[]) => {
    const item = items[0]
    if (!item) return
    newItem.inventory_item_id = item.id
    newItem.material = item.name || ''
    newItem.spec = item.spec || item.specification || ''
    newItem.unit = item.unit || '件'
    contractItemPickerVisible.value = false
  }

  const handleSaveNewItem = async () => {
    if (!newItem.inventory_item_id || !newItem.material || !newItem.qty) {
      ElMessage.warning('请选择库存物料并填写数量')
      return
    }
    savingItem.value = true
    try {
      await purchaseFlow.addContractItem(contract.value!.id, { ...newItem })
      ElMessage.success('已添加')
      showAddItemDialog.value = false
      await loadContractItems()
    } catch { /* 拦截器已提示 */ }
    finally { savingItem.value = false }
  }

  const handleDeleteContractItem = async (row: ContractItem) => {
    try { await ElMessageBox.confirm(`确定删除清单行「${row.material}」？`, '删除确认', { type: 'warning' }) } catch { return }
    try {
      await purchaseFlow.deleteContractItem(contract.value!.id, row.id)
      ElMessage.success('已删除')
      await loadContractItems()
    } catch { /* 拦截器已提示 */ }
  }

  // 合同附件上传
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const handleBeforeUploadContractFile = (file: { size: number; name?: string }) => {
    const maxSize = 20 * 1024 * 1024
    if (file.size > maxSize) {
      ElMessage.error('文件大小不能超过 20MB')
      return false
    }
    return true
  }
  const handleUploadContractFile = async (opts: UploadOpts) => {
    if (!contract.value) {
      ElMessage.warning('合同未创建')
      return
    }
    const fd = new FormData()
    fd.append('file', opts.file)
    try {
      await purchaseFlow.uploadContractFile(contract.value!.id, fd)
      ElMessage.success('附件已上传')
      await loadContractFiles()
    } catch { /* 拦截器已提示 */ }
  }
  const handlePreviewFile = (row: ContractFile) => {
    window.open(row.url, '_blank')
  }
  const handleDownloadFile = async (row: ContractFile) => {
    try {
      const a = document.createElement('a')
      a.href = row.url
      a.download = row.name
      a.target = '_blank'
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
    } catch { ElMessage.error('下载失败') }
  }
  const handleDeleteContractFile = async (row: ContractFile) => {
    try { await ElMessageBox.confirm(`确定删除附件「${row.name}」？`, '删除确认', { type: 'warning' }) } catch { return }
    try {
      await purchaseFlow.deleteContractFile(contract.value!.id, row.id)
      ElMessage.success('已删除')
      await loadContractFiles()
    } catch { /* 拦截器已提示 */ }
  }

  // ========== Tab 3: 付款 ==========
  const loadingPayments = ref(false)
  const paymentRequests = ref<PaymentRequest[]>([])
  const activePayReqIds = ref<number[]>([])

  const paymentStatusTagType = (s: string): string => ({ pending: 'warning', approved: 'success', paid: 'success', rejected: 'danger' } as Record<string, string>)[s] || 'info'

  const loadPayments = async () => {
    if (!contract.value) {
      paymentRequests.value = []
      return
    }
    loadingPayments.value = true
    try {
      // 用现有 purchase.getPaymentRequests 按 contract_id 过滤
      const r = await purchase.getPaymentRequests({ contract_id: contract.value!.id, per_page: 100 }) as ListResponse<PaymentRequest>
      const arr = ((r?.data as { items?: PaymentRequest[] })?.items) || (r?.data as PaymentRequest[]) || []
      paymentRequests.value = arr
      // 自动展开
      activePayReqIds.value = arr.map((p: PaymentRequest) => p.id)
      // 拉凭证
      for (const pr of arr) {
        try {
          const vr = await purchaseFlow.listPaymentVouchers(pr.id) as unknown as ListResponse<PaymentVoucher>
          pr._vouchers = (vr?.data as PaymentVoucher[]) || []
        } catch { pr._vouchers = [] }
      }
    } catch {
      paymentRequests.value = []
    } finally {
      loadingPayments.value = false
    }
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const handleBeforeUploadVoucher = (file: { size: number; name?: string }, _prId: number) => {
    const maxSize = 20 * 1024 * 1024
    if (file.size > maxSize) {
      ElMessage.error('文件大小不能超过 20MB')
      return false
    }
    return true
  }
  const handleUploadVoucher = async (opts: UploadOpts, prId: number) => {
    const fd = new FormData()
    fd.append('file', opts.file)
    try {
      await purchaseFlow.uploadPaymentVoucher(prId, fd)
      ElMessage.success('凭证已上传')
      await loadPayments()
    } catch { /* 拦截器已提示 */ }
  }
  const handlePreviewVoucher = (row: PaymentVoucher) => { window.open(row.url, '_blank') }
  const handleDownloadVoucher = (row: PaymentVoucher) => handleDownloadFile(row)

  // ========== Tab 4: 发货 ==========
  const loadingShipping = ref(false)
  const shippingList = ref<ShippingItem[]>([])
  const showAddShippingDialog = ref(false)
  const savingShipping = ref(false)
  const newShipping = reactive({ contract_item_id: null as number | null, expected_at: '', carrier: '', tracking_no: '', remark: '' })

  const loadShipping = async () => {
    if (!contract.value) {
      shippingList.value = []
      return
    }
    loadingShipping.value = true
    try {
      const r = await purchaseFlow.listShipping(contract.value!.id) as unknown as ListResponse<ShippingItem>
      shippingList.value = (r?.data as ShippingItem[]) || []
    } catch {
      shippingList.value = []
    } finally {
      loadingShipping.value = false
    }
  }

  const handleAddShipping = () => {
    Object.assign(newShipping, { contract_item_id: null, expected_at: '', carrier: '', tracking_no: '', remark: '' })
    showAddShippingDialog.value = true
  }

  const handleSaveShipping = async () => {
    savingShipping.value = true
    try {
      await purchaseFlow.setShippingPlan(contract.value!.id, { ...newShipping })
      ElMessage.success('已添加')
      showAddShippingDialog.value = false
      await loadShipping()
    } catch { /* 拦截器已提示 */ }
    finally { savingShipping.value = false }
  }

  const shippingStatusType = (s: string): string => ({ planned: 'info', shipped: 'warning', in_transit: 'warning', arrived: 'success', received: 'success' } as Record<string, string>)[s] || ''

  // ========== 工具 ==========
  const formatMoney = (n: number) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })

  // ========== 监听 Tab 切换 — 切换时按需加载 ==========
  watch(activeTab, (tab) => {
    if (!currentOrder.value) return
    if (tab === 'contract' && !contract.value && currentOrder.value.contract_id) {
      loadContract()
    } else if (tab === 'payment' && paymentRequests.value.length === 0 && contract.value) {
      loadPayments()
    } else if (tab === 'shipping' && shippingList.value.length === 0 && contract.value) {
      loadShipping()
    } else if (tab === 'contract' && contract.value) {
      loadContractItems()
      loadContractFiles()
    }
  })

  onMounted(() => { loadOrders() })

  return {
    // 订单列表
    loadingOrders, orders, searchForm, page, pageSize,
    orderStatusOptions, orderStatusLabel, orderStatusTagType,
    filteredOrders, pagedOrders, loadOrders, handleSearch, handleReset,
    // 选中订单 + tabs
    currentOrder, activeTab, loadingBasic, handleSelectOrder,
    // 合同
    loadingContract, contract, contractItems, contractFiles,
    editingItemId, showAddItemDialog, savingItem, newItem, contractItemPickerVisible,
    loadContract, loadContractItems, loadContractFiles,
    handleSaveContractItem, handleAddContractItem, handleContractInventorySelected,
    handleSaveNewItem, handleDeleteContractItem,
    handleBeforeUploadContractFile, handleUploadContractFile,
    handlePreviewFile, handleDownloadFile, handleDeleteContractFile,
    // 付款
    loadingPayments, paymentRequests, activePayReqIds, paymentStatusTagType,
    loadPayments, handleBeforeUploadVoucher, handleUploadVoucher,
    handlePreviewVoucher, handleDownloadVoucher,
    // 发货
    loadingShipping, shippingList, showAddShippingDialog, savingShipping, newShipping,
    loadShipping, handleAddShipping, handleSaveShipping, shippingStatusType,
    // 工具
    formatMoney, goTender,
  }
}
