// Contract 共享
export const STATUS_OPTIONS = [
  { value: 'draft',     label: '草稿' },
  { value: 'signed',    label: '已签订' },
  { value: 'shipping',  label: '运输中' },
  { value: 'completed', label: '已完成' },
  { value: 'cancelled', label: '已取消' },
]

export const STATUS_TAG: Record<string, 'info' | 'success' | 'warning' | 'danger' | 'primary'> = {
  draft:     'info',
  signed:    'primary',
  shipping:  'warning',
  completed: 'success',
  cancelled: 'danger',
}
export const statusLabel = (s: string) => STATUS_OPTIONS.find(o => o.value === s)?.label || s
export const statusTagType = (s: string): 'info' | 'success' | 'warning' | 'danger' | 'primary' => STATUS_TAG[s] || 'info'

export const formatMoney = (n: number | string | null | undefined) => Number(n || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

export const sliceDate = (s?: string) => s ? String(s).slice(0, 10) : '-'
