export const expenseCategoryMap: Record<string, string> = {
  travel: '差旅费',
  hospitality: '招待费',
  office: '办公费',
  transport: '交通费',
  meal: '餐饮费',
  accommodation: '住宿费',
  training: '培训费',
  communication: '通讯费',
  project_cost: '项目成本',
  labor: '人工费',
  outsource: '外包费',
  material: '材料费',
  other: '其他',
}

export const approvalSubTypeMap: Record<string, string> = {
  general: '通用审批',
  expense: '报销',
  reimbursement: '报销',
  reimburse: '差旅报销',
  payment: '付款单',
  receivable: '应收确认',
  payable: '应付确认',
  purchase: '采购',
  purchase_requirement: '采购需求',
  purchase_order: '采购订单',
  purchase_plan: '采购计划',
  purchase_payment: '采购付款',
  commission: '居间费',
  salary: '薪资调整',
  loan: '借款',
  leave: '请假',
  overtime: '加班',
  vehicle: '用车',
  vehicle_dispatch: '派车',
  sales: '销售',
  discount: '折扣',
  transfer: '调拨',
  attendance: '考勤异常',
  customer: '客户',
  resignation: '离职',
  onboarding: '入职',
  material_request: '物料申领',
  'material-request': '物料申领',
  material_return: '物料退库',
  'material-return': '物料退库',
  project_create: '新建项目',
  project_stage: '阶段流转',
  project_close: '项目结项',
  contract: '合同审批',
  contract_change: '合同变更',
  settlement: '项目结算',
  warranty: '质保金',
  design: '设计方案',
  change: '设计变更',
  other: '其他',
}

export const statusMap: Record<string, string> = {
  draft: '草稿',
  submitted: '待审批',
  pending: '待审批',
  pending_approval: '待审批',
  approved: '已通过',
  rejected: '已驳回',
  transferred: '已转交',
  cancelled: '已撤销',
  canceled: '已取消',
  paid: '已付款',
  completed: '已完成',
  done: '已完成',
  failed: '失败',
  success: '成功',
}

export const priorityMap: Record<string, string> = {
  urgent: '紧急',
  high: '高',
  medium: '普通',
  normal: '普通',
  low: '低',
}

export const paymentMethodMap: Record<string, string> = {
  bank: '银行转账',
  bank_transfer: '银行转账',
  cash: '现金',
  alipay: '支付宝',
  wechat: '微信',
  check: '支票',
  cheque: '支票',
  acceptance: '承兑汇票',
  other: '其他',
}

export function paymentMethodLabel(value: unknown): string {
  return labelFromMap(value, paymentMethodMap)
}

export const projectStageMap: Record<string, string> = {
  // V1.2.10 与后端 migration 2024_01_02_000001 对齐 (7 个值)
  // 移除了前端原有的 debug/acceptance (后端 CHECK 约束无此值)
  initiation: '立项',
  inquiry: '询价',
  contract: '合同阶段',
  purchase: '采购阶段',
  construction: '施工阶段',
  settlement: '结算阶段',
  warranty: '维保阶段',
}

const tokenMap: Record<string, string> = {
  ...expenseCategoryMap,
  ...approvalSubTypeMap,
  ...statusMap,
  ...priorityMap,
  ...paymentMethodMap,
  ...projectStageMap,
}

export function labelFromMap(value: unknown, map: Record<string, string>, empty = '-'): string {
  const key = String(value ?? '').trim()
  if (!key) return empty
  return map[key] || key
}

export function expenseCategoryLabel(value: unknown): string {
  return labelFromMap(value, expenseCategoryMap)
}

export function approvalSubTypeLabel(value: unknown): string {
  return labelFromMap(value, approvalSubTypeMap)
}

export function statusLabel(value: unknown): string {
  return labelFromMap(value, statusMap)
}

export function priorityLabel(value: unknown): string {
  return labelFromMap(value, priorityMap)
}

export function projectStageLabel(value: unknown): string {
  return labelFromMap(value, projectStageMap)
}

export function localizeEnumText(value: unknown): string {
  let text = String(value ?? '')
  for (const [key, label] of Object.entries(tokenMap)) {
    text = text.replace(new RegExp(`(^|[^A-Za-z0-9_])${escapeRegExp(key)}(?=$|[^A-Za-z0-9_])`, 'g'), `$1${label}`)
  }
  return text
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
