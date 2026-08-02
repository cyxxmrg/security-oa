import { get, post, put, del } from '@/utils/request'

// 通用 API 负载类型 — 替代 any, 强制调用方做类型守卫
export type ApiPayload = Record<string, unknown>;

// 客户
export function getCustomerList(params?: Record<string, unknown>) { return get('/customers', params) }
export function getCustomerDetail(id: number) { return get(`/customers/${id}`) }
export function createCustomer(data: unknown) { return post('/customers', data) }
export function updateCustomer(id: number, data: unknown) { return put(`/customers/${id}`, data) }
export function deleteCustomer(id: number) { return del(`/customers/${id}`) }
export function getCustomerMap() { return get('/customers/map') }
export function getCustomerFollow(id: number, params?: Record<string, unknown>) { return get(`/customers/${id}/follows`, params) }
export function addCustomerFollow(id: number, data: unknown) { return post(`/customers/${id}/follows`, data) }
export function getFollowCalendar(month: string, userId?: number, customerId?: number) {
  return get('/follow-ups/calendar', { month, user_id: userId, customer_id: customerId })
}
export function getUserList(params?: Record<string, unknown>) { return get('/users', params) }

// 项目
export function getProjectList(params?: Record<string, unknown>) { return get('/projects', params) }
export function getProjectDetail(id: number) { return get(`/projects/${id}`) }
export function createProject(data: unknown) { return post('/projects', data) }
export function updateProject(id: number, data: unknown) { return put(`/projects/${id}`, data) }
export function deleteProject(id: number) { return del(`/projects/${id}`) }
export function getProjectGantt(id: number) { return get(`/projects/${id}/gantt`) }
export function updateProjectStage(id: number, data: unknown) { return put(`/projects/${id}/stage`, data) }

// 售后
// 考勤
export function getAttendanceList(params?: Record<string, unknown>) { return get('/attendance', params) }
export function getAttendanceStats(params?: Record<string, unknown>) { return get('/attendance/stats', params) }
export function clockIn(data: unknown) { return post('/attendance/clock-in', data) }
export function clockOut(data: unknown) { return post('/attendance/clock-out', data) }
export function applyLeave(data: unknown) { return post('/attendance/leave', data) }
export function getLeaveList(params?: Record<string, unknown>) { return get('/attendance/leave', params) }

// ====== 排班 ======
export const schedule = {
  // 班次
  listShifts:    () => get('/schedules/shifts'),
  createShift:   (data: unknown) => post('/schedules/shifts', data),
  updateShift:   (id: number, data: unknown) => put(`/schedules/shifts/${id}`, data),
  deleteShift:   (id: number) => del(`/schedules/shifts/${id}`),
  // 班组
  listGroups:    () => get('/schedules/groups'),
  createGroup:   (data: unknown) => post('/schedules/groups', data),
  updateGroup:   (id: number, data: unknown) => put(`/schedules/groups/${id}`, data),
  deleteGroup:   (id: number) => del(`/schedules/groups/${id}`),
  syncMembers:   (id: number, user_ids: number[]) => post(`/schedules/groups/${id}/members`, { user_ids }),
  addMember:     (id: number, user_id: number) => post(`/schedules/groups/${id}/add-member`, { user_id }),
  removeMember:  (id: number, user_id: number) => del(`/schedules/groups/${id}/members/${user_id}`),
  // 排班
  index:         (params: Record<string, unknown>) => get('/schedules', params),
  batchSave:     (assignments: Record<string, unknown>[]) => post('/schedules', { assignments }),
  batchByGroup:  (data: unknown) => post('/schedules/batch-by-group', data),
  destroy:       (id: number) => del(`/schedules/${id}`),
  mySchedule:    (params?: Record<string, unknown>) => get('/schedules/my-schedule', params),
  smartSuggest:  (params: Record<string, unknown>) => get('/schedules/smart-suggest', params),
  nextReminder:  () => get('/schedules/next-reminder'),
  stats:         (params?: Record<string, unknown>) => get('/schedules/stats', params),
  // V1.2.4v: 系统默认班次 (新员工入职自动排的班)
  defaultShift:  () => get('/schedules/default-shift'),
}

