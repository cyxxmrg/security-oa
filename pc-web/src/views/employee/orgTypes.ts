// Employee/Organization shared types & constants — v0.3.14

export type EmployeeStatus = 'active' | 'inactive'
export type PositionLevel = 'P1' | 'P2' | 'P3' | 'P4' | 'P5' | 'P6' | 'P7' | 'M1' | 'M2' | 'M3' | 'M4'

export const EMPLOYEE_STATUS_OPTIONS: { value: EmployeeStatus; label: string }[] = [
  { value: 'active', label: '在职' },
  { value: 'inactive', label: '离职' },
]

export const POSITION_LEVEL_OPTIONS: { value: PositionLevel; label: string }[] = [
  { value: 'P1', label: 'P1 - 助理' },
  { value: 'P2', label: 'P2 - 初级' },
  { value: 'P3', label: 'P3 - 中级' },
  { value: 'P4', label: 'P4 - 高级' },
  { value: 'P5', label: 'P5 - 资深' },
  { value: 'P6', label: 'P6 - 专家' },
  { value: 'P7', label: 'P7 - 高级专家' },
  { value: 'M1', label: 'M1 - 主管' },
  { value: 'M2', label: 'M2 - 经理' },
  { value: 'M3', label: 'M3 - 高级经理' },
  { value: 'M4', label: 'M4 - 总监' },
]

// 员工表单
export interface EmployeeForm {
  id?: number
  name: string
  username: string
  password: string
  role_id: number | null
  department_id: number | null
  position_id: number | null
  phone: string
  email: string
  hire_date: string
  is_active: boolean
}

// 部门表单
export interface DeptForm {
  id?: number
  name: string
  parent_id: number | null
  manager_id: number | null
  sort_order: number
}

// 岗位表单
export interface PositionForm {
  id?: number
  name: string
  department_id: number | null
  level: PositionLevel | ''
  description: string
  sort_order: number
}
