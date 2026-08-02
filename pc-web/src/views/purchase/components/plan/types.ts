// 采购计划 共享类型
export const STATUS_OPTIONS_PLAN = [
  { value: 'draft',     label: '草稿' },
  { value: 'submitted', label: '已提交' },
  { value: 'approved',  label: '已通过' },
  { value: 'rejected',  label: '已驳回' },
  { value: 'completed', label: '已完成' },
]

export const statusLabel = (s: string) => STATUS_OPTIONS_PLAN.find(o => o.value === s)?.label || s
export const statusTagType = (s: string): 'info' | 'success' | 'warning' | 'danger' | 'primary' => {
  if (s === 'draft') return 'info'
  if (s === 'submitted') return 'warning'
  if (s === 'approved' || s === 'completed') return 'success'
  if (s === 'rejected') return 'danger'
  return 'primary'
}

export const formatMoney = (n: number | string | null | undefined) => Number(n || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
export const sliceDate = (s?: string) => s ? String(s).slice(0, 10) : '-'