// 报销
export function getExpenseList(params?: Record<string, unknown>) { return get('/expenses', params) }
export function createExpense(data: unknown) { return post('/expenses', data) }
export function getExpenseDetail(id: number) { return get(`/expenses/${id}`) }
export function approveExpense(id: number, data: unknown) { return post(`/expenses/${id}/approve`, data) }

// 车辆
export function getVehicleList(params?: Record<string, unknown>) { return get('/vehicles', params) }
export function createVehicle(data: unknown) { return post('/vehicles', data) }
export function applyVehicle(data: unknown) { return post('/vehicles/apply', data) }
export function getVehicleApplyList(params?: Record<string, unknown>) { return get('/vehicles/applies', params) }
export function approveVehicleApply(id: number, data: unknown) { return post(`/vehicles/applies/${id}/approve`, data) }
export function getVehicleStats() { return get('/vehicles/stats') }
// 保险
export function getInsuranceList(params?: Record<string, unknown>) { return get('/vehicles/insurances', params) }
export function createInsurance(data: unknown) { return post('/vehicles/insurances', data) }
export function updateInsurance(id: number, data: unknown) { return put(`/vehicles/insurances/${id}`, data) }
export function deleteInsurance(id: number) { return del(`/vehicles/insurances/${id}`) }
// 保养
export function getMaintenanceList(params?: Record<string, unknown>) { return get('/vehicles/maintenances', params) }
export function createMaintenance(data: unknown) { return post('/vehicles/maintenances', data) }
export function updateMaintenance(id: number, data: unknown) { return put(`/vehicles/maintenances/${id}`, data) }
export function deleteMaintenance(id: number) { return del(`/vehicles/maintenances/${id}`) }
// 油卡
export function getFuelCardList(params?: Record<string, unknown>) { return get('/fuel-cards', params) }
export function createFuelCard(data: unknown) { return post('/fuel-cards', data) }
export function updateFuelCard(id: number, data: unknown) { return put(`/fuel-cards/${id}`, data) }
export function deleteFuelCard(id: number) { return del(`/fuel-cards/${id}`) }
export function getFuelCardRecharges(params?: Record<string, unknown>) { return get('/fuel-cards/recharges', params) }
export function createFuelCardRecharge(data: unknown) { return post('/fuel-cards/recharges', data) }
export function deleteFuelCardRecharge(id: number) { return del(`/fuel-cards/recharges/${id}`) }
export function getFuelCardStats() { return get('/fuel-cards/stats') }
// 库存分类
export function getInventoryCategoryList() { return get('/inventory-categories') }
export function getInventoryCategoryTree() { return get('/inventory-categories/tree') }
export function createInventoryCategory(data: unknown) { return post('/inventory-categories', data) }
export function updateInventoryCategory(id: number, data: unknown) { return put(`/inventory-categories/${id}`, data) }
export function deleteInventoryCategory(id: number) { return del(`/inventory-categories/${id}`) }

// 财务 - 应收/应付
export function getReceivables(params?: Record<string, unknown>) { return get('/finance/receivables', params) }
export function getReceivableDetail(id: number) { return get(`/finance/receivables/${id}`) }
export function createReceivable(data: unknown) { return post('/finance/receivables', data) }
export function updateReceivable(id: number, data: unknown) { return put(`/finance/receivables/${id}`, data) }
export function deleteReceivable(id: number) { return del(`/finance/receivables/${id}`) }
export function getPayables(params?: Record<string, unknown>) { return get('/finance/payables', params) }
export function getPayableDetail(id: number) { return get(`/finance/payables/${id}`) }
export function createPayable(data: unknown) { return post('/finance/payables', data) }
export function updatePayable(id: number, data: unknown) { return put(`/finance/payables/${id}`, data) }
export function deletePayable(id: number) { return del(`/finance/payables/${id}`) }

