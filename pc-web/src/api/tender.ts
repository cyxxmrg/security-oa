import { get, post, put, del } from '@/utils/request'

/**
 * V0.6.0 招标中心 — 内部 API
 *
 * 端点：/api/tenders + /api/portal (内部用, 走 Bearer token)
 */

// ============= 类型定义 =============

export interface TenderProject {
  id: number
  code: string
  name: string
  description?: string
  type: 'rfq' | 'tender' | 'negotiation'
  status: 'draft' | 'bidding' | 'evaluating' | 'awarded' | 'cancelled' | 'closed'
  status_label?: string
  public_token?: string
  project_id?: number
  project?: { id: number; name: string; code?: string }
  rfq_id?: number
  required_items?: Array<{ name: string; spec?: string; qty: number; unit?: string }>
  invited_supplier_ids?: number[]
  deadline?: string
  open_at?: string
  publish_at?: string
  awarded_at?: string
  score_config?: { technical: number; price: number; business: number }
  awarded_bid_id?: number
  awarded_supplier_id?: number
  awardedSupplier?: { id: number; name: string; code?: string }
  creator?: { id: number; name: string }
  created_by?: number
  bids_summary?: TenderBid[]
  attachments?: TenderAttachment[]
}

export interface TenderBid {
  id: number
  code: string
  tender_project_id: number
  supplier_id: number
  supplier?: { id: number; name: string; code?: string }
  total_amount: number
  lead_time_days?: number
  technical_proposal?: string
  remark?: string
  status: 'draft' | 'submitted' | 'shortlisted' | 'awarded' | 'rejected' | 'withdrawn'
  status_label?: string
  submitted_at?: string
  scores?: { technical: number; price: number; business: number }
  total_score?: number
  items?: TenderBidItem[]
}

export interface TenderBidItem {
  id: number
  bid_id: number
  name: string
  spec?: string
  unit?: string
  quantity: number
  unit_price: number
  total_price: number
}

export interface TenderAttachment {
  id: number
  tender_project_id?: number
  tender_bid_id?: number
  uploaded_by_user_id?: number
  uploaded_by_supplier_id?: number
  file_name: string
  file_path: string
  mime_type?: string
  file_size?: number
  category: string
  visibility: 'public' | 'eval_only'
  url?: string
  created_at?: string
}

// V0.6.5 Sprint 4 — 状态机扩展类型
export type TenderStatus =
  | 'draft' | 'pending_review' | 'open'
  | 'withdrawn' | 'rejected' | 'cancelled' | 'closed'

// V0.6.5 Sprint 4 — 保证金类型
export interface TenderDepositRule {
  id: number
  tender_project_id: number
  required: boolean
  amount: number
  deadline_hours_before_open: number
  refund_policy?: { auto_refund_days: number; forfeit_on_no_contract_sign_days: number }
  bank_account?: string
  note?: string
}

export interface TenderDeposit {
  id: number
  tender_project_id: number
  supplier_id: number
  supplier?: { id: number; name: string; code?: string; contact_name?: string }
  amount: number
  status: 'pending' | 'paid' | 'refunded' | 'forfeited' | 'partial_refund'
  status_label?: string
  paid_at?: string
  paid_voucher_path?: string
  refunded_at?: string
  refund_amount?: number
  refund_reason?: string
  refund_method?: string
  forfeited_at?: string
  forfeit_reason?: string
  marked_paid_by?: number
  refunded_by?: number
  forfeited_by?: number
  markedPaidByUser?: { id: number; name: string }
  refundedByUser?: { id: number; name: string }
  forfeitedByUser?: { id: number; name: string }
  created_at?: string
  updated_at?: string
}

// ============= API 封装 =============

export const tender = {
  // 列表
  list: (params?: {
    keyword?: string
    status?: string
    project_id?: number
    per_page?: number
  }) => get('/tenders', params),

  // 详情
  get: (id: number) => get(`/tenders/${id}`),

  // 新建 (草稿)
  create: (data: Partial<TenderProject>) => post('/tenders', data),

  // 修改
  update: (id: number, data: Partial<TenderProject>) => put(`/tenders/${id}`, data),

  // 发布 (旧 V0.6.0 接口, 等同 submit-review→approve 快捷)
  publish: (id: number) => post(`/tenders/${id}/publish`),

  // 关闭
  close: (id: number) => post(`/tenders/${id}/close`),

  // 取消 (旧 V0.6.0 接口, 直接置 cancelled, 不需 reason)
  cancel: (id: number) => post(`/tenders/${id}/cancel`),

  // V0.6.5 Sprint 4: 状态机操作
  submitReview: (id: number, note?: string) => post(`/tenders/${id}/submit-review`, { note }),
  approve: (id: number, note?: string) => post(`/tenders/${id}/approve`, { note }),
  reject: (id: number, reason: string, backToDraft = false) =>
    post(`/tenders/${id}/reject`, { reason, back_to_draft: backToDraft }),
  withdraw: (id: number, reason: string) => post(`/tenders/${id}/withdraw`, { reason }),
  cancelV2: (id: number, reason: string) => post(`/tenders/${id}/cancel-v2`, { reason }),

  // V0.6.5 Sprint 4: 审核队列
  pendingReview: () => get('/tenders/pending-review'),

  // 评标
  evaluate: (id: number, evaluations: Array<{ bid_id: number; technical: number; price: number; business: number }>) =>
    post(`/tenders/${id}/evaluate`, { evaluations }),

  // 中标 (V0.6.4 自动联动 PO+Payable, V0.6.5 还联动保证金)
  award: (id: number, bid_id: number) => post(`/tenders/${id}/award`, { bid_id }),

  // V0.6.4 招标联动 - 联查下游 (PO + Payable + 入库)
  downstream: (id: number) => get(`/tenders/${id}/downstream`),

  // 投标列表 (含 items)
  listBids: (id: number) => get(`/tenders/${id}/bids`),

  // 内部代投 (E2E 用)
  createBidAsAdmin: (id: number, data: unknown) => post(`/tenders/${id}/bids`, data),

  // 附件
  listAttachments: (id: number) => get(`/tenders/${id}/attachments`),
  uploadAttachment: (id: number, formData: FormData) => post(`/tenders/${id}/attachments`, formData),
  deleteAttachment: (id: number, attId: number) => del(`/tenders/${id}/attachments/${attId}`),

  // V0.6.5 Sprint 4: 保证金 6 端点
  setDepositRule: (id: number, data: Partial<TenderDepositRule>) => put(`/tenders/${id}/deposit-rule`, data),
  listDeposits: (id: number) => get(`/tenders/${id}/deposits`),
  createDeposit: (id: number, supplier_id: number, amount?: number) =>
    post(`/tenders/${id}/deposits`, { supplier_id, amount }),
  markDepositPaid: (id: number, depositId: number, voucherPath?: string) =>
    post(`/tenders/${id}/deposits/${depositId}/mark-paid`, { voucher_path: voucherPath }),
  refundDeposit: (id: number, depositId: number, data: {
    refund_amount: number; method: 'bank_transfer' | 'cash' | 'original_channel'; reason: string; voucher_path?: string
  }) => post(`/tenders/${id}/deposits/${depositId}/refund`, data),
  forfeitDeposit: (id: number, depositId: number, reason: string) =>
    post(`/tenders/${id}/deposits/${depositId}/forfeit`, { reason }),
}
