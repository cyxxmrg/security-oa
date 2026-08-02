// Resignations 共享类型 + 文案
import type { Component } from 'vue'

export interface Resignation {
  id: number
  user_id: number
  user?: { id: number; name?: string; username?: string }
  resign_type?: string
  status?: string
  notice_date?: string
  last_work_day?: string
  resign_date?: string
  reason?: string
  handover_to_user_id?: number
  handover_note?: string
  assets?: Array<{ name: string; returned: boolean; note: string }>
  final_salary?: number
  leave_balance?: number
  severance_pay?: number
  social_security_cutoff?: string
  total_amount?: number
  all_assets_returned?: boolean
  paid_date?: string
  paid_method?: string
  certificate_file_id?: number
  certificate_file_name?: string
  created_at?: string
  updated_at?: string
}

export interface ResignationStat {
  label: string
  value: string
  icon: Component
  color: string
}

// 状态 → {label, tag}
export const STATUS_MAP: Record<string, { label: string; type: 'warning' | 'success' | 'info' | 'danger' }> = {
  draft:     { label: '草稿',   type: 'info' },
  pending:   { label: '待审批', type: 'warning' },
  approved:  { label: '已审批', type: 'success' },
  completed: { label: '已办结', type: 'success' },
  cancelled: { label: '已取消', type: 'danger' },
}
export const statusLabel = (s?: string) => s ? (STATUS_MAP[s]?.label || s) : '—'
export const statusTag   = (s?: string): 'warning' | 'success' | 'info' | 'danger' => (s ? (STATUS_MAP[s]?.type || 'info') : 'info')

// 离职类型 → {label, tag}
export const RESIGN_TYPE_MAP: Record<string, { label: string; type: 'success' | 'warning' | 'danger' | 'info' | '' }> = {
  voluntary:           { label: '主动辞职',     type: '' },
  contract_end:        { label: '合同到期不续签', type: 'info' },
  mutual:              { label: '协商解除',     type: 'warning' },
  dismissed:           { label: '公司辞退',     type: 'danger' },
  probation_dismissed: { label: '试用期辞退',   type: 'danger' },
}
export const resignTypeLabel = (s?: string) => s ? (RESIGN_TYPE_MAP[s]?.label || s) : '—'
export const resignTypeTag = (s?: string): 'success' | 'warning' | 'danger' | 'info' | '' =>
  s ? (RESIGN_TYPE_MAP[s]?.type || 'info') : 'info'

// todayStr YYYY-MM-DD
export const todayStr = (): string => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
