// 质保相关共享类型 — warranty 模块 any 治理
// 带 [key: string]: unknown 索引签名，兼容后端动态字段，同时提供具体字段类型提示

// Element Plus el-tag 接受的 type 联合
export type TagType = 'success' | 'info' | 'warning' | 'danger' | 'primary'

// 质保金记录
export interface WarrantyDeposit {
  id?: number
  project_id?: number
  warranty_id?: number
  project?: { name?: string } | null
  customer?: { name?: string } | null
  warranty?: { warranty_no?: string } | null
  contract_amount?: string | number
  deposit_amount?: string | number
  released_amount?: string | number
  release_amount?: string | number
  forfeit_amount?: string | number
  forfeited_amount?: string | number
  deposit_rate?: string | number
  expected_release_date?: string
  payment_method?: string
  bank_account?: string
  notes?: string
  reason?: string
  status?: string
  hold_date?: string
  logs?: DepositLog[]
  [key: string]: unknown
}

export interface DepositLog {
  id: number
  deposit_id: number
  operation_type: 'partial_release' | 'full_release' | 'forfeit'
  amount: number
  before_status?: string
  after_status?: string
  bank_account_id?: number
  bankAccount?: { id: number; bank_name: string; account_name: string; account_no: string } | null
  beneficiary?: string
  reason?: string
  operator_id?: number
  created_at: string
}

export interface ReleaseDialogForm {
  release_amount: number
  release_date: string
  release_reason: string
  beneficiary_name: string
  bank_account_id: number | null
}

export interface ReleaseDialogState {
  visible: boolean
  title: string
  type: 'partial' | 'full'
  target: WarrantyDeposit | null
  balance: number
  form: ReleaseDialogForm
}

export interface ForfeitDialogForm {
  forfeit_amount: number
  forfeit_date: string
  forfeit_reason: string
}

export interface ForfeitDialogState {
  visible: boolean
  target: WarrantyDeposit | null
  balance: number
  form: ForfeitDialogForm
}

// 质保期记录
export interface Warranty {
  id?: number
  warranty_no?: string
  warranty_name?: string
  terms?: string
  project_id?: number
  project?: { name?: string } | null
  customer?: { name?: string } | null
  device?: { device_name?: string; serial_number?: string } | null
  creator?: { name?: string } | null
  warranty_type?: string
  warranty_period_months?: number | string
  start_date?: string
  end_date?: string
  created_at?: string
  coverage_scope?: string
  renewed_from_id?: number
  renewed_from?: { warranty_no?: string } | null
  renewals?: Warranty[]
  deposits?: WarrantyDeposit[]
  days_left?: number
  status?: string
  [key: string]: unknown
}

export interface ActionDialogForm {
  renew_months: number
  reason: string
}

export interface ActionDialogState {
  visible: boolean
  title: string
  type: '' | 'renew' | 'terminate'
  target: Warranty | null
  form: ActionDialogForm
}