// 财务 - 收/付款记录
export function getReceipts(params?: Record<string, unknown>) { return get('/finance/receipts', params) }
export function createReceipt(data: unknown) { return post('/finance/receipts', data) }
export function updateReceipt(id: number, data: unknown) { return put(`/finance/receipts/${id}`, data) }
export function confirmReceipt(id: number) { return post(`/finance/receipts/${id}/confirm`, {}) }
export function voidReceipt(id: number) { return post(`/finance/receipts/${id}/void`, {}) }
export function getPayments(params?: Record<string, unknown>) { return get('/finance/payments', params) }
export function createPayment(data: unknown) { return post('/finance/payments', data) }
export function updatePayment(id: number, data: unknown) { return put(`/finance/payments/${id}`, data) }
export function approvePayment(id: number) { return post(`/finance/payments/${id}/approve`, {}) }
export function voidPayment(id: number) { return post(`/finance/payments/${id}/void`, {}) }

// 财务 - 资金账户/转账/总览
export function getFinanceOverview() { return get('/finance/overview') }
export function getFinanceAccounts(params?: Record<string, unknown>) { return get('/finance/accounts', params) }
export function createFinanceAccount(data: unknown) { return post('/finance/accounts', data) }
export function updateFinanceAccount(id: number, data: unknown) { return put(`/finance/accounts/${id}`, data) }
export function deleteFinanceAccount(id: number) { return del(`/finance/accounts/${id}`) }
export function createFinanceTransfer(data: unknown) { return post('/finance/accounts/transfer', data) }
// V1.2.16: 内部转账明细 (二级菜单用)
export function getInternalTransfers(params?: Record<string, unknown>) { return get('/finance/internal-transfers', params) }
export function getInternalTransferDetail(groupId: string) { return get(`/finance/internal-transfers/${groupId}`) }

// 财务 - 发票
export function getInvoices(params?: Record<string, unknown>) { return get('/finance/invoices', params) }
export function getInvoiceDetail(id: number) { return get(`/finance/invoices/${id}`) }
export function createInvoice(data: unknown) { return post('/finance/invoices', data) }
export function updateInvoice(id: number, data: unknown) { return put(`/finance/invoices/${id}`, data) }
export function deleteInvoice(id: number) { return del(`/finance/invoices/${id}`) }

// 采购
export function getPurchaseRequirements(params?: Record<string, unknown>) { return get('/purchase/requirements', params) }
export function getPurchaseRequirementDetail(id: number) { return get(`/purchase/requirements/${id}`) }
export function createPurchaseRequirement(data: unknown) { return post('/purchase/requirements', data) }
export function updatePurchaseRequirement(id: number, data: unknown) { return put(`/purchase/requirements/${id}`, data) }
export function deletePurchaseRequirement(id: number) { return del(`/purchase/requirements/${id}`) }

export function getPurchasePlans(params?: Record<string, unknown>) { return get('/purchase/plans', params) }
export function getPurchasePlanDetail(id: number) { return get(`/purchase/plans/${id}`) }
export function createPurchasePlan(data: unknown) { return post('/purchase/plans', data) }
export function updatePurchasePlan(id: number, data: unknown) { return put(`/purchase/plans/${id}`, data) }
export function deletePurchasePlan(id: number) { return del(`/purchase/plans/${id}`) }
export function approvePurchasePlan(id: number, data?: ApiPayload) { return post(`/purchase/plans/${id}/approve`, data || {}) }

export function getPurchaseApprovals(params?: Record<string, unknown>) { return get('/purchase/approvals', params) }
export function createPurchaseApproval(data: unknown) { return post('/purchase/approvals', data) }
export function updatePurchaseApproval(id: number, data: unknown) { return put(`/purchase/approvals/${id}`, data) }
export function deletePurchaseApproval(id: number) { return del(`/purchase/approvals/${id}`) }

export function getPurchasePaymentRequests(params?: Record<string, unknown>) { return get('/purchase/payment-requests', params) }
export function createPurchasePaymentRequest(data: unknown) { return post('/purchase/payment-requests', data) }
export function updatePurchasePaymentRequest(id: number, data: unknown) { return put(`/purchase/payment-requests/${id}`, data) }
export function deletePurchasePaymentRequest(id: number) { return del(`/purchase/payment-requests/${id}`) }

