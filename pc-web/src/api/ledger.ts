import { get, post } from '@/utils/request'

/**
 * V0.4.2 总账 API 封装
 *
 * 端点：/api/ledger
 */

// ============= 类型定义 =============

export interface LedgerItem {
  id: number
  supplier_id?: number
  customer_id?: number
  project_id?: number
  source_type?: string
  source_id?: number
  ref_no?: string
  amount: number
  paid_amount?: number
  received_amount?: number
  balance: number
  due_date?: string
  status: string
  note?: string
  receivable_type?: 'contract' | 'progress' | 'retention' | 'warranty'
  supplier?: { id: number; name: string; code?: string }
  customer?: { id: number; name: string }
  project?: { id: number; name: string; code?: string }
  type_label?: string
  status_label?: string
}

export interface PaymentRecord {
  id: number
  supplier_id?: number
  customer_id?: number
  amount: number
  payment_date?: string
  receipt_date?: string
  method: string
  voucher_no?: string
  allocations?: Record<string, unknown>[]
  bank_account?: string
  operator?: string
  remark?: string
  supplier?: { id: number; name: string; code?: string }
  customer?: { id: number; name: string }
}

export interface LedgerSummary {
  total_amount: number
  total_paid?: number
  total_received?: number
  total_balance: number
  payable_count?: number
  receivable_count?: number
  overdue_count?: number
  pending_count?: number
}

export interface SupplierLedger {
  summary: LedgerSummary
  payables: LedgerItem[]
  payments: PaymentRecord[]
  monthly: Array<{ month: string; amount: number; paid?: number; received?: number }>
}

export interface CustomerLedger {
  summary: LedgerSummary
  receivables: LedgerItem[]
  receipts: PaymentRecord[]
  monthly: Array<{ month: string; amount: number; paid?: number; received?: number }>
}

export interface AgingBucket {
  key: string
  amount: number
  count: number
}

// ============= API =============

export const ledger = {
  // 供应商台账列表（全局）
  listSuppliers: (params?: {
    supplier_id?: number
    project_id?: number
    status?: string
    from?: string
    to?: string
    page?: number
    per_page?: number
  }) => get('/ledger/suppliers', params),

  // 单供应商总账
  getSupplierLedger: (id: number, params?: Record<string, unknown>) =>
    get(`/ledger/suppliers/${id}`, params),

  // 某供应商应付明细
  getSupplierPayables: (id: number, params?: { status?: string }) =>
    get(`/ledger/suppliers/${id}/payables`, params),

  // 新增供应商付款
  createSupplierPayment: (data: {
    supplier_id: number
    amount: number
    payment_date: string
    method: 'cash' | 'bank' | 'alipay' | 'wechat' | 'other'
    voucher_no?: string
    bank_account?: string
    operator?: string
    remark?: string
    allocations: Array<{ payable_id: number; amount: number }>
  }) => post('/ledger/supplier-payments', data),

  // 客户台账列表
  listCustomers: (params?: {
    customer_id?: number
    project_id?: number
    status?: string
    receivable_type?: string
    from?: string
    to?: string
    page?: number
    per_page?: number
  }) => get('/ledger/customers', params),

  // 单客户总账
  getCustomerLedger: (id: number, params?: Record<string, unknown>) =>
    get(`/ledger/customers/${id}`, params),

  // 客户应收明细
  getCustomerReceivables: (id: number, params?: { status?: string; receivable_type?: string }) =>
    get(`/ledger/customers/${id}/receivables`, params),

  // 新增客户收款
  createCustomerReceipt: (data: {
    customer_id: number
    amount: number
    receipt_date: string
    method: 'cash' | 'bank' | 'alipay' | 'wechat' | 'check' | 'other'
    voucher_no?: string
    bank_account?: string
    operator?: string
    remark?: string
    allocations: Array<{ receivable_id: number; amount: number }>
  }) => post('/ledger/customer-receipts', data),

  // 财务概览
  summary: () => get('/ledger/summary'),

  // 付款详情
  getSupplierPayment: (id: number) => get(`/ledger/supplier-payments/${id}`),

  // 收款详情
  getCustomerReceipt: (id: number) => get(`/ledger/customer-receipts/${id}`),

  // 账龄分析
  aging: (type: 'payable' | 'receivable' = 'payable') =>
    get('/ledger/aging', { type }),
}
