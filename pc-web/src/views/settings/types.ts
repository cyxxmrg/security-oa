// Shared entity types for src/views/settings
// Each entity includes an index signature so loosely-shaped API payloads remain assignable.

export type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

export interface PasswordLog {
  time: string
  ip: string
  action: string
  description: string
  user_agent: string
  [key: string]: unknown
}

export interface DailySeriesItem {
  day: string
  role_changed: number
  temporary_role_granted: number
  role_revoked: number
  [key: string]: unknown
}

export interface PermissionSummary {
  by_action: {
    role_changed: number
    temporary_role_granted: number
    role_revoked: number
    [key: string]: unknown
  }
  total: number
  daily_series: DailySeriesItem[]
  [key: string]: unknown
}

export interface AuditLogRow {
  id: number
  action: string
  operator_id: number
  operator: string
  description: string
  target_user_id: number | null
  target_username?: string
  target_name?: string
  ip: string
  created_at: string
  [key: string]: unknown
}

export interface CurrentUser {
  username: string
  roles: string[]
  [key: string]: unknown
}

export interface PermissionNode {
  id?: number | string
  name?: string
  module?: string
  label?: string
  children?: PermissionNode[]
  [key: string]: unknown
}