export function getPurchasePayments(params?: Record<string, unknown>) { return get('/purchase/payments', params) }
export function createPurchasePayment(data: unknown) { return post('/purchase/payments', data) }
export function updatePurchasePayment(id: number, data: unknown) { return put(`/purchase/payments/${id}`, data) }
export function deletePurchasePayment(id: number) { return del(`/purchase/payments/${id}`) }

export function getPurchaseContracts(params?: Record<string, unknown>) { return get('/purchase/contracts', params) }
export function getPurchaseContractDetail(id: number) { return get(`/purchase/contracts/${id}`) }
export function createPurchaseContract(data: unknown) { return post('/purchase/contracts', data) }
export function updatePurchaseContract(id: number, data: unknown) { return put(`/purchase/contracts/${id}`, data) }
export function deletePurchaseContract(id: number) { return del(`/purchase/contracts/${id}`) }

export function getPurchaseShipments(params?: Record<string, unknown>) { return get('/purchase/shipments', params) }
export function createPurchaseShipment(data: unknown) { return post('/purchase/shipments', data) }
export function updatePurchaseShipment(id: number, data: unknown) { return put(`/purchase/shipments/${id}`, data) }
export function deletePurchaseShipment(id: number) { return del(`/purchase/shipments/${id}`) }

export function getPurchaseLogistics(params?: Record<string, unknown>) { return get('/purchase/logistics', params) }
export function createPurchaseLogistic(data: unknown) { return post('/purchase/logistics', data) }
export function updatePurchaseLogistic(id: number, data: unknown) { return put(`/purchase/logistics/${id}`, data) }
export function deletePurchaseLogistic(id: number) { return del(`/purchase/logistics/${id}`) }

// 审批
export function getApprovalList(params?: Record<string, unknown>) { return get('/approvals', params) }
export function getApprovalDetail(id: number) { return get(`/approvals/${id}`) }
export function createApproval(data: unknown) { return post('/approvals', data) }
export function updateApproval(id: number, data: unknown) { return put(`/approvals/${id}`, data) }
export function deleteApproval(id: number) { return del(`/approvals/${id}`) }
export function approveApproval(id: number, data?: ApiPayload) { return post(`/approvals/${id}/approve`, data || {}) }
export function rejectApproval(id: number, data?: ApiPayload) { return post(`/approvals/${id}/reject`, data || {}) }
export function transferApproval(id: number, data: unknown) { return post(`/approvals/${id}/transfer`, data) }
// 审批分类
export function getApprovalsFinance(params?: Record<string, unknown>) { return get('/approvals/finance', params) }
export function getApprovalsProject(params?: Record<string, unknown>) { return get('/approvals/project', params) }
export function getApprovalsOperation(params?: Record<string, unknown>) { return get('/approvals/operation', params) }

// ===================== 库存 (Inventory) 12 端点 =====================
export const inventory = {
  // 树 / 分类
  treeWithCounts: (params?: Record<string, unknown>) => get('/inventory/tree-with-counts', params),
  itemsByCategory: (params?: Record<string, unknown>) => get('/inventory/items-by-category', params),
  moveCategory: (id: number, parentId: number | null) => post(`/inventory-categories/${id}/move`, { parent_id: parentId }),
  getCategories: () => get('/inventory-categories'),
  createCategory: (data: unknown) => post('/inventory-categories', data),
  updateCategory: (id: number, data: unknown) => put(`/inventory-categories/${id}`, data),
  deleteCategory: (id: number) => del(`/inventory-categories/${id}`),

  // 物品 CRUD
  getItems: (params?: Record<string, unknown>) => get('/inventory', params),
  createItem: (data: unknown) => post('/inventory', data),
  updateItem: (id: number, data: unknown) => put(`/inventory/${id}`, data),
  deleteItem: (id: number) => del(`/inventory/${id}`),

  // 批量导入 / 模板
  // 注意: request 拦截器在检测到 FormData 时会自动清除 Content-Type,
  // 让浏览器自动添加 boundary, 因此这里不再显式传 Content-Type 头
  batchImport: (formData: FormData) => post('/inventory/items/batch-import', formData),
  exportTemplate: () => get('/inventory/items/export-template', undefined, { responseType: 'blob' }),

  // 批量处理
  batchDelete: (ids: number[]) => post('/inventory/batch-delete', { ids }),
  batchUpdate: (ids: number[], fields: Record<string, unknown>) => post('/inventory/batch-update', { ids, fields }),
  batchExport: (params: { ids?: number[]; keyword?: string; warehouse_id?: number; category_id?: number; status?: string }) =>
    post('/inventory/batch-export', params, { responseType: 'blob' }),

  // 预警
  warnings: () => get('/inventory/warnings'),

  // 统计 (V1.2.14p)
  stats: () => get('/inventory/stats'),

  // 仓库 (用于下拉)
  warehouses: () => get('/inventory/warehouses'),
}

