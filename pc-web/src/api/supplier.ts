import { get, post, put, del } from '@/utils/request'

/**
 * V0.4.2 供应商 API 封装
 *
 * 端点：/api/suppliers + /api/supplier-portal
 */

// ============= 类型定义 =============

export interface SupplierContact {
  name: string
  position?: string
  phone?: string
  tel?: string
  email?: string
  wechat?: string
  is_primary?: boolean
  remark?: string
}

export interface Supplier {
  id: number
  code: string
  name: string
  type: 'material' | 'labor' | 'outsource' | 'service'
  contact_person?: string
  phone?: string
  email?: string
  address?: string
  category?: string
  business_license?: string
  legal_person?: string
  registered_capital?: number
  website?: string
  bank_name?: string
  bank_account?: string
  account_name?: string
  tax_no?: string
  payment_terms: 'cash' | '30days' | '60days' | '90days'
  rating: number
  status: 'active' | 'paused' | 'blacklist'
  remark?: string
  contacts?: SupplierContact[]
  attachments?: Record<string, unknown>[]
  creator?: { id: number; name: string }
  type_label?: string
  status_label?: string
}

export interface SupplierEvaluation {
  id: number
  supplier_id: number
  project_id?: number
  quality_score: number
  delivery_score: number
  service_score: number
  price_score: number
  overall_score: number
  pros?: string
  cons?: string
  eval_date: string
  evaluator?: { id: number; name: string }
  project?: { id: number; name: string }
}

export interface SupplierAccountInfo {
  user_id: number
  username: string
  new_password: string
}

export interface SupplierAccount {
  enabled?: boolean
  username?: string
  password?: string
  allowed_modules?: string[]
}

export interface SupplierDetail {
  supplier: Supplier
  payable_total: number
  paid_total: number
  balance_total: number
  quote_count: number
  avg_rating: number
}

// ============= API =============

export const supplier = {
  // 列表
  list: (params?: {
    keyword?: string
    type?: string
    status?: string
    page?: number
    per_page?: number
  }) => get('/suppliers', params),

  // 详情
  get: (id: number) => get(`/suppliers/${id}`),

  // 新建（带账号 + 联系人）
  create: (data: {
    name: string
    type: Supplier['type']
    contact_person?: string
    phone?: string
    email?: string
    address?: string
    category?: string
    business_license?: string
    legal_person?: string
    registered_capital?: number
    website?: string
    bank_name?: string
    bank_account?: string
    account_name?: string
    tax_no?: string
    payment_terms?: Supplier['payment_terms']
    rating?: number
    status?: Supplier['status']
    remark?: string
    contacts?: SupplierContact[]
    account?: SupplierAccount
  }) => post('/suppliers', data),

  // 更新
  update: (id: number, data: Partial<Supplier> & { contacts?: SupplierContact[] }) =>
    put(`/suppliers/${id}`, data),

  // 删除
  remove: (id: number) => del(`/suppliers/${id}`),

  // 状态变更
  changeStatus: (id: number, status: Supplier['status']) =>
    post(`/suppliers/${id}/status`, { status }),

  // 同步联系人
  syncContacts: (id: number, contacts: SupplierContact[]) =>
    post(`/suppliers/${id}/contacts`, { contacts }),

  // 评价列表
  evaluations: (id: number, params?: Record<string, unknown>) => get(`/suppliers/${id}/evaluations`, params),

  // 新增评价
  addEvaluation: (id: number, data: Partial<SupplierEvaluation>) =>
    post(`/suppliers/${id}/evaluations`, data),

  // 重置账号密码
  resetAccount: (id: number, password?: string) =>
    post(`/suppliers/${id}/reset-account`, { password }),
}
