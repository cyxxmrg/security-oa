// Process shared types & constants — v0.3.14
// 7 段工序状态映射 (与 backend ProcessController 一致)

export type ProcessStatus =
  | 'pending' | 'in_progress' | 'completed' | 'accepted' | 'rejected' | 'cancelled' | 'blocked' | 'overdue'

export type InspectionResult = 'pass' | 'fail' | 'rectify' | 'pending' | 'partial'
export type InspectionType = 'self' | 'mutual' | 'supervisor' | 'owner'

export const STATUS_OPTIONS: { value: ProcessStatus; label: string }[] = [
  { value: 'pending', label: '待开始' },
  { value: 'in_progress', label: '进行中' },
  { value: 'completed', label: '已完成' },
  { value: 'accepted', label: '已验收' },
  { value: 'rejected', label: '已驳回' },
  { value: 'cancelled', label: '已取消' },
  { value: 'blocked', label: '已阻塞' },
]

export const STATUS_TAG_TYPE: Record<ProcessStatus, 'primary' | 'success' | 'info' | 'warning' | 'danger'> = {
  pending: 'info',
  in_progress: 'primary',
  completed: 'success',
  accepted: 'success',
  rejected: 'danger',
  cancelled: 'info',
  blocked: 'warning',
  overdue: 'warning',
}

export const statusLabel = (s?: string): string =>
  STATUS_OPTIONS.find((o) => o.value === s)?.label || s || '-'

export const statusTagType = (s?: string): 'primary' | 'success' | 'info' | 'warning' | 'danger' =>
  (STATUS_TAG_TYPE as Record<string, unknown>)[s || ''] || 'info'

// 行业映射
export const INDUSTRY_MAP: Record<string, string> = {
  security: '安防',
  network: '网络',
  electrical: '电气',
  fire: '消防',
  weakcurrent: '弱电',
  hvac: '暖通',
  civil: '土建',
}

export const industryLabel = (k?: string): string => (k ? INDUSTRY_MAP[k] || k : '-')

// 验收结果（前端 UI 枚举 + 后端枚举兼容）
export const RESULT_OPTIONS: { value: InspectionResult; label: string }[] = [
  { value: 'pass', label: '合格' },
  { value: 'fail', label: '不合格' },
  { value: 'rectify', label: '整改' },
  { value: 'pending', label: '待定' },
]

export const RESULT_TAG_TYPE: Record<string, 'primary' | 'success' | 'info' | 'warning' | 'danger'> = {
  pass: 'success',
  fail: 'danger',
  rectify: 'warning',
  partial: 'warning',
  pending: 'info',
}

// 后端 (pending/pass/fail/partial) → 前端 (pass/fail/rectify/pending)
export const resultKey = (r?: string): InspectionResult => {
  if (r === 'partial' || r === 'rectify') return 'rectify'
  return (r as InspectionResult) || 'pending'
}

export const resultLabel = (r?: string): string =>
  RESULT_OPTIONS.find((o) => o.value === r)?.label || r || '-'

export const resultTagType = (r?: string): 'primary' | 'success' | 'info' | 'warning' | 'danger' =>
  (RESULT_TAG_TYPE as Record<string, unknown>)[r || ''] || 'info'

// 前端 UI (rectify) → 后端 (partial)
export const toBackendResult = (r: string): string => (r === 'rectify' ? 'partial' : r)

// 验收类型
export const INSPECTION_TYPE_OPTIONS: { value: InspectionType; label: string }[] = [
  { value: 'self', label: '自检' },
  { value: 'mutual', label: '互检' },
  { value: 'supervisor', label: '监理' },
  { value: 'owner', label: '业主' },
]

export const inspectionTypeLabel = (k?: string): string =>
  INSPECTION_TYPE_OPTIONS.find((o) => o.value === k)?.label || k || '-'

// 进度条颜色
export const progressColor = (p: number): string => {
  if (p < 30) return '#A32D2D'
  if (p <= 70) return '#BA7517'
  return '#1D9E75'
}

// helpers
export const formatDate = (s?: string | null): string => {
  if (!s) return '-'
  if (typeof s === 'string' && /^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10)
  return s
}

export const formatDateTime = (s?: string | null): string => {
  if (!s) return '-'
  const d = new Date(s)
  if (isNaN(d.getTime())) return s
  const pad = (n: number) => n.toString().padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export const getInspectorName = (row: Record<string, unknown>): string =>
  row.inspector?.name || row.inspector_name || ''

export const getDefects = (row: Record<string, unknown>): string => {
  if (row.defects) return row.defects
  if (Array.isArray(row.issues)) return row.issues.filter(Boolean).join('；')
  if (typeof row.issues === 'string' && row.issues) return row.issues
  return ''
}

// 工序实例类型
export interface ProcessInstance {
  id: number
  project_id: number
  project_name?: string
  project?: { id: number; name: string; code?: string }
  template_id: number
  template_name?: string
  template?: { id: number; name: string; code?: string; quality_checkpoints?: string[]; acceptance_criteria?: string[] }
  parent_id?: number | null
  parent?: { id: number; name: string; code?: string }
  children?: Array<{ id: number; name: string; status: string }>
  foreman_id?: number | null
  foreman?: { id: number; name: string }
  foreman_name?: string
  responsible_id?: number
  responsible_name?: string
  sequence?: number
  industry?: string
  code?: string
  name?: string
  status: string
  planned_start_date?: string
  planned_end_date?: string
  planned_start?: string
  planned_end?: string
  actual_start_date?: string
  actual_end_date?: string
  actual_start?: string
  actual_end?: string
  planned_duration_days?: number
  planned_duration?: number
  actual_duration_days?: number
  progress: number
  is_overdue?: boolean
  location?: string
  description?: string
  remark?: string
  acceptance_points?: string
  accepted_at?: string | null
  acceptedByUser?: { id: number; name: string }
  accepted_by?: number | null
  created_at?: string
  updated_at?: string
  inspections?: Inspection[]
}

export interface Inspection {
  id: number
  process_instance_id?: number
  inspection_type?: string
  inspector_id?: number | null
  inspector?: { id: number; name: string }
  inspector_name?: string
  inspection_date?: string
  inspected_at?: string
  result?: string
  score?: number | null
  issues?: string[] | string | null
  defects?: string | null
  remark?: string | null
}
