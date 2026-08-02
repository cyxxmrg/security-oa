import { get, post, put, del } from '@/utils/request'

/**
 * V0.7 巡检计划 — 前端 API
 *
 * 端点：/api/inspections
 */

// ============= 类型定义 =============

export type InspectionPlanStatus = 'active' | 'paused' | 'expired' | 'cancelled'
export type InspectionFrequency = 'weekly' | 'biweekly' | 'monthly' | 'quarterly' | 'semiannual' | 'yearly' | 'custom'
export type InspectionTaskStatus = 'pending' | 'in_progress' | 'completed' | 'overdue' | 'skipped' | 'cancelled'
export type InspectionRecordStatus = 'checked_in' | 'checked_out'
export type InspectionIssueStatus = 'open' | 'work_order_created' | 'resolved' | 'ignored'
export type InspectionIssueType = 'hardware' | 'software' | 'network' | 'power' | 'environment' | 'other'
export type InspectionSeverity = 'low' | 'medium' | 'high' | 'critical'

export interface InspectionPlan {
  id: number
  plan_no: string
  contract_id: number
  customer_id: number
  name: string
  frequency: InspectionFrequency
  cycle_day?: number | null
  cycle_weekday?: number | null
  custom_interval_days?: number | null
  duration_hours: number
  priority: number
  assigned_to?: string | null
  scope?: string | null
  checklist_template?: Record<string, unknown>[]
  start_date: string
  end_date?: string | null
  ahead_generate_days: number
  status: InspectionPlanStatus
  total_generated: number
  total_completed: number
  total_issues: number
  created_by?: number | null
  created_at: string
  updated_at: string
  contract?: { id: number; contract_no: string; amount: number; start_date: string; end_date?: string | null }
  customer?: { id: number; name: string; code?: string }
  tasks?: InspectionTask[]
  completion_rate?: number
  issue_rate?: number
}

export interface InspectionTask {
  id: number
  task_no: string
  plan_id: number
  contract_id: number
  customer_id: number
  scheduled_date: string
  scheduled_hour: number
  scheduled_at: string
  assigned_to?: number | null
  status: InspectionTaskStatus
  started_at?: string | null
  completed_at?: string | null
  duration_minutes?: number | null
  equipment_count: number
  issue_count: number
  overdue_notified: boolean
  overdue_notified_at?: string | null
  remark?: string | null
  created_at: string
  plan?: InspectionPlan
  customer?: { id: number; name: string; code?: string }
  assignee?: { id: number; name: string; username: string }
  record?: InspectionRecord | null
  issues?: InspectionIssue[]
}

export interface InspectionRecord {
  id: number
  record_no: string
  task_id: number
  plan_id: number
  user_id: number
  checkin_at: string
  checkin_location?: string | null
  checkin_lat?: number | null
  checkin_lng?: number | null
  checkin_photos?: string[]
  checkout_at?: string | null
  checkout_location?: string | null
  checkout_lat?: number | null
  checkout_lng?: number | null
  checklist_answers?: Record<string, unknown>
  normal_count: number
  abnormal_count: number
  summary?: string | null
  rating?: number | null
  status: InspectionRecordStatus
  created_at: string
  task?: InspectionTask
  plan?: InspectionPlan
  user?: { id: number; name: string; username: string }
  issues?: InspectionIssue[]
}

export interface InspectionIssue {
  id: number
  issue_no: string
  record_id: number
  task_id: number
  plan_id: number
  contract_id: number
  customer_id: number
  inventory_item_id?: number | null
  equipment_name: string
  equipment_location?: string | null
  issue_type: InspectionIssueType
  severity: InspectionSeverity
  title: string
  description: string
  photos?: string[]
  status: InspectionIssueStatus
  work_order_id?: number | null
  resolved_at?: string | null
  resolved_by?: string | null
  resolution?: string | null
  created_at: string
  record?: InspectionRecord
  task?: InspectionTask
  plan?: InspectionPlan
  contract?: { id: number; contract_no: string }
  customer?: { id: number; name: string }
  equipment?: { id: number; name: string; code?: string }
  workOrder?: { id: number; code: string; status: string }
}

export interface InspectionStats {
  total_plans: number
  active_plans: number
  monthly_tasks: number
  pending_tasks: number
  overdue_tasks: number
  completed_tasks: number
  open_issues: number
  monthly_issues: number
  auto_work_orders: number
}

// ============= 常量 =============

export const PLAN_STATUS_LABEL: Record<InspectionPlanStatus, string> = {
  active: '启用',
  paused: '暂停',
  expired: '到期',
  cancelled: '取消',
}

