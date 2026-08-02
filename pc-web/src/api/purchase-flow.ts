import http from '@/utils/request'

// 通用 API 负载类型 — 替代 any, 强制调用方做类型守卫
export type ApiPayload = Record<string, unknown>;

// 8 步流转 API (V0.6.2 — 对齐后端 PurchaseFlowController 路由)
export const purchaseFlow = {
  /** 一键发起: 从维修工单 */
  fromWorkOrder: (workOrderId: number, payload: ApiPayload) =>
    http.post(`/purchase-flow/from-work-order/${workOrderId}`, payload),

  /** 一键发起: 从施工发包 */
  fromExternalWork: (workId: number, payload: ApiPayload) =>
    http.post(`/purchase-flow/from-external-work/${workId}`, payload),

  /** 从来源反查已发起的采购需求 */
  bySource: (type: string, id: number) =>
    http.get(`/purchase-flow/by-source/${type}/${id}`),

  // ============== 1. 需求 ==============
  createRequirement: (payload: ApiPayload) =>
    http.post('/purchase-flow/requirements', payload),
  submitRequirement: (id: number) =>
    http.post(`/purchase-flow/requirements/${id}/submit`),
  approveRequirement: (id: number, remark: string = '') =>
    http.post(`/purchase-flow/requirements/${id}/approve`, { remark }),

  // ============== 2. 计划 ==============
  /** 合并需求到计划 (requirement_ids 放 body) */
  createPlan: (payload: ApiPayload) =>
    http.post('/purchase-flow/plans', payload),
  submitPlan: (id: number) =>
    http.post(`/purchase-flow/plans/${id}/submit`),
  approvePlan: (id: number) =>
    http.post(`/purchase-flow/plans/${id}/approve`),

  // ============== 3. 采购单 (后端路由用 orders, 不是 pos) ==============
  /** 从计划创建 PO (plan_id + supplier_id 放 body; path=quote|bid|manual) */
  createOrder: (payload: ApiPayload) =>
    http.post('/purchase-flow/orders', payload),
  /** 中标转 PO (tender_id 放 body, path=bid) */
  poFromTender: (tenderId: number, payload: ApiPayload) =>
    http.post('/purchase-flow/orders', { tender_id: tenderId, path: 'bid', ...payload }),
  submitOrder: (id: number) =>
    http.post(`/purchase-flow/orders/${id}/submit`),
  approveOrder: (id: number) =>
    http.post(`/purchase-flow/orders/${id}/approve`),

  // ============== 4. 合同 (order_id 放 body) ==============
  createContract: (poId: number, payload: ApiPayload) =>
    http.post('/purchase-flow/contracts', { order_id: poId, ...payload }),
  signContract: (id: number) =>
    http.post(`/purchase-flow/contracts/${id}/sign`),

  // ============== 5. 付款 (contract_id 放 body) ==============
  createPaymentRequest: (contractId: number, payload: ApiPayload) =>
    http.post('/purchase-flow/payment-requests', { contract_id: contractId, ...payload }),
  approvePaymentRequest: (id: number, remark: string = '') =>
    http.post(`/purchase-flow/payment-requests/${id}/approve`, { remark }),
  /** 执行付款 (payment_request_id 放 body) */
  executePayment: (reqId: number, payload: ApiPayload) =>
    http.post('/purchase-flow/payments', { payment_request_id: reqId, ...payload }),

  // ============== 6. 收货 (contract_id 放 body) ==============
  createShipment: (contractId: number, payload: ApiPayload) =>
    http.post('/purchase-flow/shipments', { contract_id: contractId, ...payload }),
  /** 标记到货 (后端用 update-status, status=arrived) */
  markArrived: (id: number) =>
    http.post(`/purchase-flow/shipments/${id}/update-status`, { status: 'arrived' }),
  /** 通用状态更新 */
  updateShipmentStatus: (id: number, status: string, remark: string = '') =>
    http.post(`/purchase-flow/shipments/${id}/update-status`, { status, remark }),

  // ============== 7. 入库 ==============
  autoCreateInbound: (id: number) =>
    http.post(`/purchase-flow/shipments/${id}/auto-inbound`),
  confirmInbound: (id: number) =>
    http.post(`/purchase-flow/shipments/${id}/confirm-inbound`),

  // ============== 8. 审计 ==============
  /** 单实体全链路时间线 (通用 trace) */
  trace: (entityType: string, entityId: number) =>
    http.get(`/purchase-flow/${entityType}/${entityId}/trace`),
  /** 全量状态日志 */
  logs: (params?: Record<string, unknown>) =>
    http.get('/purchase-flow/logs', { params }),

  // ============== V0.6.3 通用撤回/取消 ==============
  cancel: (entityType: string, entityId: number, remark: string = '') =>
    http.post(`/purchase-flow/${entityType}/${entityId}/cancel`, { remark }),

  // ============== V0.6.2.2 合同附件/清单/付款凭证/发货计划 ==============
  // 合同附件
  listContractFiles: (contractId: number) =>
    http.get(`/purchase-flow/contracts/${contractId}/files`),
  uploadContractFile: (contractId: number, formData: FormData) =>
    http.post(`/purchase-flow/contracts/${contractId}/files`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    }),
  deleteContractFile: (contractId: number, fileId: number) =>
    http.delete(`/purchase-flow/contracts/${contractId}/files/${fileId}`),
  // 合同清单
  listContractItems: (contractId: number) =>
    http.get(`/purchase-flow/contracts/${contractId}/items`),
  syncContractItems: (contractId: number) =>
    http.post(`/purchase-flow/contracts/${contractId}/items/sync`),
  addContractItem: (contractId: number, payload: ApiPayload) =>
    http.post(`/purchase-flow/contracts/${contractId}/items`, payload),
  updateContractItem: (contractId: number, itemId: number, payload: ApiPayload) =>
    http.put(`/purchase-flow/contracts/${contractId}/items/${itemId}`, payload),
  deleteContractItem: (contractId: number, itemId: number) =>
    http.delete(`/purchase-flow/contracts/${contractId}/items/${itemId}`),
  // 付款凭证
  listPaymentVouchers: (paymentRequestId: number) =>
    http.get(`/purchase-flow/payment-requests/${paymentRequestId}/vouchers`),
  uploadPaymentVoucher: (paymentRequestId: number, formData: FormData) =>
    http.post(`/purchase-flow/payment-requests/${paymentRequestId}/voucher`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    }),
  // 发货预期/快递单号
  setShippingPlan: (contractId: number, payload: ApiPayload) =>
    http.post(`/purchase-flow/contracts/${contractId}/shipping-plans`, payload),
  addTracking: (contractId: number, payload: ApiPayload) =>
    http.post(`/purchase-flow/contracts/${contractId}/tracking`, payload),
  listShipping: (contractId: number) =>
    http.get(`/purchase-flow/contracts/${contractId}/shipping`),
}

// 8 步状态机常量 (与后端 PurchaseFlowService 对应)
export const PURCHASE_STEPS = [
  { key: 'requirement', label: '采购需求', icon: 'List' },
  { key: 'plan', label: '采购计划', icon: 'Files' },
  { key: 'quote_or_bid', label: '询价/招标', icon: 'ChatLineSquare' },
  { key: 'order', label: '采购计划', icon: 'Document' },
  { key: 'contract', label: '合同', icon: 'Tickets' },
  { key: 'payment', label: '付款', icon: 'Money' },
  { key: 'shipment', label: '收货', icon: 'Box' },
  { key: 'inbound', label: '入库', icon: 'House' },
] as const

export type PurchaseStepKey = typeof PURCHASE_STEPS[number]['key']

// 从 entity_type 推断当前所在 step
export function inferCurrentStep(entityType: string): PurchaseStepKey {
  const m: Record<string, PurchaseStepKey> = {
    requirement: 'requirement',
    plan: 'plan',
    quote: 'quote_or_bid',
    bid: 'quote_or_bid',
    order: 'order',
    contract: 'contract',
    payment_request: 'payment',
    payment: 'payment',
    shipment: 'shipment',
    inbound: 'inbound',
  }
  return m[entityType] || 'requirement'
}
