// 预算模块共享类型 — 由各组件统一引用, 收窄类型、消除隐式松散类型

export interface BudgetItem {
  item_id?: number | null
  item_type?: string | null
  category: string
  name: string
  item_name?: string
  spec: string
  specification?: string
  unit: string
  qty: number
  unit_price: number
  amount: number
  remark: string
  [key: string]: unknown
}

export interface ProjectRef {
  id?: number
  name?: string
  code?: string
  [key: string]: unknown
}

export interface CreatorRef {
  id?: number
  name?: string
  [key: string]: unknown
}

export interface CategoryAmount {
  budget?: number | string
  budget_amount?: number | string
  actual?: number | string
  actual_amount?: number | string
  balance?: number | string
  usage_rate?: number | string
  [key: string]: unknown
}

export interface CostFlow {
  date?: string
  source?: string
  ref_code?: string
  ref_id?: number | string
  summary?: string
  remark?: string
  amount?: number | string
  [key: string]: unknown
}

export interface Budget {
  id?: number
  code?: string
  version?: number | string
  status?: string
  project_id?: number
  project?: ProjectRef | null
  total_amount?: number | string | null
  actual_amount?: number | string | null
  usage_rate?: number | string | null
  creator?: CreatorRef | null
  created_by?: number | string | null
  created_at?: string | null
  remark?: string | null
  items?: BudgetItem[]
  cost_flows?: CostFlow[]
  actual_flows?: CostFlow[]
  flows?: CostFlow[]
  categories?: Record<string, CategoryAmount>
  material?: CategoryAmount
  labor?: CategoryAmount
  outsource?: CategoryAmount
  other?: CategoryAmount
  actual_sources?: Record<string, number | string>
  [key: string]: unknown
}

export interface BudgetSummary {
  total_budget?: number | string
  total_actual?: number | string
  balance?: number | string
  usage_rate?: number | string
  categories?: Record<string, CategoryAmount>
  material?: CategoryAmount
  labor?: CategoryAmount
  outsource?: CategoryAmount
  other?: CategoryAmount
  actual_sources?: Record<string, number | string>
  [key: string]: unknown
}

export type TagType = 'success' | 'info' | 'warning' | 'danger'

export interface SaveBudgetItemPayload {
  category: string
  item_id?: number | null
  item_type?: string | null
  item_name: string
  specification: string
  unit: string
  quantity: number
  unit_price: number
  planned_amount: number
  remark: string
  [key: string]: unknown
}

export interface SaveBudgetPayload {
  project_id: number
  items: SaveBudgetItemPayload[]
  remark: string
}

export interface ProjectOption {
  id: number
  code?: string
  name?: string
  [key: string]: unknown
}
