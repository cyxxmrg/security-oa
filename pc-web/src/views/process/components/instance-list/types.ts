// InstanceList 子组件共享 types
// v0.3.19 从 process/InstanceList.vue 抽出

import type { Component } from 'vue'

export interface Instance {
  id: number
  project_id: number
  project_name?: string
  project?: { id: number; name: string; code?: string }
  template_id: number
  template_name?: string
  template?: { id: number; name: string; code?: string }
  parent_id?: number | null
  parent?: { id: number; name: string; code?: string }
  foreman_id?: number | null
  foreman_name?: string
  foreman?: { id: number; name: string }
  acceptedByUser?: { id: number; name: string }
  code?: string
  name?: string
  sequence?: number
  planned_start_date?: string
  planned_end_date?: string
  planned_start?: string
  planned_end?: string
  actual_start_date?: string
  actual_end_date?: string
  actual_start?: string
  actual_end?: string
  planned_duration_days?: number
  progress: number
  status: string
  is_overdue?: boolean
  location?: string
  description?: string
  accepted_at?: string | null
  created_at?: string
  updated_at?: string
  inspections?: unknown[]
  [key: string]: unknown
}

export interface InstanceStat {
  key: keyof InstanceStats
  label: string
  value: number
  color: string
  bg: string
  icon: Component
}

export interface InstanceStats {
  in_progress: number
  accepted: number
  rejected: number
  overdue: number
}

export interface SearchForm {
  project_id: number | null
  status: string
  is_overdue: boolean
}

export interface ProjectOption { id: number; name: string }
export interface UserOption { id: number; name: string }
export interface TemplateOption {
  id: number
  name: string
  code?: string
  industry?: string
  category?: string
  standard_duration_days?: number
  standard_man_hours?: number
  [key: string]: unknown
}

export interface OptionItem { value: string; label: string }

export const STATUS_OPTIONS: OptionItem[] = [
  { value: 'pending', label: '待开始' },
  { value: 'in_progress', label: '进行中' },
  { value: 'accepted', label: '已验收' },
  { value: 'rejected', label: '已驳回' },
  { value: 'overdue', label: '超期' },
]

export const REJECT_REASONS = ['质量问题', '工期延误', '材料不达标', '工艺不符', '其他']

// 状态 tag 类型
export type StatusTagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'
export const STATUS_TAG_TYPE_MAP: Record<string, StatusTagType> = {
  pending: 'info',
  in_progress: 'primary',
  accepted: 'success',
  rejected: 'danger',
  overdue: 'warning',
}

// 进度颜色: < 30 红, 30-70 橙, > 70 绿
export const progressColor = (p: number): string => {
  if (p < 30) return '#A32D2D'
  if (p <= 70) return '#BA7517'
  return '#1D9E75'
}

// 状态 → 标签
export const statusLabel = (s: string): string => {
  if (s === 'overdue') return '超期'
  return STATUS_OPTIONS.find(o => o.value === s)?.label || s
}

// 状态 → tag 类型
export const statusTagType = (s: string): StatusTagType => {
  return STATUS_TAG_TYPE_MAP[s] || 'info'
}
