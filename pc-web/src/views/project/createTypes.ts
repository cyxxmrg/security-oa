// Project create shared types & constants — v0.3.13

export type ProjectTypeLabel = '监控' | '门禁' | '报警' | '综合'
export type BudgetKey = 'equipment' | 'material' | 'labor' | 'outsource' | 'other'

export interface BudgetEquipment {
  inventory_item_id?: number | null
  name: string
  brand: string
  model: string
  qty: number
  price: number
}
export interface BudgetMaterial {
  inventory_item_id?: number | null
  name: string
  spec: string
  unit: string
  qty: number
  price: number
}
export interface BudgetLabor {
  name: string
  qty: number
  days: number
  dailyRate: number
}
export interface BudgetOutsource {
  name: string
  vendor: string
  amount: number
  remark: string
}
export interface BudgetOther {
  name: string
  amount: number
  remark: string
}

export interface ProjectForm {
  name: string
  code: string
  customer: string
  type: ProjectTypeLabel
  location: string
  amount: number
  startDate: string
  endDate: string
  priority: number
  contractNo: string
  description: string
  manager: string
  assistant: string
  team: string[]
  safetyOfficer: string
  qcOfficer: string
  budget: {
    equipment: BudgetEquipment[]
    material: BudgetMaterial[]
    labor: BudgetLabor[]
    outsource: BudgetOutsource[]
    other: BudgetOther[]
  }
}

// 默认项目类型
export const PROJECT_TYPE_OPTIONS: { label: ProjectTypeLabel; value: ProjectTypeLabel }[] = [
  { label: '监控', value: '监控' },
  { label: '门禁', value: '门禁' },
  { label: '报警', value: '报警' },
  { label: '综合', value: '综合' },
]

// 中文 type → 后端 enum
export const TYPE_TO_ENUM: Record<ProjectTypeLabel, string> = {
  '监控': 'camera',
  '门禁': 'access_control',
  '报警': 'alarm',
  '综合': 'comprehensive',
}

// priority (1-5) → 后端 enum
export const PRIORITY_TO_ENUM: Record<number, string> = {
  1: 'urgent',
  2: 'high',
  3: 'normal',
  4: 'low',
  5: 'low',
}

// 空行模板
export const newBudgetRow = (key: BudgetKey) => {
  switch (key) {
    case 'equipment': return { inventory_item_id: null, name: '', brand: '', model: '', qty: 1, price: 0 }
    case 'material': return { inventory_item_id: null, name: '', spec: '', unit: '', qty: 1, price: 0 }
    case 'labor': return { name: '', qty: 1, days: 1, dailyRate: 0 }
    case 'outsource': return { name: '', vendor: '', amount: 0, remark: '' }
    case 'other': return { name: '', amount: 0, remark: '' }
  }
}

// 格式化金额
export const formatMoney = (v: number) =>
  Number(v || 0).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })

// 项目初始 form (含默认预算示例)
export const createEmptyForm = (): ProjectForm => ({
  name: '',
  code: '',
  customer: '',
  type: '综合',
  location: '',
  amount: 0,
  startDate: '',
  endDate: '',
  priority: 3,
  contractNo: '',
  description: '',
  manager: '',
  assistant: '',
  team: [],
  safetyOfficer: '',
  qcOfficer: '',
  budget: {
    equipment: [
      { inventory_item_id: null, name: '400万网络枪机', brand: '海康威视', model: 'DS-2CD2T47', qty: 20, price: 850 },
      { inventory_item_id: null, name: '网络球机', brand: '海康威视', model: 'DS-2DE7432', qty: 4, price: 4800 },
      { inventory_item_id: null, name: 'NVR录像机', brand: '海康威视', model: 'DS-9632N-I16', qty: 2, price: 12500 },
    ],
    material: [
      { inventory_item_id: null, name: '六类网线', spec: '305米/箱', unit: '箱', qty: 5, price: 850 },
    ],
    labor: [
      { name: '弱电工程师', qty: 3, days: 30, dailyRate: 500 },
    ],
    outsource: [],
    other: [
      { name: '运输费', amount: 2000, remark: '设备运输到现场' },
    ],
  },
})

// 预算小计
export const sumBudget = (rows: Record<string, unknown>[], field: string) =>
  rows.reduce((s, r) => s + Number(r[field] || 0), 0)
