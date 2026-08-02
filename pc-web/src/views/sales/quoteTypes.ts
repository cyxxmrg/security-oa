// Quotes 共享常量 & 状态机 — v0.3.13
// 6 段报价状态映射 (与 backend SalesController::QUOTE_STATUS 一致)

import type { TagType } from './types'

export type QuoteStatus = 'draft' | 'submitted' | 'negotiating' | 'accepted' | 'rejected' | 'expired'

export const QUOTE_STATUS_OPTIONS: { value: QuoteStatus; label: string }[] = [
  { value: 'draft', label: '草稿' },
  { value: 'submitted', label: '已提交' },
  { value: 'negotiating', label: '谈判中' },
  { value: 'accepted', label: '客户接受' },
  { value: 'rejected', label: '客户拒绝' },
  { value: 'expired', label: '已过期' },
]

export const QUOTE_STATUS_TAG_TYPE: Record<QuoteStatus, 'primary' | 'info' | 'warning' | 'success' | 'danger'> = {
  draft: 'info',
  submitted: 'primary',
  negotiating: 'warning',
  accepted: 'success',
  rejected: 'danger',
  expired: 'info',
}

// 报价单状态机:每个状态对应 el-steps 步骤 (0-3)
export const QUOTE_STATUS_STEP: Record<QuoteStatus, number> = {
  draft: 0,
  submitted: 1,
  negotiating: 2,
  accepted: 3,
  rejected: 2,
  expired: 1,
}

export const quoteStatusLabel = (s?: string): string =>
  QUOTE_STATUS_OPTIONS.find((o) => o.value === s)?.label || s || '-'

export const quoteStatusTagType = (s?: string): TagType =>
  (QUOTE_STATUS_TAG_TYPE as Record<string, TagType>)[s || ''] || 'info'

export const quoteStatusStep = (s?: string): number =>
  (QUOTE_STATUS_STEP as Record<string, number>)[s ?? ''] ?? 0

export const formatMoney = (n?: number | string) =>
  Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2, minimumFractionDigits: 2 })

export const formatDate = (d?: string | null) => (d ? String(d).slice(0, 10) : '-')

export const formatDateTime = (d?: string | null) =>
  d ? String(d).slice(0, 16).replace('T', ' ') : '-'

// items 行类型 (前端 view-model)
export interface QuoteItem {
  id?: number
  key_?: string
  product_id?: number | null
  code?: string
  name: string
  specification?: string
  spec?: string
  unit?: string
  quantity: number
  unit_price: number
  _edit?: boolean
}
