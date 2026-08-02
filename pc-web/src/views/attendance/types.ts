// 考勤模块共享类型 — 仅类型，无运行时副作用
// any 治理：用精确实体类型替换显式 any，每个实体带 [key: string]: unknown 兜底动态字段

export type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

// 捕获的异常形状（catch (e: unknown) 时安全的消息提取）
export type ErrorWithMessage = { message?: string }
export type ErrorWithResponse = { response?: { data?: { message?: string } } }

export interface AttendanceRecord {
  id?: number
  date?: string
  clock_in?: string | null
  clock_out?: string | null
  work_hours?: number | string | null
  status?: string
  clock_in_location?: string | null
  location?: string | null
  remark?: string | null
  user?: { name?: string; username?: string }
  employee?: { name?: string; username?: string }
  employee_name?: string
  [key: string]: unknown
}

export interface Shift {
  id: number | null
  name: string
  code: string
  start_time: string
  end_time: string
  work_hours: number
  late_threshold_minutes: number
  early_leave_threshold_minutes: number
  color: string
  sort_order: number
  remark: string
  is_active: boolean
  is_overnight?: boolean
  is_default?: boolean
  [key: string]: unknown
}

export interface LeaveRecord {
  id?: number
  user_id?: number
  type?: string
  start_date?: string
  end_date?: string
  days?: number
  reason?: string
  status?: string
  user?: { name?: string; username?: string }
  approver?: { name?: string }
  approved_at?: string | null
  [key: string]: unknown
}

export interface OvertimeRecord {
  id?: number
  user_id?: number
  overtime_date?: string
  start_time?: string
  end_time?: string
  hours?: number | string
  compensation_type?: string
  reason?: string
  status?: string
  user?: { name?: string; username?: string }
  approver?: { name?: string }
  approved_at?: string | null
  [key: string]: unknown
}

export interface DaySchedule {
  status?: string
  shift_color?: string
  shift_name?: string
  start_time?: string
  end_time?: string
  is_overnight?: boolean
  [key: string]: unknown
}

export interface ShiftReminder {
  shift_color?: string
  shift_name?: string
  date?: string
  start_time?: string
  end_time?: string
  minutes_until_start?: number
  [key: string]: unknown
}

export interface CalendarCell {
  iso: string
  day: number
  inMonth: boolean
  data: DaySchedule | null
}

export interface DayStat {
  present?: number
  late?: number
  absent?: number
  fieldWork?: number
  leave?: number
  [key: string]: unknown
}

export interface ClockForm {
  type: 'in' | 'out'
  location: string
  remark: string
  [key: string]: unknown
}

export interface FieldClockForm {
  type: 'in' | 'out'
  time: string
  location: string
  remark: string
  [key: string]: unknown
}

export interface SupplementClockForm {
  date: string
  type: 'in' | 'out' | 'field_in' | 'field_out'
  time: string
  location: string
  reason: string
  [key: string]: unknown
}
