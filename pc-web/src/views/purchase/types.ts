// purchase 共享类型 (any 治理)
export type PurchaseTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

// 采购审批
export interface PurchaseApproval {
  id: number
  purchase_no?: string
  title?: string
  status?: string
  amount?: number | string
  applicant?: { name: string } | null
  created_at?: string
  [key: string]: unknown
}

// 采购合同
export interface PurchaseContract {
  id: number
  contract_no?: string
  contract_name?: string
  supplier?: { id: number; name: string } | null
  total_amount?: number | string
  sign_date?: string
  status?: string
  remark?: string
  [key: string]: unknown
}

// 采购计划
export interface PurchasePlan {
  id: number
  plan_no?: string
  title?: string
  status?: string
  priority?: string
  total_amount?: number | string
  created_at?: string
  [key: string]: unknown
}

// 付款申请
export interface PaymentRequest {
  id: number
  request_no?: string
  title?: string
  amount?: number | string
  status?: string
  created_at?: string
  [key: string]: unknown
}

// 采购物品行
export interface PurchaseItem {
  id?: number
  item_name?: string
  specification?: string
  quantity?: number
  unit?: string
  unit_price?: number | string
  total_price?: number | string
  remark?: string
  [key: string]: unknown
}

// 付款记录
export interface PaymentItem {
  id: number
  payment_no?: string
  amount?: number | string
  status?: string
  supplier?: { id: number; name: string } | null
  method?: string
  payment_date?: string
  voucher_no?: string
  remark?: string
  [key: string]: unknown
}

// 采购合同文件
export interface PurchaseContractFile {
  id?: number
  name?: string
  url?: string
  size?: number
  [key: string]: unknown
}
export interface PurchaseRequirement {
  id: number
  requirement_no?: string
  title?: string
  status?: string
  priority?: string
  total_amount?: number | string
  department?: string
  proposer?: string
  created_at?: string
  expected_date?: string
  remark?: string
  [key: string]: unknown
}