export const FREQUENCY_LABEL: Record<InspectionFrequency, string> = {
  weekly: '每周',
  biweekly: '每两周',
  monthly: '每月',
  quarterly: '每季度',
  semiannual: '每半年',
  yearly: '每年',
  custom: '自定义',
}

export const TASK_STATUS_LABEL: Record<InspectionTaskStatus, string> = {
  pending: '待执行',
  in_progress: '执行中',
  completed: '已完成',
  overdue: '已逾期',
  skipped: '已跳过',
  cancelled: '已取消',
}

export const ISSUE_STATUS_LABEL: Record<InspectionIssueStatus, string> = {
  open: '待处理',
  work_order_created: '已转工单',
  resolved: '已解决',
  ignored: '已忽略',
}

export const ISSUE_TYPE_LABEL: Record<InspectionIssueType, string> = {
  hardware: '硬件故障',
  software: '软件问题',
  network: '网络异常',
  power: '供电问题',
  environment: '环境异常',
  other: '其他',
}

export const SEVERITY_LABEL: Record<InspectionSeverity, string> = {
  low: '轻微',
  medium: '一般',
  high: '严重',
  critical: '紧急',
}

export const SEVERITY_COLOR: Record<InspectionSeverity, string> = {
  low: 'info',
  medium: 'warning',
  high: 'danger',
  critical: 'danger',
}

// ============= API =============

export const inspection = {
  // 统计
  stats: () => get<InspectionStats>('/inspections/stats'),
  overview: () => get<{
    stats: InspectionStats
    recentTasks: InspectionTask[]
    recentIssues: InspectionIssue[]
    upcomingTasks: InspectionTask[]
  }>('/inspections/overview'),
  activeContracts: () => get('/inspections/active-contracts'),

  // 计划
  listPlans: (params?: {
    keyword?: string
    status?: InspectionPlanStatus
    frequency?: InspectionFrequency
    contract_id?: number
    customer_id?: number
    per_page?: number
    page?: number
  }) => get('/inspections/plans', params),
  getPlan: (id: number) => get<InspectionPlan>(`/inspections/plans/${id}`),
  createPlan: (data: Partial<InspectionPlan>) => post<InspectionPlan>('/inspections/plans', data),
  updatePlan: (id: number, data: Partial<InspectionPlan>) => put<InspectionPlan>(`/inspections/plans/${id}`, data),
  deletePlan: (id: number) => del<{ deleted: boolean }>(`/inspections/plans/${id}`),
  togglePlan: (id: number) => post<InspectionPlan>(`/inspections/plans/${id}/toggle`),
  cancelPlan: (id: number, reason: string) => post<InspectionPlan>(`/inspections/plans/${id}/cancel`, { reason }),
  generateTasks: (id: number) => post<{ generated: number; plans: number }>(`/inspections/plans/${id}/generate`),

  // 任务
  listTasks: (params?: {
    plan_id?: number
    status?: InspectionTaskStatus
    assigned_to?: number
    contract_id?: number
    date_from?: string
    date_to?: string
    per_page?: number
    page?: number
  }) => get('/inspections/tasks', params),
  myTasks: (params?: { status?: InspectionTaskStatus; today?: boolean; per_page?: number; page?: number }) =>
    get('/inspections/tasks/mine', params),
  getTask: (id: number) => get<InspectionTask>(`/inspections/tasks/${id}`),
  skipTask: (id: number, reason?: string) => post<InspectionTask>(`/inspections/tasks/${id}/skip`, { reason }),
  checkinTask: (id: number, data: { checkin_location?: string; checkin_lat?: number; checkin_lng?: number; checkin_photos?: string[] }) =>
    post<InspectionRecord>(`/inspections/tasks/${id}/checkin`, data),
  checkoutRecord: (id: number, data: {
    checkout_location?: string
    checkout_lat?: number
    checkout_lng?: number
    checklist_answers?: Record<string, unknown>
    summary?: string
    rating?: number
    issues?: Record<string, unknown>[]
  }) => post<InspectionRecord>(`/inspections/records/${id}/checkout`, data),

  // 异常
  listIssues: (params?: {
    status?: InspectionIssueStatus
    severity?: InspectionSeverity
    plan_id?: number
    contract_id?: number
    keyword?: string
    per_page?: number
    page?: number
  }) => get('/inspections/issues', params),
  getIssue: (id: number) => get<InspectionIssue>(`/inspections/issues/${id}`),
  resolveIssue: (id: number, resolution: string) => post<InspectionIssue>(`/inspections/issues/${id}/resolve`, { resolution }),
  ignoreIssue: (id: number, reason: string) => post<InspectionIssue>(`/inspections/issues/${id}/ignore`, { reason }),
  convertIssue: (id: number) => post(`/inspections/issues/${id}/convert-to-work-order`),
}
