// 财务模块共享类型 (any 治理)
// 后端返回字段动态，统一带 [key: string]: unknown 索引签名兼容

export type FinanceTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

// 资金账户
export interface FinanceAccount {
  id?: number | null
  name?: string
  account_no?: string
  bank_name?: string
  balance?: number | string | null
  currency?: string
  type?: string
  status?: string
  remark?: string
  [key: string]: unknown
}

// 账户 KPI 统计
export interface AccountStats {
  count?: number
  active_count?: number
  total_balance?: number | string | null
  [key: string]: unknown
}

// 账户流水
export interface AccountTransaction {
  id?: number
  payment_date?: string
  method?: string
  receivable_id?: number | null
  amount?: number | string | null
  voucher_no?: string
  operator?: string
  remark?: string
  [key: string]: unknown
}

// 往来单位台账 (客户/供应商)
export interface LedgerEntry {
  id?: number
  biz_date?: string
  type?: string
  amount?: number | string | null
  balance?: number | string | null
  related_no?: string
  remark?: string
  [key: string]: unknown
}

// 财务概览 (finance/index.vue)
export interface FinanceOverview {
  totalRevenue?: number
  totalReceivable?: number
  totalPayable?: number
  [key: string]: unknown
}

// 总账概览 summary (客户/供应商总账)
export interface LedgerSummaryLike {
  total_amount?: number
  total_paid?: number
  total_received?: number
  total_balance?: number
  payable_count?: number
  receivable_count?: number
  overdue_count?: number
  [key: string]: unknown
}

// 总账概览对象 (含 summary)
export interface LedgerOverview {
  summary?: LedgerSummaryLike
  [key: string]: unknown
}

// 月度趋势点 (MonthlyTrendChart 入参)
export interface MonthlyPoint {
  month: string
  amount: number
  paid?: number
  received?: number
  [key: string]: unknown
}

// 供应商/客户总账明细行 (payable/receivable 通用)
export interface LedgerRow {
  id?: number
  supplier_id?: number
  project_id?: number
  ref_no?: string
  source_type?: string
  amount?: number | string | null
  paid_amount?: number | string | null
  balance?: number | string | null
  due_date?: string
  status?: string
  supplier?: { id?: number; name?: string; code?: string } | null
  project?: { id?: number; name?: string } | null
  [key: string]: unknown
}

// 客户总账明细行 (含 receivable_type / received_amount / customer)
export interface CustomerLedgerRow extends LedgerRow {
  customer_id?: number
  receivable_type?: string
  received_amount?: number | string | null
  customer?: { id?: number; name?: string } | null
}

// 发票 (finance/Invoice.vue)
export interface FinanceInvoice {
  id: number
  invoice_no?: string
  invoice_type?: string
  status?: string
  amount?: string | number | null
  tax_rate?: string | number | null
  tax_amount?: string | number | null
  total_amount?: string | number | null
  issue_date?: string
  remark?: string
  customer?: { id?: number; name?: string } | null
  supplier?: { id?: number; name?: string } | null
  contract?: { id?: number; contract_no?: string } | null
  [key: string]: unknown
}

// 通用关联实体 (Customer/Supplier/Contract)
export interface BizContact {
  id: number
  name?: string
  contract_no?: string
  [key: string]: unknown
}

// 售后成本报表
export interface RepairCostOverview {
  completed_orders?: number
  total_cost?: number | string | null
  warranty_cost?: number | string | null
  paid_cost?: number | string | null
  total_parts_cost?: number | string | null
  total_labor_cost?: number | string | null
  total_shipping_cost?: number | string | null
  [key: string]: unknown
}
export interface RepairCostMonth {
  month?: string
  orders_count?: number
  parts_cost?: number | string | null
  labor_cost?: number | string | null
  shipping_cost?: number | string | null
  total_cost?: number | string | null
  [key: string]: unknown
}
export interface RepairCostProject {
  project_id?: number
  project_code?: string
  project_name?: string
  orders_count?: number
  total_cost?: number | string | null
  [key: string]: unknown
}
export interface RepairCostCustomer {
  customer_id?: number
  customer_name?: string
  orders_count?: number
  warranty_cost?: number | string | null
  paid_cost?: number | string | null
  total_cost?: number | string | null
  [key: string]: unknown
}
export interface RepairCostMethod {
  method_type?: string
  orders_count?: number
  total_cost?: number | string | null
  percentage?: number
  [key: string]: unknown
}
