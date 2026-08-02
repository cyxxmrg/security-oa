// process/TemplateList 子组件共享 types
// v0.3.25 从 views/process/TemplateList.vue 抽出
// v1.2.12p 字段对齐后端 process_templates 表 schema

export interface ProcessTemplate {
  id: number
  industry: string
  category: string
  code: string
  name: string
  description?: string | null
  standard_duration_days?: number
  standard_man_hours?: number
  required_qualifications?: string[]
  safety_requirements?: string | null
  quality_checkpoints?: unknown
  acceptance_criteria?: string[]
  sort_order: number
  is_active: boolean
  created_at?: string
  _statusLoading?: boolean
}

export interface TemplateStats {
  total: number
  active: number
  industryCount: number
  todayNew: number
}

export interface SearchForm {
  industry: string
  keyword: string
}

// 项目类型枚举 (V1.2.12p 用户要求: 监控工程/安防工程/网络工程/综合项目/其他工程)
export const INDUSTRY_MAP: Record<string, string> = {
  monitor:       '监控工程',
  security:      '安防工程',
  network:       '网络工程',
  comprehensive: '综合项目',
  other:         '其他工程',
}

export const INDUSTRY_COLORS: Record<string, { bg: string; color: string }> = {
  monitor:       { bg: '#dbeafe', color: '#0C447C' },
  security:      { bg: '#fee2e2', color: '#A32D2D' },
  network:       { bg: '#dcfce7', color: '#1D9E75' },
  comprehensive: { bg: '#fef3c7', color: '#BA7517' },
  other:         { bg: '#f1f5f9', color: '#475569' },
}

export const formatDate = (s?: string): string => s ? s.replace('T', ' ').slice(0, 16) : '-'

export const emptyStats = (): TemplateStats => ({
  total: 0, active: 0, industryCount: 0, todayNew: 0,
})

export interface TemplateForm {
  id: number
  industry: string
  category: string
  code: string
  name: string
  description: string
  standard_duration_days: number
  standard_man_hours: number
  required_qualifications: string[]
  safety_requirements: string
  acceptance_criteria: string[]
  sort_order: number
  is_active: boolean
}

export const defaultTemplateForm = (): TemplateForm => ({
  id: 0,
  industry: '',
  category: '',
  code: '',
  name: '',
  description: '',
  standard_duration_days: 1,
  standard_man_hours: 0,
  required_qualifications: [],
  safety_requirements: '',
  acceptance_criteria: [],
  sort_order: 0,
  is_active: true,
})