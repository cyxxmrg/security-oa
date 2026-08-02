// process/InspectionList 子组件共享 types
// v0.3.25 从 views/process/InspectionList.vue 抽出
// V1.2.13: 字段对齐后端 process_inspections 表 + processInstance.project 关联

export interface Inspection {
  id: number
  process_instance_id?: number
  // V1.2.13: Laravel 模型关系方法名 processInstance() / inspector() 序列化后是下划线
  process_instance?: {
    id: number
    name?: string
    code?: string
    project_id?: number
    project?: { id: number; name?: string; project_no?: string } | null
  }
  inspection_type?: string
  inspector_id?: number | null
  inspector?: { id: number; name?: string }
  inspection_date?: string
  result?: 'pass' | 'fail' | 'partial' | 'pending' | string
  score?: number | null
  remark?: string | null
  issues?: string[] | string | null
  images?: unknown[]
  signatures?: unknown[]
  created_at?: string
  [key: string]: unknown
}

export interface InspectionStats {
  total: number
  pass: number
  fail: number
}

export interface InspectionFilters {
  project_id: number | null
  process_instance_id: number | null
  result: string
}

export interface ProjectOption { id: number; name: string }
export interface ProcessInstanceOption { id: number; label: string }

export const RESULT_OPTIONS = [
  { value: 'pass', label: '合格' },
  { value: 'fail', label: '不合格' },
  { value: 'partial', label: '部分合格' },
  { value: 'pending', label: '待验收' },
]

export const formatDate = (s?: string): string =>
  s ? String(s).replace('T', ' ').slice(0, 16) : '-'

export const resultLabel = (r?: string): string => {
  const map: Record<string, string> = { pass: '合格', fail: '不合格', partial: '部分合格', pending: '待验收' }
  return map[r || ''] || r || '-'
}

export const resultTagType = (r?: string): 'success' | 'danger' | 'warning' | 'info' => {
  if (r === 'pass') return 'success'
  if (r === 'fail') return 'danger'
  if (r === 'partial') return 'warning'
  return 'info'
}

export const inspectionTypeLabel = (t?: string): string => {
  const map: Record<string, string> = { self: '自检', mutual: '互检', supervisor: '监理', owner: '甲方' }
  return map[t || ''] || t || '-'
}

export const formatIssues = (issues?: unknown): string => {
  if (!issues) return ''
  if (Array.isArray(issues)) return issues.map(i => typeof i === 'object' ? JSON.stringify(i) : String(i)).join('；')
  if (typeof issues === 'string') return issues
  return JSON.stringify(issues)
}

export const emptyStats = (): InspectionStats => ({ total: 0, pass: 0, fail: 0 })
