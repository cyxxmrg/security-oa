// finance/Payable 子组件共享 types
// v0.3.25 从 views/finance/Payable.vue 抽出

export interface Payable {
  id: number
  supplier_id: number
  supplier?: { id: number; name: string }
  project_id?: number
  project?: { id: number; name: string }
  amount: number
  paid_amount: number
  remaining_amount: number
  status: 'pending' | 'partial' | 'fully_paid' | string
  due_date?: string
  remark?: string
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

// 新增/编辑应付表单
export interface PayableForm {
  supplier_id: number | null
  project_id: number | null
  amount: number
  paid_amount: number
  due_date: string
  payment_term: string
  notes: string
}

// 登记付款表单
export interface PayForm {
  paid_amount: number
  paid_date: string
  account_id: number | null
}

// 付款分摊明细行
export interface PayAllocation {
  payable_id: number
  ref_no: string
  amount: number
  paid_amount: number
  balance: number
  selected: boolean
  amount_alloc: number
}

export interface PayableTotals {
  amount: number
  paid: number
  remaining: number
  overdue: number
  count: number
  paidRate: number
  overdueCount: number
}

export interface PayableFilters {
  keyword: string
  status: string
  dateRange: string[]
}

export type TagType = 'success' | 'warning' | 'info' | 'danger' | 'primary'

export const STATUS_MAP: Record<string, { label: string; type: TagType }> = {
  pending:    { label: '待付',     type: 'warning' },
  partial:    { label: '部分付',   type: 'primary' },
  fully_paid: { label: '已付完',   type: 'success' },
}

export const statusLabel = (s: string): string => STATUS_MAP[s]?.label || s
export const statusType = (s: string): TagType => STATUS_MAP[s]?.type || 'info'

export const formatMoney = (v: number | string | null | undefined): string => {
  const n = Number(v) || 0
  if (n >= 10000) return (n / 10000).toFixed(2) + '万'
  return n.toLocaleString('zh-CN', { maximumFractionDigits: 2 })
}

export const computeRate = (row: Payable): number => {
  if (!row.amount) return 0
  return Math.round((Number(row.paid_amount || 0) / row.amount) * 100)
}

export const progressColor = (row: Payable): string => {
  const r = computeRate(row)
  if (r >= 100) return '#1D9E75'
  if (r >= 50) return '#0C447C'
  return '#BA7517'
}

export const isOverdue = (row: Payable): boolean => {
  if (row.status === 'fully_paid') return false
  if (!row.due_date) return false
  return String(row.due_date).slice(0, 10) < new Date().toISOString().slice(0, 10)
}

export const overdueDays = (row: Payable): number => {
  if (!isOverdue(row) || !row.due_date) return 0
  const due = new Date(String(row.due_date).slice(0, 10)).getTime()
  const now = new Date().getTime()
  return Math.max(0, Math.floor((now - due) / 86400000))
}

export const emptyTotals = (): PayableTotals => ({
  amount: 0, paid: 0, remaining: 0, overdue: 0,
  count: 0, paidRate: 0, overdueCount: 0,
})