// ===================== 采购 (Purchase) 37 端点 =====================
export const purchase = {
  // ---- 采购需求 (5 端点)
  getRequirements: (params?: Record<string, unknown>) => get('/purchase/requirements', params),
  getRequirementStats: () => get('/purchase/requirements/stats'),
  createRequirement: (data: unknown) => post('/purchase/requirements', data),
  updateRequirement: (id: number, data: unknown) => put(`/purchase/requirements/${id}`, data),
  deleteRequirement: (id: number) => del(`/purchase/requirements/${id}`),

  // ---- 采购计划 (7 端点)
  getPlans: (params?: Record<string, unknown>) => get('/purchase/plans', params),
  getPlanStats: () => get('/purchase/plans/stats'),
  createPlan: (data: unknown) => post('/purchase/plans', data),
  updatePlan: (id: number, data: unknown) => put(`/purchase/plans/${id}`, data),
  deletePlan: (id: number) => del(`/purchase/plans/${id}`),
  submitPlan: (id: number) => post(`/purchase/plans/${id}/submit`, {}),
  approvePlan: (id: number, data: unknown) => post(`/purchase/plans/${id}/approve`, data),

  // ---- 采购单 (V0.6.2.2: 走 purchase-flow/orders-list) ----
  getOrders: (params?: Record<string, unknown>) => get('/purchase-flow/orders-list', params).catch(() => ({ data: [] })),

  // ---- 采购审批 (3 端点) — 独立采购审批单 (非 plan submit/approve)
  getApprovals: (params?: Record<string, unknown>) => get('/purchase/approvals', params),
  createApproval: (data: unknown) => post('/purchase/approvals', data),
  decideApproval: (id: number, data: unknown) => post(`/purchase/approvals/${id}/decide`, data),

  // ---- 采购付款申请 (4 端点：后端无 PUT/无单条 GET)
  getPaymentRequests: (params?: Record<string, unknown>) => get('/purchase/payment-requests', params),
  getPaymentRequestStats: () => get('/purchase/payment-requests/stats'),
  createPaymentRequest: (data: unknown) => post('/purchase/payment-requests', data),
  approvePaymentRequest: (id: number, data: unknown) => post(`/purchase/payment-requests/${id}/approve`, data),
  deletePaymentRequest: (id: number) => del(`/purchase/payment-requests/${id}`),

  // ---- 采购付款 (3 端点)
  getPayments: (params?: Record<string, unknown>) => get('/purchase/payments', params),
  getPaymentStats: () => get('/purchase/payments/stats'),
  createPayment: (data: unknown) => post('/purchase/payments', data),

  // ---- 采购合同 (7 端点)
  getContracts: (params?: Record<string, unknown>) => get('/purchase/contracts', params),
  getContractDetail: (id: number) => get(`/purchase/contracts/${id}`),
  getContractStats: () => get('/purchase/contracts/stats'),
  createContract: (data: unknown) => post('/purchase/contracts', data),
  updateContract: (id: number, data: unknown) => put(`/purchase/contracts/${id}`, data),
  deleteContract: (id: number) => del(`/purchase/contracts/${id}`),
  shipContract: (id: number, data: unknown) => post(`/purchase/contracts/${id}/ship`, data),

  // ---- 采购发货 (3 端点 — 只读)
  getShipments: (params?: Record<string, unknown>) => get('/purchase/shipments', params),
  getShipmentDetail: (id: number) => get(`/purchase/shipments/${id}`),
  getShipmentStats: () => get('/purchase/shipments/stats'),

  // ---- 采购物流 (4 端点)
  getShipmentLogistics: (shipmentId: number, params?: Record<string, unknown>) => get(`/purchase/shipments/${shipmentId}/logistics`, params),
  getShipmentTrack: (shipmentId: number) => get(`/purchase/shipments/${shipmentId}/track`),
  addLogisticsEvent: (shipmentId: number, data: unknown) => post(`/purchase/shipments/${shipmentId}/logistics-update`, data),
  updateLogisticsEvent: (shipmentId: number, logId: number, data: unknown) => put(`/purchase/shipments/${shipmentId}/logistics/${logId}`, data),
}

