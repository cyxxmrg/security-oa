// finance/Receivable 子组件共享 types
// v0.3.25 从 views/finance/Receivable.vue 抽出

export interface Receivable {
  id: number
  customer_id: number
  customer?: { id: number; name: string }
  project_id?: number
  project?: { id: number; name: string }
  amount: number
  received_amount: number
  remaining_amount: number
  status: 'pending' | 'partial' | 'fully_paid' | 'overdue' | string
  due_date?: string
  received_date?: string
  notes?: string
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

// 新增/编辑应收表单
export interface ReceivableForm {
  customer_id: number | null
  project_id: number | null
  amount: number
  received_amount: number
  due_date: string
  notes: string
  payment_term?: string
}

// 登记收款表单
export interface ReceiveForm {
  received_amount: number
  received_date: string
  account_id: number | null
}

export interface ReceivableTotals {
  amount: number
  received: number
  remaining: number
  overdue: number
  count: number
  receivedRate: number
  overdueCount: number
}

export interface ReceivableFilters {
  keyword: string
  status: string
  dateRange: string[]
}

export type TagType = 'success' | 'warning' | 'info' | 'danger' | 'primary'

export const STATUS_MAP: Record<string, { label: string; type: TagType }> = {
  pending:    { label: '待收',     type: 'warning' },
  partial:    { label: '部分收',   type: 'primary' },
  fully_paid: { label: '已收完',   type: 'success' },
  overdue:    { label: '已逾期',   type: 'danger' },
}

export const statusLabel = (s: string): string => STATUS_MAP[s]?.label || s
export const statusType = (s: string): TagType => STATUS_MAP[s]?.type || 'info'

export const formatMoney = (v: number | string | null | undefined): string => {
  const n = Number(v) || 0
  return n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export const computeRate = (row: Receivable): number => {
  const a = Number(row.amount) || 0
  const r = Number(row.received_amount) || 0
  if (a <= 0) return 0
  return Math.min(100, Math.round((r / a) * 100))
}

export const progressColor = (row: Receivable): string => {
  const r = computeRate(row)
  if (r >= 100) return '#1D9E75'
  if (isOverdue(row)) return '#A32D2D'
  return '#0C447C'
}

export const isOverdue = (row: Receivable): boolean => {
  if (!row.due_date) return false
  if (row.status === 'fully_paid') return false
  return new Date(row.due_date) < new Date(new Date().toDateString())
}

export const overdueDays = (row: Receivable): number => {
  if (!isOverdue(row) || !row.due_date) return 0
  const d = Math.floor((Date.now() - new Date(row.due_date).getTime()) / 86400000)
  return d > 0 ? d : 0
}

export const emptyTotals = (): ReceivableTotals => ({
  amount: 0, received: 0, remaining: 0, overdue: 0,
  count: 0, receivedRate: 0, overdueCount: 0,
})
