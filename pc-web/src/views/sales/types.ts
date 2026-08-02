// Opportunity shared constants & types — v0.3.13
// 6 段阶段值映射 (与 backend SalesController::STAGES 一致)
// V1.2.12: 线索 Lead 模块删除

import type { QuoteItem } from './quoteTypes'

/** Element Plus 标签类型（el-tag :type 接受） */
export type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

/** 商机 视图模型 */
export interface Opportunity {
  id?: number | string
  opp_no?: string
  name?: string | null
  customer_id?: number | null
  customer?: { id?: number; name?: string } | null
  stage?: string | null
  status?: string | null
  probability?: number | null
  estimated_amount?: number | string | null
  sales_id?: number | null
  presale_id?: number | null
  sales?: { id?: number; name?: string } | null
  expected_sign_date?: string | null
  referrer_id?: number | null
  notes?: string | null
  [key: string]: unknown
}
export interface Referrer {
  id?: number
  name?: string | null
  phone?: string | null
  customer_id?: number | null
  bank_name?: string | null
  bank_account?: string | null
  commission_rate?: number | string | null
  total_commission?: number | string | null
  notes?: string | null
  customer?: { name?: string } | null
  [key: string]: unknown
}

/** 推荐人居间费结算单 视图模型 */
export interface Settlement {
  id?: number
  opportunity_id?: number
  opportunity?: { name?: string } | null
  referrer_id?: number
  referrer?: { name?: string } | null
  amount?: number | string
  commission_rate?: number | string
  contract_amount?: number | string
  status?: string | null
  approved_at?: string | null
  approver?: { name?: string } | null
  paid_at?: string | null
  payment_no?: string | null
  creator?: { name?: string } | null
  payer?: { name?: string } | null
  payment_voucher?: string | null
  notes?: string | null
  created_at?: string | null
  [key: string]: unknown
}

/** 结算统计 视图模型 */
export interface SettlementStats {
  pending?: number
  approved?: number
  paid?: number
  cancelled?: number
  total_amount_pending?: number | string
  total_amount_approved?: number | string
  total_amount_paid?: number | string
  [key: string]: unknown
}

/** 产品库 产品 视图模型 */
export interface Product {
  id?: number
  code?: string
  name?: string
  spec?: string
  unit?: string
  sale_price?: number | string
  category?: { name?: string } | null
  [key: string]: unknown
}

/** 产品库 分类 视图模型 */
export interface ProductCategory {
  id?: number
  name?: string
  [key: string]: unknown
}

/** 客户下拉选项 */
export interface CustomerOption {
  id: number
  name: string
}

/** 报价单 视图模型 */
export interface Quote {
  id?: number
  quote_no?: string | null
  version?: number | string | null
  status?: string | null
  created_at?: string | null
  valid_until?: string | null
  discount_rate?: number | string | null
  tax_rate?: number | string | null
  total_amount?: number | string | null
  items?: QuoteItem[]
  [key: string]: unknown
}

// 8 段枚举 — V1.2.12b 现场地勘独立成段
// DB 实际值: inquiry/qualification/site_survey/proposal/negotiating/quoted/won/lost
export type StageValue =
  | 'inquiry' | 'qualification' | 'site_survey' | 'proposal' | 'negotiating' | 'quoted' | 'won' | 'lost'

export interface StageOption {
  value: StageValue
  label: string
  color: string
  icon?: string
}

export const STAGE_OPTIONS: StageOption[] = [
  { value: 'inquiry',       label: '需求确认', color: '#0C447C' },
  { value: 'qualification', label: '资质评估', color: '#185FA5' },
  { value: 'site_survey',   label: '现场地勘', color: '#3A8FCD' },
  { value: 'proposal',      label: '方案设计', color: '#2680C2' },
  { value: 'negotiating',   label: '报价谈判', color: '#BA7517' },
  { value: 'quoted',        label: '已报价',   color: '#D4961F' },
  { value: 'won',           label: '成交',     color: '#67C23A' },
  { value: 'lost',          label: '战败',     color: '#A32D2D' },
]

export const STAGE_TAG_TYPE: Record<StageValue, 'primary' | 'info' | 'warning' | 'success' | 'danger'> = {
  inquiry: 'primary',
  qualification: 'info',
  site_survey: 'info',
  proposal: 'info',
  negotiating: 'warning',
  quoted: 'warning',
  won: 'success',
  lost: 'danger',
}

export const stageLabel = (s?: string): string =>
  STAGE_OPTIONS.find((o) => o.value === s)?.label || s || '-'

export const stageTagType = (s?: string): TagType =>
  (STAGE_TAG_TYPE as Record<string, TagType>)[s || ''] || 'info'

export const probabilityColor = (p?: number): string => {
  const v = Number(p) || 0
  if (v >= 70) return '#1D9E75'
  if (v >= 40) return '#0C447C'
  return '#BA7517'
}

export const formatMoney = (n?: number) =>
  Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 0 })

export const formatDate = (d?: string | null) => (d ? String(d).slice(0, 10) : '-')

// 终态判断
export const isClosed = (s?: string) => s === 'won' || s === 'lost'