// ===================== 入职档案 (Employee Onboardings) =====================
export const onboardings = {
  list:   (params: Record<string, unknown>) => get('/employee-onboardings', params),
  show:   (id: number)  => get(`/employee-onboardings/${id}`),
  create: (data: unknown)   => post('/employee-onboardings', data),
  update: (id: number, data: unknown) => put(`/employee-onboardings/${id}`, data),
  archive: (id: number) => del(`/employee-onboardings/${id}`),
}

// ===================== 离职管理 (Employee Resignations) =====================
export const resignations = {
  list:              (params: Record<string, unknown>) => get('/employee-resignations', params),
  show:              (id: number)  => get(`/employee-resignations/${id}`),
  create:            (data: unknown)   => post('/employee-resignations', data),
  update:            (id: number, data: unknown) => put(`/employee-resignations/${id}`, data),
  submit:            (id: number)  => post(`/employee-resignations/${id}/submit`),
  approve:           (id: number)  => post(`/employee-resignations/${id}/approve`),
  cancel:            (id: number)  => post(`/employee-resignations/${id}/cancel`),
  complete:          (id: number, data: unknown) => post(`/employee-resignations/${id}/complete`, data),
  settlementPreview: (params: Record<string, unknown>) => get('/employee-resignations/settlement-preview', params),
}

// ===================== 工序管理 (Process) V1.1 工序验收 =====================
export const processApi = {
  // ---- 行业字典 (Industries) ----
  industries:        () => get('/process/industries'),

  // ---- 工序模板 (Templates) ----
  templateList:      (params: Record<string, unknown>) => get('/process/templates', params),
  templateDetail:    (id: number)   => get(`/process/templates/${id}`),
  templateCreate:    (data: unknown)    => post('/process/templates', data),
  templateUpdate:    (id: number, data: unknown) => put(`/process/templates/${id}`, data),
  templateDelete:    (id: number)   => del(`/process/templates/${id}`),

  // ---- 工序实例 (Instances) ----
  instanceList:      (params: Record<string, unknown>) => get('/process/instances', params),
  instanceCreate:    (data: unknown)    => post('/process/instances', data),
  instanceDetail:    (id: number)   => get(`/process/instances/${id}`),
  instanceUpdate:    (id: number, data: unknown) => put(`/process/instances/${id}`, data),
  instanceDelete:    (id: number)   => del(`/process/instances/${id}`),
  instanceAccept:    (id: number, data: unknown) => post(`/process/instances/${id}/accept`, data),
  instanceReject:    (id: number, data: unknown) => post(`/process/instances/${id}/reject`, data),
  instanceProgress:  (id: number, data: unknown) => post(`/process/instances/${id}/progress`, data),

  // ---- 验收记录 (Inspections) ----
  inspectionList:    (params: Record<string, unknown>) => get('/process/inspections', params),
  inspectionDetail:  (id: number)   => get(`/process/inspections/${id}`),
  inspectionCreate:  (data: unknown)    => post('/process/inspections', data),
  inspectionUpdate:  (id: number, data: unknown) => put(`/process/inspections/${id}`, data),
  inspectionDelete:  (id: number)   => del(`/process/inspections/${id}`),
}
