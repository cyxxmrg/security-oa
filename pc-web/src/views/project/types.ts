// 项目相关共享类型 — 由各组件统一引用, 收窄类型、消除隐式松散类型

export type TagType = 'success' | 'primary' | 'info' | 'warning' | 'danger'

export interface Project {
  id: number
  name?: string
  code?: string
  status?: string
  stage?: string
  progress?: number | string
  total_amount?: number | string
  contract_amount?: number | string
  budget_device?: number | string
  budget_material?: number | string
  budget_labor?: number | string
  customer?: { id?: number; name?: string } | null
  manager?: { id?: number; name?: string } | null
  [key: string]: any
}

export interface PaymentNode { name?: string; amount?: number | string; paid_amount?: number | string; [key: string]: any }
export interface TimelineEntry { time?: string; title?: string; description?: string; [key: string]: any }
export interface Risk { id?: number; title?: string; is_mitigated?: boolean; is_closed?: boolean; [key: string]: any }
export interface MaterialStats { issued_records?: number; issued_cost?: number | string; [key: string]: any }
export interface PurchaseStats { fulfill_rate?: number | string; [key: string]: any }
export interface Tracking {
  current_stage?: string
  current_stage_label?: string
  display_progress?: number | string
  stage_progress?: any[]
  payment?: { contract_amount?: number | string; paid_amount?: number | string; payment_rate?: number | string; overdue_count?: number; overdue_amount?: number | string; pending_count?: number; nodes?: PaymentNode[]; [key: string]: any }
  purchase_stats?: PurchaseStats
  material_stats?: MaterialStats
  risks?: Risk[]
  timeline?: TimelineEntry[]
  [key: string]: any
}
export interface ConstructionLog { id?: number; [key: string]: any }
export interface ProcessInstance extends import('@/views/process/types').ProcessInstance { [key: string]: any }
export interface ProcessInspection extends import('@/views/process/types').Inspection { [key: string]: any }

export interface EmployeeOption {
  id: number
  name?: string
  [key: string]: unknown
}

export interface PoolItem {
  id?: number
  pool_no?: string
  name?: string
  contract_amount?: number | string
  signed_at?: string
  status?: string
  customer?: { id?: number; name?: string; [key: string]: unknown } | null
  opportunity?: { id?: number; name?: string; [key: string]: unknown } | null
  project?: { id?: number; name?: string; [key: string]: unknown } | null
  [key: string]: unknown
}

// 看板项目
export interface KanbanProject {
  id: number
  name?: string
  code?: string
  status?: string
  stage?: string
  total_amount?: number | string
  manager?: { id?: number; name?: string } | null
  [key: string]: unknown
}

// 日历付款项目
export interface CalendarItem {
  id: number
  project_id?: number
  project?: { id?: number; name?: string; code?: string } | null
  amount?: number | string
  status?: string
  due_date?: string
  [key: string]: unknown
}

// 甘特图任务
export interface GanttTask {
  id: number
  name?: string
  start_date?: string
  end_date?: string
  progress?: number
  status?: string
  assignee?: string
  [key: string]: unknown
}

// 项目阶段
export interface ProjectStage {
  id: number
  name?: string
  status?: string
  progress?: number
  start_date?: string
  end_date?: string
  [key: string]: unknown
}

// 创建项目表单
export interface CreateProjectForm {
  name: string
  code?: string
  customer_id?: number | null
  manager_id?: number | null
  total_amount?: number
  status?: string
  [key: string]: unknown
}

// ========== 工程辅助函数 ==========
export const formatDate = (s: string | null | undefined) => s ? s.slice(0, 10) : '-'

export const getCustomerName = (p: { customer?: { name?: string } | null }): string => p.customer?.name || '-'
export const getManagerName = (p: { manager?: { name?: string } | null }): string => p.manager?.name || '-'
export const computeTotalBudgetWan = (p: { total_amount?: number | string; contract_amount?: number | string }): string => {
  const v = p.total_amount || p.contract_amount || 0
  return (Number(v) / 10000).toFixed(2)
}
export const computeTotalBudgetYuan = (p: { total_amount?: number | string; contract_amount?: number | string }): string => {
  const v = p.total_amount || p.contract_amount || 0
  return Number(v).toFixed(2)
}

export const STAGES = ['mobilization', 'construction', 'acceptance', 'settlement', 'warranty', 'closed']

export const STAGE_LABEL_MAP: Record<string, string> = {
  mobilization: '进场准备', construction: '现场施工',
  acceptance: '验收交付', settlement: '项目结算',
  warranty: '售后质保', closed: '已关闭',
}
export const STAGE_INDEX_MAP: Record<string, number> = Object.fromEntries(STAGES.map((s, i) => [s, i]))

export const stageLabel = (s: string) => STAGE_LABEL_MAP[s] || s || '-'
export const statusLabel = (s: string) => ({ pending: '待启动', in_progress: '进行中', completed: '已完成', cancelled: '已取消' }[s] || s || '-')

export const typeLabel = (t: string) => ({
  camera: '监控', access_control: '门禁', alarm: '报警',
  comprehensive: '综合', network: '网络', cloud_platform: '云平台',
}[t] || t || '-')

export const riskActionLabel = (r: { is_mitigated?: boolean; is_closed?: boolean }): string => {
  if (r.is_mitigated) return '已缓解'
  if (r.is_closed) return '已关闭'
  return '处理'
}

export const RISK_ACTION_MAP: Record<string, string> = {
  mitigate: '缓解', close: '关闭', followup: '跟进',
}
