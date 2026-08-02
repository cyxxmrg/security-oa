// purchase/Requirement 子组件共享 types
// v0.3.23 从 views/purchase/Requirement.vue 抽出

export interface MaterialItem {
  inventory_item_id?: number | null
  name: string
  spec: string
  quantity: number
  unit: string
}

export interface Requirement {
  id: number
  code: string
  project_id: number
  project_name?: string
  material: string
  spec?: string
  quantity: number
  unit?: string
  need_date?: string
  priority: 'urgent' | 'high' | 'medium' | 'low' | string
  creator?: string
  status: 'pending' | 'approved' | 'rejected' | 'cancelled' | string
  review_remark?: string
  remark?: string
  created_at?: string
}

export interface RequirementFilters {
  project_id: number | null
  status: string
  priority: string
  keyword: string
}

export interface RequirementForm {
  id: number
  project_id: number | null
  need_date: string
  priority: string
  creator: string
  materials: MaterialItem[]
  remark: string
}

export interface ProjectOption { id: number; name: string }

export type FormMode = 'create' | 'edit'
export type TagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'

// ============ 状态/优先级选项 ============

export const STATUS_OPTIONS = [
  { value: 'pending', label: '待审核' },
  { value: 'approved', label: '已通过' },
  { value: 'rejected', label: '已驳回' },
  { value: 'cancelled', label: '已取消' },
]

export const PRIORITY_OPTIONS = [
  { value: 'urgent', label: '紧急' },
  { value: 'high', label: '高' },
  { value: 'medium', label: '中' },
  { value: 'low', label: '低' },
]

// ============ 工具函数 ============

export const formatDate = (d?: string): string => d ? String(d).slice(0, 10) : '-'

export const statusLabel = (s: string): string =>
  STATUS_OPTIONS.find((o) => o.value === s)?.label || s || '-'

export const statusTagType = (s: string): TagType => {
  if (s === 'approved') return 'success'
  if (s === 'pending') return 'warning'
  if (s === 'rejected') return 'danger'
  if (s === 'cancelled') return 'info'
  return 'info'
}

export const priorityLabel = (p: string): string =>
  PRIORITY_OPTIONS.find((o) => o.value === p)?.label || p || '-'

export const priorityTagType = (p: string): TagType => {
  if (p === 'urgent') return 'danger'
  if (p === 'high') return 'danger'
  if (p === 'medium') return 'warning'
  if (p === 'low') return 'info'
  return 'info'
}

export const isOverdue = (d?: string, status?: string): boolean => {
  if (status === 'approved' || status === 'rejected' || status === 'cancelled') return false
  if (!d) return false
  return String(d).slice(0, 10) < new Date().toISOString().slice(0, 10)
}

export const parseMaterials = (row: Requirement): MaterialItem[] => [{
  name: row.material,
  spec: row.spec || '',
  quantity: row.quantity,
  unit: row.unit || '件',
}]

export const emptyForm = (): RequirementForm => ({
  id: 0,
  project_id: null,
  need_date: '',
  priority: 'medium',
  creator: '',
  materials: [{ inventory_item_id: null, name: '', spec: '', quantity: 1, unit: '件' }],
  remark: '',
})
