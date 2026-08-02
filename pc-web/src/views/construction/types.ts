// 施工模块共享类型 — 统一收敛各实体的松散 any, 收窄类型。
// 每个实体都保留 [key: string]: unknown, 以容忍后端可能返回的多余字段,
// 同时显式声明页面脚本/模板中实际访问的字段, 避免 unknown 带来的类型报错。

export type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

// ===== 嵌套引用 =====
export interface ProjectRef {
  id?: number
  name?: string | null
  code?: string | null
  [key: string]: unknown
}

export interface TeamRef {
  id?: number
  name?: string | null
  leader?: { name?: string | null } | null
  [key: string]: unknown
}

export interface UserRef {
  id?: number
  name?: string | null
  dept?: string | null
  [key: string]: unknown
}

// ===== 下拉选项类型 =====
export interface ProjectOption {
  id: number
  code?: string | null
  name?: string | null
  [key: string]: unknown
}

export interface UserOption {
  id: number
  name?: string | null
  dept?: string | null
  [key: string]: unknown
}

export interface CommencementOption {
  id: number
  code?: string | null
  project?: { name?: string | null } | null
  team?: { name?: string | null } | null
  [key: string]: unknown
}

export interface ProcessOption {
  id: number
  name?: string | null
  code?: string | null
  [key: string]: unknown
}

export interface TeamOption {
  id: number
  name?: string | null
  leader?: { name?: string | null } | null
  [key: string]: unknown
}

// ===== 实体 =====
export interface ExternalWork {
  id?: number | string
  code: string
  title: string
  project_id?: number | string | null
  project?: ProjectRef | null
  budget?: number | string | null
  award_amount?: number | string | null
  bid_count?: number | string | null
  deadline?: string | null
  status: string
  creator?: { name?: string | null } | null
  created_at?: string | null
  [key: string]: unknown
}

export interface Bid {
  id?: number | string
  supplier_name?: string | null
  bidder_name?: string | null
  supplier?: { name?: string | null } | null
  amount?: number | string | null
  duration_days?: number | string | null
  status?: string | null
  created_at?: string | null
  [key: string]: unknown
}

export interface DailyReport {
  id?: number | string
  date?: string | null
  weather?: string | null
  commencement_id?: number | string | null
  process_id?: number | string | null
  worker_count?: number | string | null
  work_hours?: number | string | null
  progress?: number | string | null
  issues?: string | null
  photos?: string | string[] | null
  remark?: string | null
  status?: string | null
  [key: string]: unknown
}

export interface OverdueItem {
  id?: number | string
  team_name?: string | null
  date?: string | null
  project_name?: string | null
  commencement_id?: number | string | null
  [key: string]: unknown
}

export interface TeamMember {
  id?: number | string
  user_id?: number | string | null
  user?: { name?: string | null } | null
  user_name?: string | null
  role?: string | null
  specialty?: string | null
  daily_wage?: number | string | null
  join_date?: string | null
  status?: string | null
  [key: string]: unknown
}

export interface TeamProject {
  code?: string | null
  name?: string | null
  stage?: string | null
  status?: string | null
  [key: string]: unknown
}

export interface LogRow {
  date?: string | null
  work_date?: string | null
  weather?: string | null
  progress?: number | string | null
  status?: string | null
  remark?: string | null
  process_name?: string | null
  process?: string | null
  worker_count?: number | string | null
  [key: string]: unknown
}

export interface Team {
  id?: number | string
  name: string
  type?: string | null
  status?: string | null
  phone?: string | null
  specialty?: string[] | null
  leader_id?: number | string | null
  leader?: { name?: string | null } | null
  leader_name?: string | null
  member_count?: number | null
  members?: TeamMember[]
  projects?: TeamProject[]
  logs?: LogRow[]
  remark?: string | null
  created_at?: string | null
  updated_at?: string | null
  [key: string]: unknown
}

export interface Rectification {
  id?: number | string
  code: string
  title: string
  project_id?: number | string | null
  project?: ProjectRef | null
  owner_id?: number | string | null
  owner?: { name?: string | null } | null
  deadline?: string | null
  status: string
  completed_at?: string | null
  result?: string | null
  creator?: { name?: string | null } | null
  created_at?: string | null
  [key: string]: unknown
}

export interface Commencement {
  id?: number | string
  code: string
  project_id?: number | string | null
  project?: ProjectRef | null
  team_id?: number | string | null
  team?: TeamRef | null
  planned_start?: string | null
  planned_end?: string | null
  worker_count?: number | string | null
  estimated_hours?: number | string | null
  actual_hours?: number | string | null
  work_scope?: string | null
  remark?: string | null
  status: string
  creator?: { name?: string | null } | null
  created_at?: string | null
  updated_at?: string | null
  // 打印字段
  commencement_date?: string | null
  planned_end_date?: string | null
  work_content?: string | null
  work_location?: string | null
  safety_requirements?: string | null
  remarks?: string | null
  [key: string]: unknown
}

export interface WorkProcess {
  id?: number | string
  name: string
  description?: string | null
  estimated_hours?: number | string | null
  sequence?: number | string | null
  status?: string | null
  [key: string]: unknown
}
